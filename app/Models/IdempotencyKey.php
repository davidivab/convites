<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Clave de idempotencia para operaciones críticas (aportes, registro, moderación).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $key
 * @property string $route
 * @property int|null $response_code
 * @property array<string, mixed>|null $response_body
 * @property \Illuminate\Support\Carbon $expires_at
 */
class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'key',
        'route',
        'response_code',
        'response_body',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'response_code' => 'integer',
            'response_body' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
