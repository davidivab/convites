<?php

namespace App\Models;

use App\Enums\EstadoIniciativa;
use App\Enums\Urgencia;
use Database\Factories\IniciativaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Iniciativa / convite comunitario.
 *
 * Escalabilidad:
 * - progreso_cache / asistentes_count son denormalizados (ver IniciativaProgresoService).
 * - lugar_exacto es privado; lugar_convite es público.
 * - persona_responsable / quien_respalda / telefono_contacto NO se exponen en API pública.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $zona_id
 * @property int|null $municipio_id
 * @property int $categoria_id
 * @property string $slug
 * @property string $titulo
 * @property string $resumen
 * @property list<string> $historia
 * @property Urgencia $urgencia
 * @property EstadoIniciativa $estado
 * @property string|null $imagen_path
 * @property Carbon|null $fecha_convite
 * @property Carbon|null $fecha_limite_aportes
 * @property string|null $fecha_convite_texto
 * @property string $lugar_convite
 * @property string|null $lugar_exacto
 * @property string|null $enlace_externo_plataforma
 * @property string|null $enlace_externo_url
 * @property int $asistentes_count
 * @property int $progreso_cache
 * @property bool $destacada
 * @property int $orden_destacada
 * @property int $version
 * @property string|null $persona_responsable
 * @property string|null $quien_respalda
 * @property string|null $telefono_contacto
 */
class Iniciativa extends Model
{
    /** @use HasFactory<IniciativaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Plural correcto en español.
     */
    protected $table = 'iniciativas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'zona_id',
        'municipio_id',
        'categoria_id',
        'slug',
        'titulo',
        'resumen',
        'historia',
        'urgencia',
        'estado',
        'imagen_path',
        'fecha_convite',
        'fecha_limite_aportes',
        'fecha_convite_texto',
        'lugar_convite',
        'lugar_exacto',
        'lat',
        'lng',
        'geo_fuente',
        'geo_precision',
        'mapa_visible',
        'enlace_externo_plataforma',
        'enlace_externo_url',
        'asistentes_count',
        'progreso_cache',
        'destacada',
        'orden_destacada',
        'version',
        'enviada_revision_at',
        'publicada_at',
        'cerrada_at',
        'moderada_por',
        'nota_moderacion',
        'acepta_terminos_at',
        'acepta_descargo_at',
        'persona_responsable',
        'quien_respalda',
        'telefono_contacto',
    ];

    /**
     * Campos sensibles: nunca serializar en listados públicos.
     *
     * @var list<string>
     */
    protected $hidden = [
        'persona_responsable',
        'quien_respalda',
        'telefono_contacto',
        'lugar_exacto',
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
            'categoria_id' => 'integer',
            'historia' => 'array',
            'urgencia' => Urgencia::class,
            'estado' => EstadoIniciativa::class,
            'fecha_convite' => 'date',
            'fecha_limite_aportes' => 'date',
            'asistentes_count' => 'integer',
            'progreso_cache' => 'integer',
            'destacada' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
            'mapa_visible' => 'boolean',
            'orden_destacada' => 'integer',
            'version' => 'integer',
            'enviada_revision_at' => 'datetime',
            'publicada_at' => 'datetime',
            'cerrada_at' => 'datetime',
            'moderada_por' => 'integer',
            'acepta_terminos_at' => 'datetime',
            'acepta_descargo_at' => 'datetime',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function moderador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderada_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IniciativaItem::class)->orderBy('orden');
    }

    public function aportes(): HasMany
    {
        return $this->hasMany(Aporte::class);
    }

    public function moderacionAcciones(): HasMany
    {
        return $this->hasMany(ModeracionAccion::class);
    }

    /**
     * En listados (sin items eager) usa progreso_cache; con items cargados recalcula en vivo.
     */
    public function progresoTotal(): int
    {
        if (! $this->relationLoaded('items') && $this->exists) {
            return (int) $this->progreso_cache;
        }

        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $suma = $items->sum(fn (IniciativaItem $item) => $item->progresoPorcentaje());

        return (int) round($suma / $items->count());
    }
}
