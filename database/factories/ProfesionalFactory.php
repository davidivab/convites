<?php

namespace Database\Factories;

use App\Enums\AreaProfesional;
use App\Enums\EstadoProfesional;
use App\Enums\ModalidadProfesional;
use App\Models\Profesional;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Profesional>
 */
class ProfesionalFactory extends Factory
{
    protected $model = Profesional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->name();

        return [
            'user_id' => User::factory(),
            'zona_id' => Zona::factory(),
            'area' => AreaProfesional::Legal,
            'nombre' => $nombre,
            'titulo' => fake()->jobTitle(),
            'email' => fake()->unique()->safeEmail(),
            'celular' => '+57 300 111 2233',
            'modalidad' => ModalidadProfesional::Ambas,
            'disponibilidad' => 'Fines de semana',
            'descripcion' => fake()->paragraph(),
            'inicial' => Str::upper(Str::substr($nombre, 0, 1)),
            'estado' => EstadoProfesional::Pendiente,
            'enviado_at' => now(),
            'acepta_terminos_at' => now(),
        ];
    }

    public function aprobado(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoProfesional::Aprobado,
            'aprobado_at' => now(),
        ]);
    }
}
