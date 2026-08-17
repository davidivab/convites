# Convites API

Backend API for Convites (cooperación comunitaria / convites en especie).  
El frontend vive en un repo separado (Next.js).

## Stack

- Laravel 13
- MySQL (`convites` / `convites_test`)
- Auth: Sanctum **Bearer token**
- Roles/permisos: Spatie Permission (`permission:` en rutas)

## Local (Docker)

| Servicio | Puerto |
|----------|--------|
| API (nginx) | **8095** |

MySQL en el host: `root` sin password.

```bash
cp .env.example .env
php artisan key:generate
composer install
docker compose up -d --build
```

API: http://localhost:8095  
Health: http://localhost:8095/up  
JSON root: http://localhost:8095/

Lint: `composer lint` · Análisis: `composer analyse` (Larastan level 5 + baseline) · Tests: `composer test`

Usuarios seed:

- `admin@convites.test` / `password`
- `member@convites.test` / `password`

## Auth

**Modelo actual:** Sanctum personal access tokens en la API.
El **front Next** guarda el token en cookie **httpOnly** vía BFF (`/api/auth/*` + `/api/proxy/*`).
El navegador no ve el Bearer; no hace falta Sanctum SPA stateful todavía.

```http
POST /api/auth/register
POST /api/auth/login
GET  /api/auth/me
POST /api/auth/logout
```

Header hacia Laravel (desde BFF o clientes de confianza): `Authorization: Bearer {token}`

`config/cors.php` → `supports_credentials = false` (el browser no llama cross-origin con cookies a la API).
Los `stateful` domains de Sanctum quedan como scaffold por si un día se unifica dominio.

Permisos de ejemplo: `config/route_permissions.php`.

CORS: `FRONTEND_URL` en `.env` (ej. `http://localhost:3095`).

## Retención PII (solicitudes a profesionales)

Comando diario `convites:purge-profesional-solicitudes` (default 30 días).
**REVIEW:** confirmar política de retención con el equipo/legal.

## Producción (Dokploy)

Template GitHub → compose:

```bash
docker compose -f docker-compose.production.yml up -d --build
```

(`docker-compose.prod.yml` es el mismo stack, nombre legacy.)

- Imagen: `Dockerfile.dokploy` (multi-stage: base → composer deps cacheadas → runtime)
- Entrypoint: wait MySQL → `migrate --force` (fail-fast) → `storage:link` → en `APP_ENV=production` cachea `config` / `routes` / `views` → supervisord
- Supervisor: php-fpm + nginx + `schedule:work` (como `www-data`)
- Volumen DB: `convites_db_data`
- Volúmenes storage: `convites_storage_app`, `convites_storage_logs`, `convites_storage_framework`
- **`DB_PASSWORD` es obligatorio** (sin fallback inseguro)
- El servicio `app` **no publica `ports:`** a propósito: Dokploy/Traefik mapea el contenedor desde fuera. Healthcheck: `GET /up`
- Logs: `LOG_CHANNEL=stderr`
- **Backups de MySQL:** configurar en Dokploy (no viven en este repo)

Lint local: `composer lint` / `composer lint:fix` (Pint).
