<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one plain email through the configured mailer, so the SMTP settings
 * in .env can be checked on the server without clicking through the admin:
 *
 *   php artisan config:clear
 *   php artisan mail:test someone@example.com
 *
 * Prints the mail server's own error when delivery fails (wrong password,
 * blocked port, sender not allowed).
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {to : Address to send the test email to}';

    protected $description = 'Send a test email through the configured mailer to check the SMTP settings';

    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("\"{$to}\" is not a valid email address.");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');

        $this->line("Mailer: {$mailer}");
        $this->line('From:   '.config('mail.from.address'));

        if ($mailer === 'smtp') {
            $this->line('Server: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        }

        try {
            Mail::raw(
                'This is a test email from '.config('app.name').'. If you can read this, outgoing email is working.',
                fn ($message) => $message->to($to)->subject(config('app.name').' test email'),
            );
        } catch (\Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER={$mailer} only records emails, it never sends them. Set MAIL_MAILER=smtp in .env.");

            return self::FAILURE;
        }

        $this->info("Sent to {$to}. Check that inbox (and its spam folder).");

        return self::SUCCESS;
    }
}
