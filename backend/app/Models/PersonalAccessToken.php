<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Bearer-token authentication for the mobile REST API. Self-contained — no
 * external Sanctum dependency — so the API works on a plain Laravel install.
 *
 * Tokens are returned to the client once as `<id>|<plaintext>`, only the
 * SHA-256 hash of the plaintext portion is stored (a DB leak never exposes
 * live tokens, same design as Laravel's built-in password reset tokens).
 */
class PersonalAccessToken extends Model
{
    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Shares Sanctum's personal_access_tokens table, so the owner lives in
     * the tokenable morph columns rather than a user_id column.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tokenable_id');
    }

    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * Create a token for a user. Persists the hash; returns the full
     * `id|plaintext` pair to hand to the client.
     */
    public static function issue(
        User $user,
        string $name = 'mobile',
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null
    ): string {
        $plaintext = Str::random(64);

        $token = static::create([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->id,
            'name' => $name,
            'token' => static::hash($plaintext),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return sprintf('%s|%s', $token->id, $plaintext);
    }

    /**
     * Parse an `id|plaintext` token into a model, validating expiry and the
     * stored hash. Returns null when the token is unknown/expired/revoked.
     */
    public static function verify(?string $token): ?self
    {
        if (! $token || ! str_contains($token, '|')) {
            return null;
        }

        [$id, $plaintext] = explode('|', $token, 2);

        $model = static::find($id);

        if (! $model || ! hash_equals($model->token, static::hash($plaintext))) {
            return null;
        }

        if ($model->expires_at && $model->expires_at->isPast()) {
            return null;
        }

        return $model;
    }

    public function can(string $ability): bool
    {
        $abilities = $this->abilities ?? ['*'];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function touchLastUsed(): void
    {
        if ($this->exists && $this->last_used_at?->diffInMinutes(now()) < 5) {
            return;
        }

        $this->forceFill(['last_used_at' => now()])->save();
    }
}