<?php

/**
 * Extrae departamentos y municipios de Colombia del dataset CSC (Comfy).
 *
 * Uso: php scripts/extract-colombia-geo.php
 */

declare(strict_types=1);

$sourceDir = '/Users/david/Desktop/projects/comfy/comfy_back_v2/database/sql/postgresql/data';
$outFile = __DIR__.'/../database/data/colombia-geo.json';

$activeNames = ['Risaralda', 'Chocó', 'Valle del Cauca'];

function unquote(string $s): string
{
    return str_replace("''", "'", $s);
}

function parseStates(string $sql): array
{
    // (id, 'name', 48, 'CO', ...
    preg_match_all(
        "/\(\s*(\d+)\s*,\s*'((?:''|[^'])*)'\s*,\s*48\s*,\s*'CO'\s*,\s*(?:NULL|'[^']*')\s*,\s*(?:NULL|'((?:''|[^'])*)')/u",
        $sql,
        $matches,
        PREG_SET_ORDER,
    );

    $states = [];
    foreach ($matches as $m) {
        $states[] = [
            'external_id' => (int) $m[1],
            'nombre' => unquote($m[2]),
            'codigo' => isset($m[3]) && $m[3] !== '' ? unquote($m[3]) : null,
        ];
    }

    return $states;
}

function parseCities(string $sql): array
{
    // (id, 'name', state_id, 'state_code', 48, 'CO',
    preg_match_all(
        "/\(\s*(\d+)\s*,\s*'((?:''|[^'])*)'\s*,\s*(\d+)\s*,\s*(?:NULL|'((?:''|[^'])*)')\s*,\s*48\s*,\s*'CO'/u",
        $sql,
        $matches,
        PREG_SET_ORDER,
    );

    $cities = [];
    foreach ($matches as $m) {
        $cities[] = [
            'external_id' => (int) $m[1],
            'nombre' => unquote($m[2]),
            'state_external_id' => (int) $m[3],
            'state_code' => isset($m[4]) && $m[4] !== '' ? unquote($m[4]) : null,
        ];
    }

    return $cities;
}

$statesSql = file_get_contents($sourceDir.'/states data.sql');
if ($statesSql === false) {
    fwrite(STDERR, "No se pudo leer states data.sql\n");
    exit(1);
}

$states = parseStates($statesSql);
if (count($states) < 30) {
    fwrite(STDERR, 'Pocos estados CO parseados: '.count($states)."\n");
    exit(1);
}

$cities = [];
foreach (glob($sourceDir.'/cities data *.sql') ?: [] as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        continue;
    }
    $chunk = parseCities($sql);
    echo basename($file).': '.count($chunk)." ciudades CO\n";
    $cities = array_merge($cities, $chunk);
}

$activeExternalIds = [];
foreach ($states as &$state) {
    $state['activo'] = in_array($state['nombre'], $activeNames, true);
    if ($state['activo']) {
        $activeExternalIds[] = $state['external_id'];
    }
}
unset($state);

foreach ($cities as &$city) {
    $city['activo'] = in_array($city['state_external_id'], $activeExternalIds, true);
}
unset($city);

$payload = [
    'generated_at' => date('c'),
    'source' => 'comfy_back_v2/database/sql/postgresql/data (CSC)',
    'active_departamentos' => $activeNames,
    'departamentos' => $states,
    'municipios' => $cities,
    'counts' => [
        'departamentos' => count($states),
        'municipios' => count($cities),
        'municipios_activos' => count(array_filter($cities, fn ($c) => $c['activo'])),
    ],
];

if (! is_dir(dirname($outFile))) {
    mkdir(dirname($outFile), 0755, true);
}

file_put_contents(
    $outFile,
    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n",
);

echo 'OK → '.$outFile."\n";
echo 'Departamentos: '.$payload['counts']['departamentos']."\n";
echo 'Municipios: '.$payload['counts']['municipios'].' (activos '.$payload['counts']['municipios_activos'].")\n";
