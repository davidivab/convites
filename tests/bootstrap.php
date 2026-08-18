<?php

/**
 * Bootstrap de PHPUnit — se ejecuta ANTES que vendor/autoload.php y por lo
 * tanto antes que Laravel resuelva `bootstrap/app.php` para cada test.
 *
 * CRÍTICO: docker-compose.yml inyecta DB_DATABASE=convites (base real de
 * dev) como variable de entorno del contenedor. El `force="true"` de
 * phpunit.xml en <env> solo pisa `putenv()`/`$_ENV`, NO `$_SERVER` — y
 * `env()` de Laravel prioriza `$_SERVER` en este stack, así que sin este
 * bootstrap los tests igual terminaban resolviendo la base real y
 * `RefreshDatabase` la vaciaba con `migrate:fresh`. Forzar acá los tres
 * (putenv + $_ENV + $_SERVER) antes de que exista ninguna app de Laravel
 * es la única forma de garantizar que TODO el proceso de tests use
 * `convites_test`, nunca la base de dev.
 */
putenv('DB_DATABASE=convites_test');
$_ENV['DB_DATABASE'] = 'convites_test';
$_SERVER['DB_DATABASE'] = 'convites_test';

require __DIR__.'/../vendor/autoload.php';
