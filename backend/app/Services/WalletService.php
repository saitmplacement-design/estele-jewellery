<?php

namespace App\Services;

use App\Mail\WalletCredited;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The only code path allowed to change users.wallet_balance or write a
 * wallet_transactions row. Every caller (reward approval, checkout debit,
 * cancel/return refund) goes through here so balance math never happens
 * twice in two different places.
 */
class WalletService
{
    public function credit(User $user, float $amount, string $reason, ?Model $reference = null, ?\DateTimeInterface $expiresAt = null): WalletTransaction
    {
        $transaction = DB::transaction(function () use ($user, $amount, $reason, $reference, $expiresAt) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            $newBalance = (float) $locked->wallet_balance + $amount;
            $locked->update(['wallet_balance' => $newBalance]);

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'expires_at' => $expiresAt,
            ]);
        });

        // Phone-OTP customers have no email (users.email is nullable since
        // make_email_nullable_on_users_table) — Mail::to(null) throws, and the
        // credit above has already committed, so the caller would see a 500 on
        // a wallet that was in fact credited and be tempted to credit it twice.
        // The live server has no queue worker (QUEUE_CONNECTION=sync), so
        // queue() sends right here; a mail-server failure must not turn a
        // committed credit (an order cancel, a refund) into an error either.
        if (filled($user->email)) {
            try {
                Mail::to($user->email)->queue(new WalletCredited($transaction->fresh(['user'])));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $transaction;
    }

    /**
     * @throws \DomainException if $amount exceeds the user's current balance.
     */
    public function debit(User $user, float $amount, string $reason, ?Model $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $reason, $reference) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ($amount > (float) $locked->wallet_balance) {
                throw new \DomainException('Wallet balance is insufficient for this debit.');
            }

            $newBalance = (float) $locked->wallet_balance - $amount;
            $locked->update(['wallet_balance' => $newBalance]);

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);
        });
    }

    /**
     * Strikes an expiring credit off the balance once its window has passed
     * (used by sell:wallet-expire). Only the credit's own row is unwound —
     * however much of it is still unused at expiry, up to that amount. The
     * write goes through WalletService so balance math stays single-sourced.
     */
    public function expireCredit(WalletTransaction $credit): void
    {
        if ($credit->type !== 'credit' || $credit->expired_at !== null) {
            return;
        }

        DB::transaction(function () use ($credit) {
            $lockedUser = User::whereKey($credit->user_id)->lockForUpdate()->first();

            $outstanding = min((float) $credit->amount, (float) $lockedUser?->wallet_balance ?? 0.0);

            if ($outstanding > 0) {
                $newBalance = (float) $lockedUser->wallet_balance - $outstanding;
                $lockedUser->update(['wallet_balance' => $newBalance]);

                WalletTransaction::create([
                    'user_id' => $lockedUser->id,
                    'type' => 'debit',
                    'amount' => $outstanding,
                    'balance_after' => $newBalance,
                    'reason' => 'wallet_expiry',
                    'reference_type' => $credit->getMorphClass(),
                    'reference_id' => $credit->id,
                ]);
            }

            $credit->update(['expired_at' => now()]);
        });
    }
}
