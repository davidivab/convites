<?php

namespace App\Models;

use Database\Factories\IniciativaAvanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Avance de convite (P54) — reporte de progreso, general o por ítem.
 *
 * `porcentaje` es puramente narrativo: documenta lo reportado en el avance,
 * NUNCA muta `iniciativa_items.cantidad_aportada` ni `iniciativas.progreso_cache`.
 * El piso monotónico para `tipo=item` se calcula en vivo vía `floorPublicado()`
 * (D-C) — sin columna cacheada.
 *
 * `tipo` es derivado, no una columna: `iniciativa_item_id === null` ⇒ 'general'.
 *
 * @property int $id
 * @property int $iniciativa_id
 * @property int|null $iniciativa_item_id
 * @property int $user_id
 * @property string $slug
 * @property string $titulo
 * @property string|null $cuerpo
 * @property int|null $porcentaje
 * @property string|null $enlace_externo
 * @property bool $notificar_aportantes
 * @property Carbon|null $notificado_at
 * @property Carbon|null $publicado_at
 * @property-read Iniciativa $iniciativa
 * @property-read IniciativaItem|null $item
 * @property-read User $autor
 * @property-read Collection<int, IniciativaAvanceMedia> $media
 */
class IniciativaAvance extends Model
{
    /** @use HasFactory<IniciativaAvanceFactory> */
    use HasFactory;

    protected $table = 'iniciativa_avances';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'iniciativa_id',
        'iniciativa_item_id',
        'user_id',
        'slug',
        'titulo',
        'cuerpo',
        'porcentaje',
        'enlace_externo',
        'notificar_aportantes',
        'notificado_at',
        'publicado_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciativa_id' => 'integer',
            'iniciativa_item_id' => 'integer',
            'user_id' => 'integer',
            'porcentaje' => 'integer',
            'notificar_aportantes' => 'boolean',
            'notificado_at' => 'datetime',
            'publicado_at' => 'datetime',
        ];
    }

    public function iniciativa(): BelongsTo
    {
        return $this->belongsTo(Iniciativa::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(IniciativaItem::class, 'iniciativa_item_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(IniciativaAvanceMedia::class)->orderBy('orden');
    }

    public function scopePublicados(Builder $query): void
    {
        $query->whereNotNull('publicado_at');
    }

    public function esGeneral(): bool
    {
        return $this->iniciativa_item_id === null;
    }

    /**
     * Piso monotónico: máximo `porcentaje` entre los avances PUBLICADOS de
     * ese ítem, excluyendo borradores y (opcionalmente) un id propio —
     * usado por la validación de create/update para excluirse a sí mismo.
     */
    public static function floorPublicado(int $iniciativaId, int $itemId, ?int $exceptId = null): int
    {
        return (int) static::query()
            ->where('iniciativa_id', $iniciativaId)
            ->where('iniciativa_item_id', $itemId)
            ->whereNotNull('publicado_at')
            ->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->max('porcentaje');
    }
}
