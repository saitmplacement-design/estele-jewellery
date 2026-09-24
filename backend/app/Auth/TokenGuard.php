<?php

namespace App\Auth;

use App\Models\PersonalAccessToken;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Macroable;

/**
 * Guard that authenticates API requests from a `Authorization: Bearer <token>`
 * header. Token resolution is delegated to PersonalAccessToken::verify()
 * (hash-compare, expiry check, ability check) — see that model.
 */
class TokenGuard implements Guard
{
    use GuardHelpers, Macroable;

    public function __construct(
        UserProvider $provider,
        private readonly Request $request
    ) {
        $this->provider = $provider;
        $this->user = null;
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getToken();
        $model = PersonalAccessToken::verify($token);

        if (! $model) {
            return $this->user = null;
        }

        $model->touchLastUsed();

        return $this->user = $this->provider->retrieveById($model->tokenable_id);
    }

    public function validate(array $credentials = []): bool
    {
        return (bool) $this->user();
    }

    /**
     * Returns the raw token string from the request's Authorization header.
     */
    public function getToken(): ?string
    {
        $header = $this->request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        $sessionToken = $this->request->query('api_token');

        return is_string($sessionToken) && $sessionToken !== '' ? $sessionToken : null;
    }
}