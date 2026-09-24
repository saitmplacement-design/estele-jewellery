<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class OtpCode extends Model
{
    use Prunable;

    protected $fillable = [
        'phone',
        'email',
        'channel',
        'code_hash',
        'expires_at',
        'attempts',
        'consumed_at',
        'verified_token',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => 'string',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }
}
