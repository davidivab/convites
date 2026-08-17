<?php

namespace Database\Seeders;

use App\Enums\EstadoCentro;
use App\Enums\TipoCentro;
use App\Models\Centro;
use App\Models\Municipio;
use App\Models\Zona;
use Illuminate\Database\Seeder;

/**
 * P45 — puntos oficiales de censo de afectaciones (Alcaldía de Pereira).
 *
 * Datos del aviso ciudadano: no alterar nombres ni direcciones.
 */
class CensoAfectacionesSeeder extends Seeder
{
    private const URL_PORTAL = 'https://sospereira.com/';

    private const HORARIO = '8:00 a.m. a 4:00 p.m. (jornada continua)';

    public function run(): void
    {
        $municipio = Municipio::query()->where('nombre', 'Pereira')->firstOrFail();
        $zona = Zona::query()->where('nombre', 'Pereira — Centro')->firstOrFail();

        $descripcionPresencial = 'Punto de atención presencial del censo oficial de evaluación de daños '
            .'en inmuebles (Alcaldía de Pereira). También puedes reportar en línea en '
            .self::URL_PORTAL;

        $portalNombre = 'Portal SOS Pereira — censo de afectaciones';

        Centro::query()->updateOrCreate(
            ['nombre' => $portalNombre],
            [
                'tipo' => TipoCentro::Censo,
                'zona_id' => $zona->id,
                'municipio_id' => $municipio->id,
                'direccion' => 'Reporte en línea',
                'url_externa' => self::URL_PORTAL,
                'telefono' => null,
                'horario' => self::HORARIO,
                'estado' => EstadoCentro::Abierto,
                'descripcion' => 'Portal oficial de la Alcaldía de Pereira para reportar afectaciones '
                    .'en inmuebles. Existe además un censo psicosocial en paralelo (acompañamiento a familias). '
                    .'Si no tienes conectividad, acude a un punto presencial listado en este directorio.',
                'emergencia' => false,
                'activo' => true,
                'orden' => 0,
            ],
        );

        $puntos = [
            ['nombre' => 'Comuna Centro — Parque El Lago', 'direccion' => 'Parque El Lago'],
            ['nombre' => 'Comuna El Rocío — Patios de Tránsito', 'direccion' => 'Patios de Tránsito'],
            ['nombre' => 'Comuna El Poblado — Cancha techada de Villa del Prado', 'direccion' => 'Cancha techada de Villa del Prado'],
            [
                'nombre' => 'Comuna Boston — Calle 23 Avenida Belalcázar Esquina, caseta comunal barrio centenario',
                'direccion' => 'Calle 23 Avenida Belalcázar Esquina, caseta comunal barrio centenario',
            ],
            ['nombre' => 'Comuna Consota — Manzana E casa 20, Villa Cecilia', 'direccion' => 'Manzana E casa 20, Villa Cecilia'],
            ['nombre' => 'Comuna Cuba — Calle 72 # 23B 20, Salón Comunal Cuba', 'direccion' => 'Calle 72 # 23B 20, Salón Comunal Cuba'],
            [
                'nombre' => 'Comuna El Oso — Salón Comunal de Guadalupe',
                'direccion' => 'Salón Comunal de Guadalupe (Calle 72 Cra 36 Esquina)',
            ],
            [
                'nombre' => 'Comuna El Oso — Salón Comunal Pardo Leal',
                'direccion' => 'Salón Comunal Pardo Leal (Manzana 2 casa No 15)',
            ],
            [
                'nombre' => 'Comuna Villavicencio — Calle 5 #10-49, Caseta Comunal Barrio Berlín',
                'direccion' => 'Calle 5 #10-49, Caseta Comunal Barrio Berlín',
            ],
            ['nombre' => 'Comuna Villa Santana — Parroquia Católica San Vicente', 'direccion' => 'Parroquia Católica San Vicente'],
            ['nombre' => 'Comuna El Oriente — Caseta Comunal Kennedy', 'direccion' => 'Caseta Comunal Kennedy'],
            ['nombre' => 'Comuna del Río — Caseta Comuna El Progreso', 'direccion' => 'Caseta Comuna El Progreso'],
            ['nombre' => 'Comuna San Joaquín — CAI de San Joaquín', 'direccion' => 'CAI de San Joaquín'],
            [
                'nombre' => 'Comuna Ferrocarril — Caseta Comunal del barrio Nacederos',
                'direccion' => 'Caseta Comunal del barrio Nacederos',
            ],
            [
                'nombre' => 'Corregimiento de Tribunas — Empresa de Servicios Públicos',
                'direccion' => 'Empresa de Servicios Públicos',
            ],
            [
                'nombre' => 'Corregimiento de La Bella — Centro Poblado, Corregiduría',
                'direccion' => 'Centro Poblado, Corregiduría',
            ],
            [
                'nombre' => 'Corregimiento de Arabia — Corregiduría del Arabia, esquina parque principal',
                'direccion' => 'Corregiduría del Arabia, esquina parque principal',
            ],
            [
                'nombre' => 'Combia — La Convención (Unidad Deportiva)',
                'direccion' => 'La Convención (Unidad Deportiva)',
            ],
            [
                'nombre' => 'Combia — Betanía y San Vicente (Subestación de Policía)',
                'direccion' => 'Betanía y San Vicente (Subestación de Policía)',
            ],
            [
                'nombre' => 'Combia — Vereda Pital de Combia (Caseta Comunal)',
                'direccion' => 'Vereda Pital de Combia (Caseta Comunal)',
            ],
            ['nombre' => 'Combia Baja — Mall de Combia', 'direccion' => 'Mall de Combia'],
            ['nombre' => 'Corregimiento de Altagracia — Caseta Comunal', 'direccion' => 'Caseta Comunal'],
            ['nombre' => 'Corregimiento de Morelia — Corregiduría de Morelia', 'direccion' => 'Corregiduría de Morelia'],
            [
                'nombre' => 'Corregimiento Estrella La Palmilla — Corregiduría de La Estrella',
                'direccion' => 'Corregiduría de La Estrella',
            ],
        ];

        foreach ($puntos as $i => $punto) {
            Centro::query()->updateOrCreate(
                ['nombre' => $punto['nombre']],
                [
                    'tipo' => TipoCentro::Censo,
                    'zona_id' => $zona->id,
                    'municipio_id' => $municipio->id,
                    'direccion' => $punto['direccion'],
                    'url_externa' => self::URL_PORTAL,
                    'telefono' => null,
                    'horario' => self::HORARIO,
                    'estado' => EstadoCentro::Abierto,
                    'descripcion' => $descripcionPresencial,
                    'emergencia' => false,
                    'activo' => true,
                    'orden' => $i + 1,
                ],
            );
        }
    }
}
