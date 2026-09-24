<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile API (Api\AuthController) issues email OTPs and hands out a
 * short-lived verification token after a successful OTP check — neither had
 * a column to live in, so every OtpManager call failed with "no such column
 * channel". Purely additive: existing rows are all SMS codes.
 *
 * Every step checks first: some databases (production) already received
 * some of these columns outside this migration, and re-adding one aborts
 * the whole run with "Duplicate column name".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
        });

        foreach ([
            'email' => fn (Blueprint $t) => $t->string('email')->nullable()->after('phone'),
            'channel' => fn (Blueprint $t) => $t->string('channel', 10)->default('sms')->after('email'),
            'verified_token' => fn (Blueprint $t) => $t->string('verified_token', 80)->nullable(),
            'verified_at' => fn (Blueprint $t) => $t->timestamp('verified_at')->nullable(),
        ] as $column => $add) {
            if (! Schema::hasColumn('otp_codes', $column)) {
                Schema::table('otp_codes', $add);
            }
        }

        foreach (['email', 'verified_token'] as $column) {
            if (! $this->hasIndexOn('otp_codes', $column)) {
                Schema::table('otp_codes', fn (Blueprint $t) => $t->index($column));
            }
        }
    }

    public function down(): void
    {
        foreach (Schema::getIndexes('otp_codes') as $index) {
            if (in_array($index['columns'], [['email'], ['verified_token']], true) && ! $index['primary']) {
                Schema::table('otp_codes', fn (Blueprint $t) => $t->dropIndex($index['name']));
            }
        }

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['email', 'channel', 'verified_token', 'verified_at'],
                fn ($column) => Schema::hasColumn('otp_codes', $column),
            )));
        });
    }

    private function hasIndexOn(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === [$column]) {
                return true;
            }
        }

        return false;
    }
};
