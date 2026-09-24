<?php

namespace App\Services\Vendors;

use App\Models\User;
use App\Models\Vendor;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\PanelAccessInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Gives a Vendor row a panel login: a User to sign in as, the Spatie role that
 * decides what it may see, and the one-time link it uses to choose a password.
 *
 * What the login can actually do is never decided here — that is entirely the
 * Filament Shield permissions attached to the role, which the admin edits.
 */
class PanelAccessService
{
    /**
     * Create (or reattach) the login for a vendor and send the setup link.
     * Safe to call repeatedly: an existing linked user is reused rather than
     * duplicated.
     *
     * Returns false when the vendor has no email — the link has nowhere to go,
     * and the vendor still works through its signed invitation token.
     *
     * @throws \RuntimeException if the email belongs to a user this vendor
     *                           does not already own — see ensureUser().
     */
    public function grant(Vendor $vendor, bool $isResend = false): bool
    {
        $user = $this->ensureUser($vendor);

        if (! $user) {
            return false;
        }

        return $this->sendSetupLink($vendor->fresh('user'), $user, $isResend);
    }

    /**
     * Create/sync the login without mailing anything — used when only the role
     * changed, so an existing contact is not spammed with a fresh link.
     */
    public function grantWithoutNotifying(Vendor $vendor): bool
    {
        return $this->ensureUser($vendor) !== null;
    }

    /**
     * Never adopts a user this vendor doesn't already own. A brand-new vendor
     * row always gets a brand-new user — reusing whoever else happens to hold
     * that email (a customer, staff, or a super_admin) would hand that
     * account's login to whoever the admin sends the invite to.
     *
     * @throws \RuntimeException if the email is already someone else's login.
     */
    private function ensureUser(Vendor $vendor): ?User
    {
        if (blank($vendor->email)) {
            return null;
        }

        return DB::transaction(function () use ($vendor) {
            $user = $vendor->user;

            if ($user) {
                // Keep the login's address in step with the vendor record, so
                // a corrected email does not leave the user signing in with
                // the old one — but only once we know no other account is
                // already sitting on that address.
                $collision = User::where('email', $vendor->email)
                    ->whereKeyNot($user->id)
                    ->first();

                if ($collision) {
                    throw new \RuntimeException(self::collisionMessage($collision));
                }

                $user->forceFill(['email' => $vendor->email])->save();
            } else {
                $collision = User::where('email', $vendor->email)->first();

                if ($collision) {
                    throw new \RuntimeException(self::collisionMessage($collision));
                }

                $user = User::create([
                    'name' => $vendor->name,
                    'email' => $vendor->email,
                    // No password yet: the setup link is the only way in, and
                    // a null password cannot be guessed or brute-forced.
                    'password' => null,
                ]);
            }

            $this->syncRole($user, $vendor->access_role);

            if ($vendor->user_id !== $user->id) {
                $vendor->forceFill(['user_id' => $user->id])->save();
            }

            return $user;
        });
    }

    /**
     * Names what the colliding account actually is, so the admin isn't left
     * guessing whether it's a staff login, another vendor, or a customer —
     * "already belongs to a different account" alone was the kind of vague
     * error that made this screen feel unpredictable. Public: VendorForm's
     * live "is this email taken" check surfaces the same wording before the
     * admin even submits, instead of only finding out on save.
     */
    public static function collisionMessage(User $existing): string
    {
        $reason = match (true) {
            $existing->vendor !== null => 'another vendor\'s login',
            $existing->roles->isNotEmpty() => 'a staff member\'s login',
            default => 'a customer account',
        };

        return "That email already belongs to {$reason}. Use a different address for this vendor.";
    }

    /** Why the last sendSetupLink() call did not deliver, for the admin. */
    public ?string $lastError = null;

    /**
     * The setup link from the last sendSetupLink() call, so an admin can pass
     * it on by hand when email delivery isn't working.
     */
    public ?string $lastSetupUrl = null;

    /**
     * Issue a fresh setup token and notify the vendor by email (and WhatsApp
     * when a number is on file). Returns true only when the email was really
     * handed to a mail server; otherwise $lastError says why and
     * $lastSetupUrl holds the link to share manually.
     */
    public function sendSetupLink(Vendor $vendor, ?User $user = null, bool $isResend = false): bool
    {
        $this->lastError = null;
        $this->lastSetupUrl = null;
        $user ??= $vendor->user;

        if (! $user || blank($user->email)) {
            $this->lastError = 'This vendor has no email address.';

            return false;
        }

        $token = Password::broker('panel_invites')->createToken($user);
        $this->lastSetupUrl = route('panel.password.setup', ['token' => $token, 'email' => $user->email]);
        $notification = new PanelAccessInvitation(
            vendor: $vendor,
            setupUrl: $this->lastSetupUrl,
            isResend: $isResend,
        );

        // Email and WhatsApp are sent separately so a WhatsApp outage can't
        // make a delivered email look like a failure (or vice versa).
        try {
            $vendor->notifyNow($notification, ['mail']);
        } catch (\Throwable $e) {
            // A mail failure must not roll back the account that was just
            // created — the admin can resend, or share the link by hand.
            Log::warning('Failed to send panel access invitation.', [
                'vendor_id' => $vendor->id,
                'exception' => $e->getMessage(),
            ]);
            $this->lastError = 'The mail server refused the email: '.Str::limit($e->getMessage(), 220);

            return false;
        }

        $this->sendWhatsAppCopy($vendor, $notification);

        // The "log"/"array" mailers accept every message without sending it,
        // so a live site left on them would report success while nothing
        // ever arrives.
        $mailer = config('mail.default');
        if (in_array($mailer, ['log', 'array'], true) && ! app()->runningUnitTests()) {
            $this->lastError = "Email is not set up on this server (MAIL_MAILER={$mailer}), so the email was only written to the log file.";

            return false;
        }

        return true;
    }

    /**
     * Human-readable failure for admin notifications: the reason plus the
     * link to send by hand.
     */
    public function failureDetails(): string
    {
        $details = $this->lastError ?? 'The setup link could not be sent.';

        if ($this->lastSetupUrl) {
            $details .= "\n\nSend this link to the vendor yourself (valid 48 hours):\n{$this->lastSetupUrl}";
        }

        return $details;
    }

    private function sendWhatsAppCopy(Vendor $vendor, PanelAccessInvitation $notification): void
    {
        if (! in_array(WhatsAppChannel::class, $notification->via($vendor), true)) {
            return;
        }

        try {
            $vendor->notifyNow($notification, [WhatsAppChannel::class]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send panel access invitation over WhatsApp.', [
                'vendor_id' => $vendor->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Point the user at exactly one of the two access roles. Roles are created
     * on demand with no permissions attached, so a brand-new role grants
     * nothing until an admin ticks boxes in Shield.
     */
    private function syncRole(User $user, string $accessRole): void
    {
        $roleName = $accessRole === Vendor::ACCESS_ROLE_ADMIN
            ? Vendor::ACCESS_ROLE_ADMIN
            : Vendor::ACCESS_ROLE_VENDOR;

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        // Only ever swap between the two roles this service manages — a
        // super_admin who also happens to be a vendor contact keeps that role.
        $managed = Vendor::ACCESS_ROLES;
        $keep = $user->roles->pluck('name')->reject(fn ($n) => in_array($n, $managed, true));

        $user->syncRoles($keep->push($role->name)->unique()->all());
    }

    /**
     * Revoke panel access without deleting history: the user keeps existing,
     * but loses the managed role and any usable password.
     */
    public function revoke(Vendor $vendor): void
    {
        $user = $vendor->user;

        if (! $user) {
            return;
        }

        $keep = $user->roles->pluck('name')
            ->reject(fn ($n) => in_array($n, Vendor::ACCESS_ROLES, true));

        $user->syncRoles($keep->all());
        $user->forceFill(['password' => null, 'remember_token' => Str::random(60)])->save();
    }
}
