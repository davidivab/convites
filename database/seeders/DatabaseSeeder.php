<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder principal del entorno local / demo.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Nunca crear/actualizar usuarios demo con password fijo fuera de local/testing.
        if (app()->environment('production', 'prod', 'staging')) {
            $this->command?->warn(
                'DatabaseSeeder: entorno '.app()->environment().' — solo catálogos/roles; sin users demo ni DemoDataSeeder.',
            );
            $this->call(CatalogosSeeder::class);
            $this->call(ColombiaGeoSeeder::class);
            $this->call(RolesAndPermissionsSeeder::class);
            $this->call(LegalAndNotificationsSeeder::class);

            return;
        }

        // 1) Catálogos (zonas legacy, categorías, …) + geo Colombia
        $this->call(CatalogosSeeder::class);
        $this->call(ColombiaGeoSeeder::class);

        // 2) Roles y permisos Spatie
        $this->call(RolesAndPermissionsSeeder::class);

        // 3) Usuarios base de trabajo (solo local/testing)
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@convites.test'],
            [
                'name' => 'Admin Convites',
                'password' => 'password',
                'celular' => '+57 300 000 0001',
                'inicial' => 'A',
            ],
        );
        $admin->forceFill([
            'acepta_terminos_at' => now(),
            'acepta_descargo_at' => now(),
        ])->save();
        $admin->syncRoles(['admin']);

        $moderator = User::query()->updateOrCreate(
            ['email' => 'moderator@convites.test'],
            [
                'name' => 'Moderador Convites',
                'password' => 'password',
                'celular' => '+57 300 000 0002',
                'inicial' => 'M',
            ],
        );
        $moderator->forceFill([
            'acepta_terminos_at' => now(),
            'acepta_descargo_at' => now(),
        ])->save();
        $moderator->syncRoles(['moderator']);

        // Asigna municipios activos de Risaralda al moderador demo (P20).
        $risaraldaMunicipios = \App\Models\Municipio::query()
            ->where('activo', true)
            ->whereHas('departamento', fn ($q) => $q->where('nombre', 'Risaralda'))
            ->pluck('id');
        if ($risaraldaMunicipios->isNotEmpty()) {
            $moderator->municipiosAsignados()->sync($risaraldaMunicipios);
        }

        $voluntario = User::query()->updateOrCreate(
            ['email' => 'voluntario@convites.test'],
            [
                'name' => 'Voluntario Convites',
                'password' => 'password',
                'celular' => '+57 300 000 0004',
                'inicial' => 'V',
            ],
        );
        $voluntario->forceFill([
            'acepta_terminos_at' => now(),
            'acepta_descargo_at' => now(),
        ])->save();
        $voluntario->syncRoles(['voluntario']);
        if ($risaraldaMunicipios->isNotEmpty()) {
            $voluntario->municipiosAsignados()->sync($risaraldaMunicipios->take(3));
        }

        $member = User::query()->updateOrCreate(
            ['email' => 'member@convites.test'],
            [
                'name' => 'Member Convites',
                'password' => 'password',
                'celular' => '+57 300 000 0003',
                'inicial' => 'V',
            ],
        );
        $member->forceFill([
            'acepta_terminos_at' => now(),
            'acepta_descargo_at' => now(),
        ])->save();
        $member->syncRoles(['member']);

        // 4) Legales vigentes + preferencias de notificación
        $this->call(LegalAndNotificationsSeeder::class);

        // 5) Datos demo del front v0 (iniciativas, centros, profesionales)
        $this->call(DemoDataSeeder::class);

        // 6) Puntos oficiales de censo de afectaciones (Alcaldía de Pereira)
        $this->call(CensoAfectacionesSeeder::class);
    }
}
