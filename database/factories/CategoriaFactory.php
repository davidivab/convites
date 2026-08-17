<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($nombre).'-'.fake()->unique()->numerify('###'),
            'nombre' => Str::title($nombre),
            'descripcion' => fake()->sentence(),
            'orden' => fake()->numberBetween(1, 100),
            'activo' => true,
        ];
    }
}
