<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Vendors\PanelAccessService;
use App\Support\PanelSessionParking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * A vendor's own login, entirely separate from the Filament admin panel —
 * same 'web' guard and User row (see PanelSessionParking's docblock for why
 * there is only one guard), but its own form, its own branding, and its own
 * post-login destination. A vendor never sees /admin, and staff never see
 * this.
 */
class VendorAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Vendor::current() !== null) {
            return redirect()->route('vendor.dashboard');
        }

        // Same "an OTP-only customer sitting in the guard slot must not be
        // silently signed out" concern the admin login has — park them so
        // this form loads instead of inheriting their session, and they
        // come back exactly as they were if they never actually sign in
        // here.
        if (Auth::check() && blank(Auth::user()->password)) {
            PanelSessionParking::park(Auth::user());
        }

        return view('vendor.portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, remember: true)) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Those details don\'t match a vendor account.');
        }

        $request->session()->regenerate();

        if (Vendor::current() === null) {
            // The password matched a real account, just not a vendor one
            // (e.g. a customer who happens to share this email) — undo the
            // login rather than leave them signed in somewhere with nothing
            // to see.
            Auth::logout();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'That account is not a vendor login.');
        }

        return redirect()->route('vendor.dashboard');
    }

    public function showForgotPassword(): View|RedirectResponse
    {
        if (Vendor::current() !== null) {
            return redirect()->route('vendor.dashboard');
        }

        return view('vendor.portal.forgot-password');
    }

    /**
     * Self-service "forgot password": emails the same one-time password link
     * an admin's "Resend setup link" sends, landing on the same
     * /panel/set-password page. Email only — this form is reachable by
     * anyone, so it must not be able to trigger WhatsApp messages.
     *
     * The reply is identical whether or not the email belongs to a vendor,
     * so the form can't be used to find out which addresses have accounts.
     */
    public function sendResetLink(Request $request, PanelAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $vendor = User::where('email', $validated['email'])->first()?->vendor;

        if ($vendor?->receivesBiddingNotifications() && $vendor->is_active) {
            if (! $access->sendSetupLink($vendor, isResend: true, withWhatsApp: false)) {
                Log::warning('Vendor password reset email was not delivered.', [
                    'vendor_id' => $vendor->id,
                    'reason' => $access->lastError,
                ]);
            }
        }

        return redirect()->route('vendor.login')->with(
            'status',
            'If that email belongs to a vendor account, a password reset link is on its way. Check your inbox (and spam folder).',
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->regenerateToken();

        PanelSessionParking::restore();

        return redirect()->route('vendor.login');
    }
}
