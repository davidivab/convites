<?php

namespace Database\Seeders;

use App\Enums\AccionModeracion;
use App\Enums\AptitudFisica;
use App\Enums\AreaProfesional;
use App\Enums\EstadoAporte;
use App\Enums\EstadoCentro;
use App\Enums\EstadoIniciativa;
use App\Enums\EstadoProfesional;
use App\Enums\EstadoSolicitudProfesional;
use App\Enums\Genero;
use App\Enums\ModalidadProfesional;
use App\Enums\PreferenciaContacto;
use App\Enums\TipoCentro;
use App\Enums\Urgencia;
use App\Models\Aporte;
use App\Models\AporteItem;
use App\Models\Categoria;
use App\Models\Centro;
use App\Models\Disponibilidad;
use App\Models\Habilidad;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\ModeracionAccion;
use App\Models\Profesional;
use App\Models\ProfesionalSolicitud;
use App\Models\User;
use App\Models\Zona;
use App\Services\IniciativaProgresoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos dummy en BD para probar flujos de punta a punta (no mocks del front).
 *
 * Idempotente: updateOrCreate por email / slug / nombre.
 * Tras seed, el progreso de ítems sale de aportes reales (recalcularTodas).
 *
 * Cuentas (password: password):
 * - admin@convites.test / moderator@convites.test / member@convites.test
 * - aportante1..8@convites.test, creador2@convites.test
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $creador = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $moderator = User::query()->where('email', 'moderator@convites.test')->firstOrFail();

        $aportantes = $this->seedAportantes();
        $creador2 = $this->seedCreadorExtra();

        $this->enrichMemberProfiles($creador, $creador2, $aportantes);

        $this->seedIniciativas($creador, $creador2, $moderator);
        $this->seedCentros();
        $this->seedProfesionales($aportantes);
        $this->seedAportes($aportantes);
        $this->seedSolicitudesContacto($aportantes);

        app(IniciativaProgresoService::class)->recalcularTodas();
    }

    /**
     * @return list<User>
     */
    private function seedAportantes(): array
    {
        $nombres = [
            'Camila Restrepo',
            'Juan Pablo Mejía',
            'Sofía Valencia',
            'Carlos Hoyos',
            'Mariana Giraldo',
            'Esteban Quintero',
            'Valentina Ríos',
            'Diego Salazar',
        ];

        $users = [];

        foreach ($nombres as $i => $nombre) {
            $n = $i + 1;
            $user = User::query()->updateOrCreate(
                ['email' => "aportante{$n}@convites.test"],
                [
                    'name' => $nombre,
                    'password' => 'password',
                    'celular' => '+57 310 400 '.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                    'inicial' => Str::upper(Str::substr($nombre, 0, 1)),
                ],
            );
            $user->forceFill([
                'acepta_terminos_at' => now()->subDays(10),
                'acepta_descargo_at' => now()->subDays(10),
                'email_verified_at' => now()->subDays(10),
            ])->save();
            $user->syncRoles(['member']);
            $users[] = $user;
        }

        return $users;
    }

    private function seedCreadorExtra(): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'creador2@convites.test'],
            [
                'name' => 'JAC El Manzano',
                'password' => 'password',
                'celular' => '+57 312 555 7788',
                'inicial' => 'J',
            ],
        );
        $user->forceFill([
            'acepta_terminos_at' => now()->subDays(20),
            'acepta_descargo_at' => now()->subDays(20),
            'email_verified_at' => now()->subDays(20),
        ])->save();
        $user->syncRoles(['member']);

        return $user;
    }

    /**
     * @param  list<User>  $aportantes
     */
    private function enrichMemberProfiles(User $creador, User $creador2, array $aportantes): void
    {
        $zonas = Zona::query()->orderBy('orden')->get();
        $habilidades = Habilidad::query()->pluck('id')->all();
        $disponibilidades = Disponibilidad::query()->pluck('id')->all();

        $all = array_merge([$creador, $creador2], $aportantes);

        foreach ($all as $i => $user) {
            $zona = $zonas[$i % $zonas->count()];
            $user->forceFill([
                'zona_id' => $zona->id,
                'genero' => $i % 2 === 0 ? Genero::Mujer : Genero::Hombre,
                'edad' => 22 + ($i * 3 % 40),
                'aptitud_fisica' => match ($i % 3) {
                    0 => AptitudFisica::Alta,
                    1 => AptitudFisica::Media,
                    default => AptitudFisica::Baja,
                },
                'notas_salud' => $i === 2 ? 'Evitar carga pesada (espalda)' : null,
            ])->save();

            if ($habilidades !== []) {
                $pick = array_slice($habilidades, $i % max(1, count($habilidades) - 2), 3);
                $user->habilidades()->sync($pick);
            }

            if ($disponibilidades !== []) {
                $pick = array_slice($disponibilidades, 0, 1 + ($i % 2));
                $user->disponibilidades()->sync($pick);
            }
        }
    }

    private function seedIniciativas(User $creador, User $creador2, User $moderator): void
    {
        $demos = [
            [
                'slug' => 'reconstruir-casa-familia-quintero',
                'user' => $creador,
                'titulo' => 'Reconstruir la casa de la familia Quintero',
                'resumen' => 'El deslizamiento se llevó media casa. Entre todos ponemos el techo antes de que vuelvan las lluvias.',
                'historia' => [
                    'Doña Rosa y sus tres nietos viven en la vereda hace más de treinta años. La creciente de noviembre se llevó una de las paredes y todo el techo de la parte de atrás.',
                    'Ya limpiamos el terreno y conseguimos la mano de obra de tres vecinos albañiles. Lo que falta es el material: tejas, cemento y madera para las vigas.',
                    'El convite es un sábado. Traé lo que puedas y quedate a levantar el techo con nosotros. Al mediodía hay sancocho comunitario.',
                ],
                'zona' => 'Dosquebradas',
                'categoria' => 'vivienda',
                'urgencia' => Urgencia::Alta,
                'estado' => EstadoIniciativa::EnCurso,
                'fecha' => '2026-09-06',
                'fecha_texto' => 'Sábado 6 de septiembre, 7:00 a.m.',
                'lugar' => 'Vereda La Pradera, Dosquebradas',
                'enlace' => ['plataforma' => 'Vaki', 'url' => 'https://vaki.co'],
                'items' => [
                    ['Tejas de zinc', 'unid.', 40],
                    ['Bultos de cemento', 'bultos', 30],
                    ['Vigas de madera', 'unid.', 12],
                    ['Comida para el convite', 'porciones', 60],
                ],
                'destacada' => true,
                'coords' => [4.8375, -75.6802],
            ],
            [
                'slug' => 'comedor-comunitario-villa-santana',
                'user' => $creador,
                'titulo' => 'Volver a abrir el comedor comunitario',
                'resumen' => 'El comedor alimenta a 80 niños cada día. El agua dañó la cocina y necesitamos volver a montarla.',
                'historia' => [
                    'El comedor de Villa Santana lleva ocho años dando almuerzo a los niños del barrio. La inundación dañó las estufas, las mesas y buena parte de la loza.',
                    'Queremos reabrir antes de que empiecen las clases. Necesitamos ollas, mesas y una mano para pintar y organizar.',
                ],
                'zona' => 'Pereira — Villa Santana',
                'categoria' => 'comunitario',
                'urgencia' => Urgencia::Alta,
                'estado' => EstadoIniciativa::Publicada,
                'fecha' => '2026-09-14',
                'fecha_texto' => 'Domingo 14 de septiembre, 8:00 a.m.',
                'lugar' => 'Salón comunal, Villa Santana',
                'enlace' => null,
                'items' => [
                    ['Ollas industriales', 'unid.', 6],
                    ['Mesas plásticas', 'unid.', 10],
                    ['Galones de pintura', 'galones', 8],
                    ['Platos y cubiertos', 'juegos', 80],
                ],
                'destacada' => true,
                'coords' => [4.7950, -75.6550],
            ],
            [
                'slug' => 'reparar-escuela-vereda-el-manzano',
                'user' => $creador2,
                'titulo' => 'Reparar la escuela de la vereda El Manzano',
                'resumen' => 'Dos salones quedaron sin techo. Los niños vuelven a clase en tres semanas.',
                'historia' => [
                    'La escuela rural de El Manzano recibe a 45 niños de la vereda. El vendaval arrancó el techo de dos salones y mojó los pupitres.',
                    'Necesitamos tejas, madera y pintura, y manos para armar de nuevo los pupitres el día del convite.',
                ],
                'zona' => 'Santa Rosa de Cabal',
                'categoria' => 'educacion',
                'urgencia' => Urgencia::Media,
                'estado' => EstadoIniciativa::EnCurso,
                'fecha' => '2026-09-20',
                'fecha_texto' => 'Sábado 20 de septiembre, 7:30 a.m.',
                'lugar' => 'Escuela El Manzano, Santa Rosa de Cabal',
                'enlace' => null,
                'items' => [
                    ['Tejas de barro', 'unid.', 60],
                    ['Tablones de madera', 'unid.', 20],
                    ['Galones de pintura', 'galones', 6],
                ],
                'destacada' => true,
                'coords' => [4.8680, -75.6210],
            ],
            [
                'slug' => 'herramientas-para-los-convites',
                'user' => $creador,
                'titulo' => 'Un banco de herramientas para los convites',
                'resumen' => 'Palas, carretillas y herramienta que rota entre las veredas para no comprar de cero cada vez.',
                'historia' => [
                    'Cada convite empieza buscando prestadas las mismas herramientas. Queremos armar un banco comunitario que rote entre las veredas afectadas.',
                ],
                'zona' => 'Marsella',
                'categoria' => 'herramientas',
                'urgencia' => Urgencia::Baja,
                'estado' => EstadoIniciativa::Publicada,
                'fecha' => null,
                'fecha_texto' => 'Por definir con la comunidad',
                'lugar' => 'Casa comunal, Marsella',
                'enlace' => null,
                'items' => [
                    ['Palas', 'unid.', 25],
                    ['Carretillas', 'unid.', 10],
                    ['Picas', 'unid.', 15],
                    ['Guantes de trabajo', 'pares', 50],
                ],
                'destacada' => false,
                'coords' => [4.7365, -75.7390],
            ],
            [
                'slug' => 'reconstruir-puente-peatonal-quebrada',
                'user' => $creador,
                'titulo' => 'Reconstruir el puente peatonal de la quebrada',
                'resumen' => 'El puente que conecta las dos orillas se cayó. Necesitamos guadua, tornillería y manos para armarlo un fin de semana.',
                'historia' => [
                    'El puente peatonal es el acceso diario de varias familias. Tras la creciente quedó inutilizable.',
                ],
                'zona' => 'La Virginia',
                'categoria' => 'comunitario',
                'urgencia' => Urgencia::Alta,
                'estado' => EstadoIniciativa::EnRevision,
                'fecha' => '2026-09-28',
                'fecha_texto' => 'Fin de semana por confirmar',
                'lugar' => 'Sector El Puente, La Virginia',
                'enlace' => null,
                'items' => [
                    ['Guaduas', 'unid.', 30],
                    ['Cajas de tornillería', 'cajas', 4],
                    ['Tablones', 'unid.', 20],
                ],
                'destacada' => false,
                'coords' => [4.8990, -75.8820],
                'privado' => [
                    'persona_responsable' => 'Vecinos del sector El Puente',
                    'quien_respalda' => 'JAC El Puente',
                    'telefono_contacto' => '+57 310 000 1111',
                ],
            ],
            [
                'slug' => 'huerta-comunitaria-borrador',
                'user' => $creador2,
                'titulo' => 'Huerta comunitaria (borrador)',
                'resumen' => 'Borrador para probar edición y envío a revisión desde el panel del creador.',
                'historia' => [
                    'Queremos recuperar la huerta del salón comunal. Aún estamos armando la lista de materiales.',
                ],
                'zona' => 'Pereira — Centro',
                'categoria' => 'alimentacion',
                'urgencia' => Urgencia::Baja,
                'estado' => EstadoIniciativa::Borrador,
                'fecha' => null,
                'fecha_texto' => 'Por definir',
                'lugar' => 'Salón comunal centro',
                'enlace' => null,
                'items' => [
                    ['Semillas de hortalizas', 'paquetes', 20],
                    ['Abono orgánico', 'bultos', 10],
                ],
                'destacada' => false,
                'coords' => [4.8130, -75.6960],
            ],
            [
                'slug' => 'techo-provisional-rechazada',
                'user' => $creador,
                'titulo' => 'Techo provisional (rechazada — demo)',
                'resumen' => 'Ejemplo rechazado por moderación para probar reenvío / corrección.',
                'historia' => [
                    'Faltaba información de contacto y respaldo comunitario.',
                ],
                'zona' => 'Dosquebradas',
                'categoria' => 'vivienda',
                'urgencia' => Urgencia::Media,
                'estado' => EstadoIniciativa::Rechazada,
                'fecha' => null,
                'fecha_texto' => 'Sin fecha',
                'lugar' => 'Por confirmar',
                'enlace' => null,
                'items' => [
                    ['Lonas', 'unid.', 15],
                ],
                'destacada' => false,
                'coords' => null,
                'privado' => [
                    'persona_responsable' => 'Sin datos claros',
                    'quien_respalda' => 'N/A',
                    'telefono_contacto' => '+57 300 000 0000',
                ],
            ],
            [
                'slug' => 'limpiar-quebrada-cerrada',
                'user' => $creador,
                'titulo' => 'Limpieza de quebrada (cerrada)',
                'resumen' => 'Convite ya realizado — sirve para historial del panel creador.',
                'historia' => [
                    'Se recolectaron escombros y se reforzó el talud con la comunidad.',
                ],
                'zona' => 'Dosquebradas',
                'categoria' => 'comunitario',
                'urgencia' => Urgencia::Media,
                'estado' => EstadoIniciativa::Cerrada,
                'fecha' => '2026-07-12',
                'fecha_texto' => 'Domingo 12 de julio',
                'lugar' => 'Quebrada La Dulcera',
                'enlace' => null,
                'items' => [
                    ['Bolsas de escombros', 'bolsas', 100],
                    ['Guantes', 'pares', 40],
                ],
                'destacada' => false,
                'coords' => [4.8250, -75.6900],
            ],
        ];

        foreach ($demos as $i => $demo) {
            $zona = Zona::query()->where('nombre', $demo['zona'])->firstOrFail();
            $categoria = Categoria::query()->where('slug', $demo['categoria'])->firstOrFail();
            /** @var User $owner */
            $owner = $demo['user'];

            $estado = $demo['estado'];
            $publicadaAt = in_array($estado, [EstadoIniciativa::Publicada, EstadoIniciativa::EnCurso, EstadoIniciativa::Cerrada], true)
                ? now()->subDays(5)
                : null;

            $iniciativa = Iniciativa::query()->updateOrCreate(
                ['slug' => $demo['slug']],
                [
                    'user_id' => $owner->id,
                    'zona_id' => $zona->id,
                    'categoria_id' => $categoria->id,
                    'titulo' => $demo['titulo'],
                    'resumen' => $demo['resumen'],
                    'historia' => $demo['historia'],
                    'urgencia' => $demo['urgencia'],
                    'estado' => $estado,
                    'fecha_convite' => $demo['fecha'],
                    'fecha_limite_aportes' => $demo['fecha'],
                    'fecha_convite_texto' => $demo['fecha_texto'],
                    'lugar_convite' => $demo['lugar'],
                    'lugar_exacto' => $demo['lugar'],
                    'lat' => $demo['coords'][0] ?? null,
                    'lng' => $demo['coords'][1] ?? null,
                    'geo_fuente' => ($demo['coords'] ?? null) ? 'manual' : null,
                    'geo_precision' => ($demo['coords'] ?? null) ? 'aproximado' : 'punto',
                    'mapa_visible' => (bool) ($demo['coords'] ?? false),
                    'enlace_externo_plataforma' => $demo['enlace']['plataforma'] ?? null,
                    'enlace_externo_url' => $demo['enlace']['url'] ?? null,
                    'asistentes_count' => 0,
                    'progreso_cache' => 0,
                    'destacada' => $demo['destacada'],
                    'orden_destacada' => $demo['destacada'] ? $i + 1 : 0,
                    'publicada_at' => $publicadaAt,
                    'enviada_revision_at' => $estado === EstadoIniciativa::Borrador
                        ? null
                        : now()->subDays(6),
                    'acepta_terminos_at' => now(),
                    'acepta_descargo_at' => now(),
                    'persona_responsable' => $demo['privado']['persona_responsable'] ?? $owner->name,
                    'quien_respalda' => $demo['privado']['quien_respalda'] ?? 'Comunidad local',
                    'telefono_contacto' => $demo['privado']['telefono_contacto'] ?? $owner->celular,
                ],
            );

            foreach ($demo['items'] as $orden => [$nombre, $unidad, $meta]) {
                IniciativaItem::query()->updateOrCreate(
                    [
                        'iniciativa_id' => $iniciativa->id,
                        'nombre' => $nombre,
                    ],
                    [
                        'unidad' => $unidad,
                        'cantidad_meta' => $meta,
                        'cantidad_aportada' => 0,
                        'orden' => $orden + 1,
                    ],
                );
            }

            $this->seedBitacoraModeracion($iniciativa, $owner, $moderator);
        }
    }

    private function seedBitacoraModeracion(Iniciativa $iniciativa, User $owner, User $moderator): void
    {
        if ($iniciativa->estado === EstadoIniciativa::Borrador) {
            return;
        }

        ModeracionAccion::query()->updateOrCreate(
            [
                'iniciativa_id' => $iniciativa->id,
                'accion' => AccionModeracion::EnviarRevision,
            ],
            [
                'user_id' => $owner->id,
                'estado_anterior' => EstadoIniciativa::Borrador,
                'estado_nuevo' => EstadoIniciativa::EnRevision,
                'nota' => 'Enviado a revisión (demo)',
            ],
        );

        if ($iniciativa->estado === EstadoIniciativa::Rechazada) {
            ModeracionAccion::query()->updateOrCreate(
                [
                    'iniciativa_id' => $iniciativa->id,
                    'accion' => AccionModeracion::Rechazar,
                ],
                [
                    'user_id' => $moderator->id,
                    'estado_anterior' => EstadoIniciativa::EnRevision,
                    'estado_nuevo' => EstadoIniciativa::Rechazada,
                    'nota' => 'Falta teléfono verificable y respaldo de JAC.',
                ],
            );

            return;
        }

        if (in_array($iniciativa->estado, [
            EstadoIniciativa::Publicada,
            EstadoIniciativa::EnCurso,
            EstadoIniciativa::Cerrada,
        ], true)) {
            ModeracionAccion::query()->updateOrCreate(
                [
                    'iniciativa_id' => $iniciativa->id,
                    'accion' => AccionModeracion::Aprobar,
                ],
                [
                    'user_id' => $moderator->id,
                    'estado_anterior' => EstadoIniciativa::EnRevision,
                    'estado_nuevo' => EstadoIniciativa::Publicada,
                    'nota' => 'Aprobada (demo)',
                ],
            );
        }

        if ($iniciativa->estado === EstadoIniciativa::Cerrada) {
            ModeracionAccion::query()->updateOrCreate(
                [
                    'iniciativa_id' => $iniciativa->id,
                    'accion' => AccionModeracion::Cerrar,
                ],
                [
                    'user_id' => $moderator->id,
                    'estado_anterior' => EstadoIniciativa::EnCurso,
                    'estado_nuevo' => EstadoIniciativa::Cerrada,
                    'nota' => 'Convite realizado (demo)',
                ],
            );
        }
    }

    private function seedCentros(): void
    {
        $centros = [
            [
                'tipo' => TipoCentro::Acopio,
                'nombre' => 'Acopio Coliseo Dosquebradas',
                'zona' => 'Dosquebradas',
                'direccion' => 'Cra. 16 con Cl. 38, Coliseo Municipal',
                'telefono' => '+57 606 322 1010',
                'horario' => 'Lun a dom, 7:00 a.m. – 7:00 p.m.',
                'estado' => EstadoCentro::Abierto,
                'descripcion' => 'Punto principal de recepción y clasificación de donaciones en especie para las familias afectadas.',
                'necesita' => ['Agua embotellada', 'Mercados no perecederos', 'Cobijas y colchonetas', 'Kits de aseo', 'Pañales y leche de fórmula'],
                'no_recibe' => ['Ropa usada en mal estado', 'Medicamentos vencidos', 'Comida preparada'],
            ],
            [
                'tipo' => TipoCentro::Albergue,
                'nombre' => 'Albergue Institución Educativa La Badea',
                'zona' => 'Dosquebradas',
                'direccion' => 'Av. Simón Bolívar #45-20',
                'telefono' => '+57 606 333 4455',
                'horario' => 'Recepción 24 horas',
                'estado' => EstadoCentro::Abierto,
                'descripcion' => 'Alojamiento temporal con alimentación y acompañamiento para familias evacuadas.',
                'capacidad_total' => 180,
                'capacidad_ocupada' => 132,
            ],
            [
                'tipo' => TipoCentro::Bomberos,
                'nombre' => 'Bomberos Dosquebradas',
                'zona' => 'Dosquebradas',
                'direccion' => 'Cra. 19 #40-30',
                'telefono' => '119',
                'horario' => 'Servicio permanente',
                'estado' => EstadoCentro::VeinticuatroHoras,
                'descripcion' => 'Atención de emergencias, rescate y evaluación de estructuras. Línea directa 119.',
                'emergencia' => true,
            ],
            [
                'tipo' => TipoCentro::Hospital,
                'nombre' => 'Hospital Santa Mónica',
                'zona' => 'Dosquebradas',
                'direccion' => 'Cra. 16 #21-45',
                'telefono' => '+57 606 330 9090',
                'horario' => 'Urgencias 24 horas',
                'estado' => EstadoCentro::VeinticuatroHoras,
                'descripcion' => 'Urgencias, hospitalización y atención prioritaria a personas afectadas por la emergencia.',
                'emergencia' => true,
            ],
            [
                'tipo' => TipoCentro::Acopio,
                'nombre' => 'Acopio Parque Olaya — Pereira',
                'zona' => 'Pereira — Centro',
                'direccion' => 'Cra. 7 con Cl. 19, costado norte',
                'telefono' => '+57 606 335 1212',
                'horario' => 'Lun a sáb, 8:00 a.m. – 6:00 p.m.',
                'estado' => EstadoCentro::Abierto,
                'descripcion' => 'Punto de recepción para donaciones en especie orientadas a iniciativas de Pereira.',
                'necesita' => ['Herramientas', 'Material de construcción', 'Mercados'],
            ],
        ];

        foreach ($centros as $i => $c) {
            $zona = Zona::query()->where('nombre', $c['zona'])->firstOrFail();

            Centro::query()->updateOrCreate(
                ['nombre' => $c['nombre']],
                [
                    'tipo' => $c['tipo'],
                    'zona_id' => $zona->id,
                    'direccion' => $c['direccion'],
                    'telefono' => $c['telefono'],
                    'horario' => $c['horario'],
                    'estado' => $c['estado'],
                    'descripcion' => $c['descripcion'],
                    'necesita' => $c['necesita'] ?? null,
                    'no_recibe' => $c['no_recibe'] ?? null,
                    'capacidad_total' => $c['capacidad_total'] ?? null,
                    'capacidad_ocupada' => $c['capacidad_ocupada'] ?? null,
                    'emergencia' => $c['emergencia'] ?? false,
                    'activo' => true,
                    'orden' => $i + 1,
                ],
            );
        }
    }

    /**
     * @param  list<User>  $aportantes
     */
    private function seedProfesionales(array $aportantes): void
    {
        $pros = [
            [
                'area' => AreaProfesional::Psicologia,
                'nombre' => 'Laura Cardona',
                'titulo' => 'Psicóloga clínica',
                'zona' => 'Dosquebradas',
                'email' => 'laura.cardona@convites.test',
                'modalidad' => ModalidadProfesional::Ambas,
                'disponibilidad' => 'Fines de semana y noches',
                'descripcion' => 'Acompañamiento en duelo, ansiedad y estrés post-emergencia para familias y niños.',
                'estado' => EstadoProfesional::Aprobado,
                'user' => $aportantes[0] ?? null,
            ],
            [
                'area' => AreaProfesional::Legal,
                'nombre' => 'Andrés Villegas',
                'titulo' => 'Abogado — derecho de propiedad',
                'zona' => 'Pereira — Centro',
                'email' => 'andres.villegas@convites.test',
                'modalidad' => ModalidadProfesional::Ambas,
                'disponibilidad' => 'Martes y jueves',
                'descripcion' => 'Orientación en escrituras, predios afectados, seguros y reclamaciones ante entidades.',
                'estado' => EstadoProfesional::Aprobado,
                'user' => null,
            ],
            [
                'area' => AreaProfesional::Arquitectura,
                'nombre' => 'Diana Ospina',
                'titulo' => 'Arquitecta',
                'zona' => 'Dosquebradas',
                'email' => 'diana.ospina@convites.test',
                'modalidad' => ModalidadProfesional::Presencial,
                'disponibilidad' => 'Sábados en jornada',
                'descripcion' => 'Diagnóstico de daños y recomendaciones para reparar viviendas de forma segura.',
                'estado' => EstadoProfesional::Aprobado,
                'user' => null,
            ],
            [
                'area' => AreaProfesional::Salud,
                'nombre' => 'Julián Castaño',
                'titulo' => 'Médico general',
                'zona' => 'Santa Rosa de Cabal',
                'email' => 'julian.castano@convites.test',
                'modalidad' => ModalidadProfesional::Presencial,
                'disponibilidad' => 'Mañanas entre semana',
                'descripcion' => 'Atención primaria en albergues y visitas a veredas (demo pendiente de moderación).',
                'estado' => EstadoProfesional::Pendiente,
                'user' => $aportantes[1] ?? null,
            ],
            [
                'area' => AreaProfesional::Nutricion,
                'nombre' => 'Natalia Duque',
                'titulo' => 'Nutricionista',
                'zona' => 'Marsella',
                'email' => 'natalia.duque@convites.test',
                'modalidad' => ModalidadProfesional::Ambas,
                'disponibilidad' => 'Tardes',
                'descripcion' => 'Planes alimentarios en comedores — demo con cambios solicitados.',
                'estado' => EstadoProfesional::CambiosSolicitados,
                'user' => null,
            ],
        ];

        foreach ($pros as $pro) {
            $zona = Zona::query()->where('nombre', $pro['zona'])->firstOrFail();
            $estado = $pro['estado'];

            Profesional::query()->updateOrCreate(
                ['email' => $pro['email']],
                [
                    'user_id' => $pro['user']?->id,
                    'zona_id' => $zona->id,
                    'area' => $pro['area'],
                    'nombre' => $pro['nombre'],
                    'titulo' => $pro['titulo'],
                    'celular' => '+57 310 555 0000',
                    'modalidad' => $pro['modalidad'],
                    'disponibilidad' => $pro['disponibilidad'],
                    'descripcion' => $pro['descripcion'],
                    'inicial' => Str::upper(Str::substr($pro['nombre'], 0, 1)),
                    'estado' => $estado,
                    'enviado_at' => now()->subDays(7),
                    'aprobado_at' => $estado === EstadoProfesional::Aprobado ? now()->subDays(5) : null,
                    'acepta_terminos_at' => now()->subDays(7),
                ],
            );
        }
    }

    /**
     * Aportes reales → alimentan panel aportante + progreso de iniciativas.
     *
     * @param  list<User>  $aportantes
     */
    private function seedAportes(array $aportantes): void
    {
        $specs = [
            'reconstruir-casa-familia-quintero' => [
                ['user' => 0, 'asiste' => true, 'estado' => EstadoAporte::Confirmado, 'nota' => 'Llevo tejas el sábado', 'items' => [['Tejas de zinc', 10], ['Bultos de cemento', 5]]],
                ['user' => 1, 'asiste' => true, 'estado' => EstadoAporte::Confirmado, 'nota' => null, 'items' => [['Tejas de zinc', 8], ['Vigas de madera', 4]]],
                ['user' => 2, 'asiste' => true, 'estado' => EstadoAporte::Cumplido, 'nota' => 'Ya entregué cemento', 'items' => [['Bultos de cemento', 8]]],
                ['user' => 3, 'asiste' => true, 'estado' => EstadoAporte::Confirmado, 'nota' => null, 'items' => [['Comida para el convite', 20], ['Vigas de madera', 3]]],
                ['user' => 4, 'asiste' => false, 'estado' => EstadoAporte::Confirmado, 'nota' => 'Solo material', 'items' => [['Tejas de zinc', 6], ['Comida para el convite', 15]]],
                ['user' => 5, 'asiste' => true, 'estado' => EstadoAporte::Cancelado, 'nota' => 'No puedo ir', 'items' => [['Bultos de cemento', 4]]],
            ],
            'comedor-comunitario-villa-santana' => [
                ['user' => 0, 'asiste' => true, 'estado' => EstadoAporte::Confirmado, 'nota' => null, 'items' => [['Ollas industriales', 1], ['Mesas plásticas', 2]]],
                ['user' => 2, 'asiste' => true, 'estado' => EstadoAporte::Confirmado, 'nota' => 'Puedo pintar', 'items' => [['Galones de pintura', 3]]],
                ['user' => 6, 'asiste' => false, 'estado' => EstadoAporte::Confirmado, 'nota' => null, 'items' => [['Platos y cubiertos', 20], ['Mesas plásticas', 2]]],
            ],
            'reparar-escuela-vereda-el-manzano' => [
                ['user' => 1, 'asiste' => true, 'estado' => EstadoAporte::Confirmado, 'nota' => null, 'items' => [['Tejas de barro', 20], ['Tablones de madera', 6]]],
                ['user' => 3, 'asiste' => true, 'estado' => EstadoAporte::Cumplido, 'nota' => 'Pintura entregada', 'items' => [['Galones de pintura', 4], ['Tejas de barro', 15]]],
                ['user' => 7, 'asiste' => true, 'estado' => EstadoAporte::Confirmado, 'nota' => null, 'items' => [['Tablones de madera', 5]]],
            ],
            'herramientas-para-los-convites' => [
                ['user' => 4, 'asiste' => false, 'estado' => EstadoAporte::Confirmado, 'nota' => null, 'items' => [['Palas', 5], ['Guantes de trabajo', 12]]],
                ['user' => 5, 'asiste' => true, 'estado' => EstadoAporte::Confirmado, 'nota' => null, 'items' => [['Carretillas', 2], ['Picas', 3]]],
            ],
            'limpiar-quebrada-cerrada' => [
                ['user' => 0, 'asiste' => true, 'estado' => EstadoAporte::Cumplido, 'nota' => 'Convite hecho', 'items' => [['Bolsas de escombros', 40], ['Guantes', 10]]],
                ['user' => 1, 'asiste' => true, 'estado' => EstadoAporte::Cumplido, 'nota' => null, 'items' => [['Bolsas de escombros', 30], ['Guantes', 10]]],
            ],
        ];

        foreach ($specs as $slug => $filas) {
            $iniciativa = Iniciativa::query()->where('slug', $slug)->with('items')->first();
            if (! $iniciativa) {
                continue;
            }

            $itemsByName = $iniciativa->items->keyBy('nombre');

            foreach ($filas as $fila) {
                $user = $aportantes[$fila['user']] ?? null;
                if (! $user) {
                    continue;
                }

                $aporte = Aporte::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'iniciativa_id' => $iniciativa->id,
                    ],
                    [
                        'estado' => $fila['estado'],
                        'asiste_al_convite' => $fila['asiste'],
                        'nota' => $fila['nota'],
                        'client_request_id' => (string) Str::uuid(),
                        'confirmado_at' => now()->subDays(2),
                        'cancelado_at' => $fila['estado'] === EstadoAporte::Cancelado ? now()->subDay() : null,
                        'cumplido_at' => $fila['estado'] === EstadoAporte::Cumplido ? now()->subHours(12) : null,
                    ],
                );

                $aporte->items()->delete();

                foreach ($fila['items'] as [$nombreItem, $cantidad]) {
                    $item = $itemsByName->get($nombreItem);
                    if (! $item) {
                        continue;
                    }

                    AporteItem::query()->create([
                        'aporte_id' => $aporte->id,
                        'iniciativa_item_id' => $item->id,
                        'cantidad' => $cantidad,
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<User>  $aportantes
     */
    private function seedSolicitudesContacto(array $aportantes): void
    {
        $laura = Profesional::query()->where('email', 'laura.cardona@convites.test')->first();
        if (! $laura) {
            return;
        }

        $zona = Zona::query()->where('nombre', 'Dosquebradas')->first();
        $user = $aportantes[6] ?? null;

        ProfesionalSolicitud::query()->updateOrCreate(
            [
                'profesional_id' => $laura->id,
                'email' => 'valentina.rios@convites.test',
            ],
            [
                'user_id' => $user?->id,
                'nombre' => $user?->name ?? 'Valentina Ríos',
                'celular' => $user?->celular ?? '+57 310 400 0007',
                'zona_id' => $zona?->id,
                'preferencia_contacto' => PreferenciaContacto::Whatsapp,
                'mensaje' => 'Hola, somos una familia en La Pradera. ¿Pueden acompañarnos el sábado del convite?',
                'estado' => EstadoSolicitudProfesional::Pendiente,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'DemoDataSeeder',
            ],
        );

        ProfesionalSolicitud::query()->updateOrCreate(
            [
                'profesional_id' => $laura->id,
                'email' => 'vecino.demo@convites.test',
            ],
            [
                'user_id' => null,
                'nombre' => 'Vecino anónimo (demo)',
                'celular' => '+57 300 111 2222',
                'zona_id' => $zona?->id,
                'preferencia_contacto' => PreferenciaContacto::Llamada,
                'mensaje' => 'Necesito orientación para mi hijo (ansiedad post-emergencia).',
                'estado' => EstadoSolicitudProfesional::Notificada,
                'notificada_at' => now()->subHours(6),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'DemoDataSeeder',
            ],
        );
    }
}
