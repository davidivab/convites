<?php

namespace App\Models;

use App\Enums\TipoDocumentoLegal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versión publicada de un documento legal (términos, descargo, privacidad).
 *
 * @property int $id
 * @property TipoDocumentoLegal $tipo
 * @property string $version
 * @property string $titulo
 * @property string $contenido
 * @property \Illuminate\Support\Carbon|null $publicado_at
 * @property bool $vigente
 */
class DocumentoLegal extends Model
{
    protected $table = 'documentos_legales';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tipo',
        'version',
        'titulo',
        'contenido',
        'publicado_at',
        'vigente',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoLegal::class,
            'publicado_at' => 'datetime',
            'vigente' => 'boolean',
        ];
    }

    public function aceptaciones(): HasMany
    {
        return $this->hasMany(DocumentoLegalAceptacion::class);
    }
}
