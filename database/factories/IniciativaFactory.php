<?php

namespace Database\Factories;

use App\Enums\EstadoIniciativa;
use App\Enums\Urgencia;
use App\Models\Categoria;
use App\Models\Iniciativa;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Iniciativa>
 */
class IniciativaFactory extends Factory
{
    protected $model = Iniciativa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titulo = fake()->sentence(4);

        return [
            'user_id' => User::factory(),
            'zona_id' => Zona::factory(),
            'categoria_id' => Categoria::factory(),
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numerify('####'),
            'titulo' => Str::limit($titulo, 180, ''),
            'resumen' => fake()->sentence(12),
            'historia' => [fake()->paragraph()],
            'urgencia' => Urgencia::Media,
            'estado' => EstadoIniciativa::Borrador,
            'lugar_convite' => fake()->streetAddress(),
            'mapa_visible' => true,
            'asistentes_count' => 0,
            'progreso_cache' => 0,
            'destacada' => false,
            'orden_destacada' => 0,
            'version' => 1,
            'persona_responsable' => fake()->name(),
            'quien_respalda' => fake()->company(),
            'telefono_contacto' => '+57 300 000 0000',
            'acepta_terminos_at' => now(),
            'acepta_descargo_at' => now(),
        ];
    }

    public function publicada(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoIniciativa::Publicada,
            'publicada_at' => now(),
        ]);
    }
}
