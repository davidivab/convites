<?php

namespace App\Support;

use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use Illuminate\Support\Str;

/**
 * Generador de slugs únicos reutilizable por iniciativas y (más adelante) avances.
 *
 * Comportamiento idéntico al histórico `IniciativaController::uniqueSlug()`:
 * título truncado a 140 caracteres antes de sluggificar, sufijos `-1`, `-2`, ...
 * en colisión, y un slug de respaldo cuando el título produce un slug vacío.
 */
final class UniqueSlug
{
    /**
     * @param  callable(string): bool  $taken  Debe devolver true si el slug ya está en uso en el scope relevante.
     */
    public static function make(string $titulo, callable $taken, string $fallback): string
    {
        $base = Str::slug(Str::limit($titulo, 140, ''));
        $base = $base !== '' ? $base : $fallback;
        $slug = $base;
        $i = 1;

        while ($taken($slug)) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public static function forIniciativa(string $titulo): string
    {
        return self::make(
            $titulo,
            fn (string $slug): bool => Iniciativa::withTrashed()->where('slug', $slug)->exists(),
            'iniciativa',
        );
    }

    /**
     * Unicidad scoped a `(iniciativa_id, slug)` — distinta del scope global
     * de `forIniciativa()`. Incluye soft-deleted: el unique de BD sigue
     * ocupando el slug tras un borrado lógico.
     */
    public static function forAvance(int $iniciativaId, string $titulo): string
    {
        return self::make(
            $titulo,
            fn (string $slug): bool => IniciativaAvance::withTrashed()
                ->where('iniciativa_id', $iniciativaId)
                ->where('slug', $slug)
                ->exists(),
            'avance',
        );
    }
}
