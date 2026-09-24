<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email delivery for the mobile app's email-OTP login/registration. Sends a
 * plain-text message through whatever mailer MAIL_MAILER points at (the
 * "log" mailer in local dev, so the code lands in storage/logs).
 */
class MailOtpGateway
{
    public function send(string $email, string $code): bool
    {
        try {
            Mail::raw(
                "Your Estele verification code is {$code}. It expires in 5 minutes. Do not share this code with anyone.",
                fn ($message) => $message->to($email)->subject('Your Estele verification code'),
            );
        } catch (Throwable $e) {
            Log::warning('Email OTP could not be sent', ['email' => $email, 'error' => $e->getMessage()]);

            return false;
        }

        return true;
    }
}
