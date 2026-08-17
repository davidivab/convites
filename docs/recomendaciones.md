# Recomendaciones — Convites API (Backend)

_Generado el 2026-08-16 a partir de una revisión del código, configuración de Docker y preparación para producción (Dokploy)._

## 🔴 Críticas

(problemas de seguridad o que pueden romper producción)

### 1. Secretos de `.env` quedan embebidos en la imagen Docker de producción

No existe `.dockerignore` en la raíz del repo, y `docker/Dockerfile:27` hace `COPY . .` sobre el build context completo, que en cualquier build local incluye el `.env` real con credenciales. Ese archivo queda persistido en un layer de la imagen, exportable por cualquiera con acceso al registry o a `docker history`/`docker save`.

**Referencia:** `docker/Dockerfile:27` (falta `.dockerignore`)

### 2. Contraseña de MySQL con fallback inseguro en producción

`docker-compose.prod.yml:34,36,40` usan `${DB_PASSWORD:-changeme}` para `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD` y el healthcheck de `mysqladmin ping`. Si Dokploy no tiene seteada la variable `DB_PASSWORD` en el entorno de despliegue, tanto el root de MySQL como el usuario de la app quedan con la contraseña literal `changeme`, un valor público y predecible.

**Referencia:** `docker-compose.prod.yml:34,36,40`

### 3. El seeder de producción puede sobrescribir un admin real con contraseña hardcodeada

`database/seeders/DatabaseSeeder.php:25-62` crea `admin@convites.test`, `moderator@convites.test` y `member@convites.test` con contraseña fija `'password'` para los tres, sin ningún guard de entorno (`app()->environment(...)`). Usa `updateOrCreate`, así que si el seeder corre por error contra producción (deploy mal configurado, comando manual), **sobrescribe** la contraseña de una cuenta existente con ese email a un valor público y conocido.

**Referencia:** `database/seeders/DatabaseSeeder.php:25-62`

### 4. Fallo de migración silenciado en el arranque del contenedor

`docker/entrypoint.sh:18` corre `php artisan migrate --force || true`. Si la migración falla (columna faltante, conflicto de esquema, timeout de DB), el `|| true` traga el error y el contenedor arranca igual sirviendo tráfico contra un esquema desactualizado o inconsistente, en vez de fallar rápido y visible en el pipeline de deploy.

**Referencia:** `docker/entrypoint.sh:18`

### 5. `.env.testing` no está cubierto por `.gitignore`

`.gitignore:3-5` solo ignora `.env`, `.env.backup` y `.env.production` (match literal de nombre). `.env.testing` no está en la lista, por lo que un `git add -A`/`git add .` lo commitearía tal cual esté en disco — riesgo real de fuga de credenciales si alguien llega a poner ahí un valor no genérico.

**Referencia:** `.gitignore:3-5`

---

## 🟠 Importantes

(deuda técnica relevante, riesgos de mantenibilidad, performance)

### 6. `COPY . .` puede pisar el `vendor/` instalado sin dependencias de dev

`docker/Dockerfile:24-25` instala con `composer install --no-dev --optimize-autoloader`, pero la línea 27 (`COPY . .`) copia todo el build context, incluyendo un eventual `vendor/` del host si existe ahí (por ejemplo si alguien corrió `composer install` local antes del build). Eso anula el `--no-dev` y deja herramientas de desarrollo/test en la imagen de producción. Sumado al punto 1, confirma que falta un `.dockerignore` que excluya `vendor/`, `.env`, `node_modules`, `.git`, etc. del build context.

**Referencia:** `docker/Dockerfile:24-27`

### 7. El scheduler de Laravel nunca corre en producción

`routes/console.php:23-26` define `RecalcularProgresosIniciativasCommand` como cron horario (`->hourly()->withoutOverlapping()`), documentado explícitamente como "red de seguridad ante drift de contadores". Pero `docker/supervisor.conf` solo define los procesos `php-fpm` y `nginx` — no hay `cron` ni ningún proceso corriendo `schedule:run` en el contenedor. Esa red de seguridad para los contadores de progreso (relacionada con el punto 12) queda muerta en prod.

**Referencia:** `docker/supervisor.conf`, `routes/console.php:23-26`

### 8. Sin usuario no-root en las imágenes

Ni `docker/Dockerfile` ni `docker/Dockerfile.dev` definen `USER`. Todo el proceso (`php-fpm`, `nginx`, `supervisord`) corre como root dentro del contenedor, ampliando el impacto de cualquier vulnerabilidad de ejecución remota en la app o en una dependencia.

**Referencia:** `docker/Dockerfile`, `docker/Dockerfile.dev`

### 9. Sin healthcheck del servicio `app` en `docker-compose.prod.yml`

Solo el servicio `db` tiene `healthcheck` (`docker-compose.prod.yml:39-43`). Laravel ya expone la ruta nativa `/up` (`bootstrap/app.php:16`, `health: '/up'`), pero no está conectada a ningún healthcheck de Docker ni referenciada en el compose. Un contenedor "corriendo" pero devolviendo 500 en cada request no se detecta automáticamente ni por Docker ni por Dokploy.

**Referencia:** `docker-compose.prod.yml` (servicio `app`, falta `healthcheck`), `bootstrap/app.php:16`

### 10. Volumen de base de datos sin estrategia de backup

`docker-compose.prod.yml:51-53` define el volumen nombrado `convites_db_data`. No hay ningún script, cron o job de backup en el repo. Un volumen Docker no es un backup: si se corrompe o se borra el host, se pierde toda la base sin posibilidad de recuperación.

**Referencia:** `docker-compose.prod.yml:51-53`

### 11. Logging probablemente escribe a archivo dentro del contenedor, no a stdout

`config/logging.php` usa por defecto el canal `stack` → `single` (archivo `storage/logs/laravel.log`), y `docker-compose.prod.yml` no fija `LOG_CHANNEL`/`LOG_STACK` para forzar un canal apto para contenedores (`stderr`). Si `.env` de producción no lo sobreescribe, los logs de la aplicación no aparecen en `docker logs`/Dokploy y solo viven en el volumen `storage_logs`. En contraste, `php-fpm` y `nginx` sí redirigen correctamente su stdout/stderr vía `docker/supervisor.conf:10-13,19-22`.

**Referencia:** `config/logging.php`, `docker-compose.prod.yml`

### 12. Tokens de Sanctum sin expiración

`config/sanctum.php:53` tiene `'expiration' => null`. Los tokens creados en `AuthController` (`createToken('spa')`) nunca expiran, y `logout` solo revoca el token actual, no todos los del usuario. Si un token se filtra (log, XSS, dispositivo perdido), queda válido indefinidamente sin mecanismo de expiración automática.

**Referencia:** `config/sanctum.php:53`, `app/Http/Controllers/Api/AuthController.php`

### 13. Lógica de autorización de ownership duplicada en lugar de Policies

No existen Policies (`app/Policies` está vacío) ni `Gate::define`. El chequeo "dueño o moderador" se repite manualmente en al menos 3 lugares: `IniciativaController::assertOwnerOrModerator` (líneas 349-358), inline en `update()` (línea 205), y `AporteService::cancelar()` (línea 141). Cambiar la regla de negocio de ownership requiere tocar varios archivos en vez de uno solo, y aumenta el riesgo de que alguno quede desactualizado.

**Referencia:** `app/Http/Controllers/Api/IniciativaController.php:205,349-358`, `app/Services/AporteService.php:141`

### 14. N+1 en la ruta transaccional más caliente del sistema

`IniciativaProgresoService::recalcular()` (líneas 41-57) ejecuta una query separada por cada `IniciativaItem` de la iniciativa dentro de un `foreach`. Este método se invoca en cada `confirmar()`/`cancelar()` de aporte — la ruta con más escritura concurrente del sistema. Con varios ítems por iniciativa, esto multiplica queries innecesariamente; se podría resolver con una sola query agrupada (`groupBy`) y un `upsert`.

**Referencia:** `app/Services/IniciativaProgresoService.php:41-57`

### 15. Campo `version` como optimistic lock decorativo, sin enforcement real

La migración `2026_08_16_162000_harden_iniciativas_for_scale.php` documenta `version` como "optimistic lock ligero para contadores concurrentes". `IniciativaController.php:242` e `IniciativaProgresoService.php:55,73` lo incrementan, pero en ningún punto del código se compara/valida ese valor antes de guardar (cero `where('version', ...)` en el repo), ni `update()` usa `lockForUpdate()`. Dos `PUT` concurrentes al mismo recurso pueden pisarse entre sí sin que nada lo detecte — el campo existe pero no protege nada todavía.

**Referencia:** `app/Http/Controllers/Api/IniciativaController.php:242`, `app/Services/IniciativaProgresoService.php:55,73`

### 16. Cobertura de tests mínima y ausencia total de CI/CD

Solo 5 archivos de test en todo el repo (8 métodos totales), de los cuales 2 son el boilerplate `ExampleTest.php` de Laravel sin modificar. `tests/Unit` está completamente vacío — cero tests para `AporteService`, `IniciativaProgresoService` o `NominatimService`. No existe `.github/workflows/` ni ningún otro sistema de CI (`.gitlab-ci.yml`, `.circleci/`, etc.): nada corre tests o lint automáticamente en push o PR, por lo que ni siquiera la cobertura mínima existente se verifica de forma consistente.

**Referencia:** `tests/`, ausencia de `.github/workflows/`

### 17. Sin FormRequest classes ni tests de validación negativa

`app/Http/Requests` no existe: toda la validación es inline con `$request->validate()`, repetida en al menos 4 controllers (ej. la regla `zona_id => exists:zonas,id` en `IniciativaController`, `CentroController`, `ProfesionalController`, `GeoController`). Tampoco hay ningún test que verifique el camino de error (422) al crear una iniciativa, un aporte o registrar un profesional — todos los tests existentes cubren solo el happy path.

**Referencia:** `app/Http/Controllers/Api/IniciativaController.php:283-314`, `CentroController.php:77-97`, `ProfesionalController.php:63-74`

### 18. Throttle de login más permisivo que lo recomendado

`routes/api.php:19-21` permite 20 intentos/min de login por IP (`register` 10/min). 20/min es notablemente más permisivo que los 5-6/min típicamente recomendados para mitigar fuerza bruta de contraseñas.

**Referencia:** `routes/api.php:19-21`

---

## 🟡 Sugeridas

(mejoras de calidad, nice-to-have)

### 19. Sin multi-stage build en `docker/Dockerfile`

Un único `FROM php:8.4-fpm-alpine` (línea 1) instala `git`, `curl`, `bash`, `mysql-client` y deja `composer` binario en la imagen final, sin necesidad real en runtime. Un build multi-stage reduciría el tamaño y la superficie de ataque de la imagen productiva.

**Referencia:** `docker/Dockerfile:1-18`

### 20. Imágenes base con tag flotante, no pineadas por digest

`php:8.4-fpm-alpine`, `mysql:8.4`, `composer:2` no usan `latest`, pero tampoco están fijadas por digest SHA256. Un rebuild en fecha distinta puede traer una versión de parche diferente sin que quede registrado en el repo.

**Referencia:** `docker/Dockerfile:1,20`, `docker-compose.prod.yml:29`

### 21. Sin `queue:work` configurado en supervisor

`docker/supervisor.conf` no define ningún worker de cola. Hoy no hay `app/Jobs/` en el proyecto, así que el impacto es bajo, pero es un gap latente si se agregan colas (ej. envío de notificaciones, mencionado como pendiente en `routes/console.php:19`).

**Referencia:** `docker/supervisor.conf`

### 22. Config de CORS/Sanctum "stateful" es scaffold sin uso real

`config/cors.php:22` (`supports_credentials => false`) y `config/sanctum.php` (`stateful` domains, `guard => ['web']`) sugieren soporte de autenticación SPA por cookie, pero el flujo real de auth es 100% bearer token (`AuthController`). No es un riesgo de seguridad activo, pero es configuración heredada del scaffold de Laravel que puede confundir a futuros desarrolladores sobre qué mecanismo de auth se usa realmente.

**Referencia:** `config/cors.php:22`, `config/sanctum.php`

### 23. `SESSION_SECURE_COOKIE` sin default explícito

`config/session.php:172` usa `env('SESSION_SECURE_COOKIE')` sin segundo argumento, por lo que cae a `null`/`false` si no está seteada en `.env`. Bajo impacto hoy porque no hay rutas con sesión real activa, pero es un riesgo latente si se agregan rutas `web` con sesión sin fijar explícitamente `true` en producción.

**Referencia:** `config/session.php:172`

### 24. `$fillable` de `User` incluye campos que no deberían ser mass-assignable desde un futuro endpoint de perfil

`app/Models/User.php` incluye `email_verified_at`, `acepta_terminos_at`, `acepta_descargo_at` en `$fillable`. Hoy ningún controller hace mass-assignment de esos campos sobre `User`, pero si se agrega un endpoint de "actualizar perfil" con `$user->update($request->all())` sin whitelist explícita, un usuario podría auto-verificar su email.

**Referencia:** `app/Models/User.php`

### 25. Sin rate limit en `GET /iniciativas` y `/iniciativas/mapa`

A diferencia de `geo/search` (`throttle:20,1`) y `geo/reverse` (`throttle:30,1`), las rutas `GET /iniciativas` (línea 47) y `GET /iniciativas/mapa` (línea 48) no tienen `throttle`. Ambas hacen búsqueda `LIKE '%...%'` sin índice full-text (ver punto 26), lo que las vuelve una superficie de scraping/DoS de bajo costo.

**Referencia:** `routes/api.php:47-48`

### 26. Sin estrategia de búsqueda full-text para `titulo`/`resumen`

`IniciativaController::index()`/`mapa()` filtran con `LIKE '%q%'` (wildcard al inicio) sobre `titulo`/`resumen`. Ningún índice B-tree ayuda con ese patrón; si el volumen de datos crece, conviene migrar a búsqueda full-text (MySQL `FULLTEXT` o similar).

**Referencia:** `app/Http/Controllers/Api/IniciativaController.php:44-50,90-96`

### 27. PII en `profesional_solicitudes` sin retención/expiración a nivel de esquema

La tabla almacena `nombre`, `celular`, `email`, `ip_address`, `user_agent` sin `expires_at` ni job de purga, a diferencia de `idempotency_keys` que sí define `expires_at` con índice. El comentario de la propia migración menciona "retención / rate-limit en capa de aplicación", pero no hay job de limpieza visible en el repo.

**Referencia:** `database/migrations/` (tabla `profesional_solicitudes`)

### 28. `Iniciativa::progresoTotal()` con lógica condicional redundante

Mezcla dos estrategias de cálculo (cache vs. en vivo) con una condición confusa: el `if` externo no cambia de forma útil el resultado del `if` interno. No es un bug funcional, pero dificulta la lectura y el mantenimiento futuro.

**Referencia:** `app/Models/Iniciativa.php:180-198`

### 29. Solo existe `UserFactory`; el resto de modelos no tiene factories

`database/factories/` solo tiene `UserFactory.php`. Los tests de Feature dependen enteramente de correr los seeders completos (incluyendo `DemoDataSeeder`, pensado para datos demo/dev) para obtener fixtures, lo que acopla la corrección de los tests a datos de demo que pueden cambiar y dificulta testear casos borde.

**Referencia:** `database/factories/`, `tests/Feature/IniciativaApiTest.php:20-21,44-45,97-100`

### 30. Sin script de lint/análisis estático configurado

`laravel/pint` está como dependencia dev (`composer.json`) pero no hay script que lo invoque desde `composer.json`, y no hay PHPStan/Larastan instalado. Sumado al punto 16, no hay ninguna automatización de calidad de código en el repo.

**Referencia:** `composer.json`

### 31. Dos grupos `Route::prefix('moderacion')` separados

`routes/api.php:115` y `routes/api.php:124` — funcionalmente correcto pero redundante; podría unificarse en un solo grupo con los permisos combinados.

**Referencia:** `routes/api.php:115,124`

### 32. `docker-compose.prod.yml` sin `ports`/`labels` explícitos

Puede ser intencional si Dokploy maneja el ruteo/proxy externamente, pero no es verificable desde el repo — vale la pena documentar esa dependencia externa en el propio compose o en el README de despliegue.

**Referencia:** `docker-compose.prod.yml` (servicio `app`)
