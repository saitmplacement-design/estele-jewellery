<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile API binds a signed-in user's cart to their account
 * (Api\CartController, Api\AuthController's guest-cart merge) and records
 * who wrote a review (Api\ContentController::storeReview, ReviewController).
 * Both writes failed without these columns. Nullable: website carts stay
 * keyed by session_id, and existing reviews have no author account.
 *
 * Skips a table that already has the column (added outside this migration
 * on some databases), so it can't abort with "Duplicate column name".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('carts', 'user_id')) {
            Schema::table('carts', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('reviews', 'user_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['reviews', 'carts'] as $name) {
            if (Schema::hasColumn($name, 'user_id')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
            }
        }
    }
};
