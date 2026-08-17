<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Documento adjunto a un perfil profesional.
 *
 * @property int $id
 * @property int $profesional_id
 * @property string $disk
 * @property string $path
 * @property string $nombre_original
 * @property string|null $mime
 * @property int $tamanio_bytes
 */
class ProfesionalDocumento extends Model
{
    use SoftDeletes;

    protected $table = 'profesional_documentos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profesional_id',
        'disk',
        'path',
        'nombre_original',
        'mime',
        'tamanio_bytes',
        'checksum',
        'uploaded_by',
        'virus_scan_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profesional_id' => 'integer',
            'tamanio_bytes' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }

    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
