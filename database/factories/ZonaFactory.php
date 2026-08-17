<?php

namespace Database\Factories;

use App\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Zona>
 */
class ZonaFactory extends Factory
{
    protected $model = Zona::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->city();

        return [
            'slug' => Str::slug($nombre).'-'.fake()->unique()->numerify('###'),
            'nombre' => $nombre,
            'municipio' => $nombre,
            'orden' => fake()->numberBetween(1, 100),
            'activo' => true,
        ];
    }
}
