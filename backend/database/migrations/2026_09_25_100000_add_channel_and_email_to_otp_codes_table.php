<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile API (Api\AuthController) issues email OTPs and hands out a
 * short-lived verification token after a successful OTP check — neither had
 * a column to live in, so every OtpManager call failed with "no such column
 * channel". Purely additive: existing rows are all SMS codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            $table->string('email')->nullable()->index()->after('phone');
            $table->string('channel', 10)->default('sms')->after('email');
            $table->string('verified_token', 80)->nullable()->index();
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['verified_token']);
            $table->dropColumn(['email', 'channel', 'verified_token', 'verified_at']);
        });
    }
};
