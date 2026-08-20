<?php

namespace Database\Factories;

use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IniciativaAvance>
 */
class IniciativaAvanceFactory extends Factory
{
    protected $model = IniciativaAvance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titulo = fake()->sentence(4);

        return [
            'iniciativa_id' => Iniciativa::factory(),
            'iniciativa_item_id' => null,
            'user_id' => User::factory(),
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numerify('####'),
            'titulo' => Str::limit($titulo, 200, ''),
            'cuerpo' => fake()->paragraph(),
            'porcentaje' => null,
            'enlace_externo' => null,
            'notificar_aportantes' => false,
            'notificado_at' => null,
            'publicado_at' => null,
        ];
    }

    public function publicado(): static
    {
        return $this->state(fn () => [
            'publicado_at' => now(),
        ]);
    }
}
