<?php

namespace App\Models;

use App\Enums\AreaProfesional;
use App\Enums\EstadoProfesional;
use App\Enums\ModalidadProfesional;
use Database\Factories\ProfesionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Perfil de mano profesional (voluntariado especializado gratuito).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $zona_id
 * @property AreaProfesional $area
 * @property string $nombre
 * @property string $titulo
 * @property string $email
 * @property string|null $celular
 * @property string|null $tarjeta_profesional
 * @property ModalidadProfesional $modalidad
 * @property string $disponibilidad
 * @property string $descripcion
 * @property string|null $inicial
 * @property EstadoProfesional $estado
 * @property Carbon|null $enviado_at
 * @property Carbon|null $aprobado_at
 * @property int|null $revisado_por
 * @property string|null $nota_revision
 * @property Carbon|null $acepta_terminos_at
 */
class Profesional extends Model
{
    /** @use HasFactory<ProfesionalFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Eloquent pluralizaría "profesionals".
     */
    protected $table = 'profesionales';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'zona_id',
        'municipio_id',
        'area',
        'nombre',
        'titulo',
        'email',
        'celular',
        'tarjeta_profesional',
        'modalidad',
        'disponibilidad',
        'descripcion',
        'inicial',
        'estado',
        'enviado_at',
        'aprobado_at',
        'revisado_por',
        'nota_revision',
        'acepta_terminos_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'zona_id' => 'integer',
            'municipio_id' => 'integer',
            'area' => AreaProfesional::class,
            'modalidad' => ModalidadProfesional::class,
            'estado' => EstadoProfesional::class,
            'enviado_at' => 'datetime',
            'aprobado_at' => 'datetime',
            'revisado_por' => 'integer',
            'acepta_terminos_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(ProfesionalDocumento::class);
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(ProfesionalSolicitud::class);
    }

    public function moderacionAcciones(): HasMany
    {
        return $this->hasMany(ProfesionalModeracionAccion::class);
    }
}
