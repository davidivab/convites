<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aceptación auditable de un documento legal por un usuario.
 *
 * @property int $id
 * @property int $user_id
 * @property int $documento_legal_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $contexto
 * @property \Illuminate\Support\Carbon $aceptado_at
 */
class DocumentoLegalAceptacion extends Model
{
    protected $table = 'documento_legal_aceptaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'documento_legal_id',
        'ip_address',
        'user_agent',
        'contexto',
        'aceptado_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'documento_legal_id' => 'integer',
            'aceptado_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoLegal::class, 'documento_legal_id');
    }
}
