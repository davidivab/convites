# Deploy en Dokploy — Convites (API + Front)

Guía paso a paso para desplegar **convites** (API, Laravel) y **convites-front** (Next.js) en Dokploy como dos aplicaciones independientes que se comunican entre sí. Escrita después de auditar el pipeline de deploy real de ambos repos (Dockerfile, compose, entrypoint, comando de backup) y corregir dos bugs críticos encontrados en el camino — ver `finalizados.md` para el detalle de cada fix.

**Principio rector:** esto va a producción. Ningún paso de esta guía debe ejecutarse "a ojo" — cada uno tiene su verificación explícita antes de pasar al siguiente.

---

## 1. Arquitectura del deploy

```
                     ┌─────────────────────┐
  Internet ──HTTPS──▶│  Dokploy / Traefik   │
                     └──────────┬──────────┘
                    ┌────────────┴────────────┐
                    ▼                          ▼
          convites-front (Next.js)     convites (Laravel API)
          Dockerfile.dokploy            Dockerfile.dokploy
          puerto interno 3000           nginx + php-fpm, puerto 80
          NEXT_PUBLIC_API_URL ────────▶ (llama a la API por HTTPS)
                                              │
                                              ▼
                                    MySQL 8.4 (contenedor propio,
                                    definido en el mismo compose
                                    de convites — volumen persistente
                                    `convites_db_data`)
```

Son **dos aplicaciones separadas en Dokploy** (dos repos, dos dominios, dos deploys independientes). El front necesita la URL pública de la API en **build time** (ver sección 5) — por eso, si la API cambia de dominio, el front necesita **rebuild**, no solo un restart.

---

## 2. Checklist pre-deploy (obligatorio, no saltarse ninguno)

- [ ] **Backup de cualquier dato existente** que no venga de este deploy (si estás migrando desde otro entorno). Este proyecto arranca de cero en Dokploy, pero si alguna vez hay datos reales antes de re-desplegar: `php artisan db:backup` manual y confirmar el archivo en S3 antes de tocar nada.
- [ ] **Generar `APP_KEY` una sola vez** (`php artisan key:generate --show`) y guardarlo — perderlo invalida todas las cookies/tokens firmados existentes.
- [ ] **Confirmar credenciales AWS S3 reales** (bucket, key, secret) — no las de dev/test.
- [ ] **Confirmar credenciales de Google OAuth de producción** (Client ID/Secret) y que el **Authorized redirect URI** en Google Cloud Console apunte exactamente a `https://<dominio-api>/api/auth/google/callback`.
- [ ] **Confirmar credenciales SMTP de producción** (Mailtrap es solo para pruebas — cambiar antes de ir a producción real).
- [ ] **Definir los dominios finales** de API y front (necesarios para `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `GOOGLE_FRONTEND_CALLBACK_URL`, `NEXT_PUBLIC_API_URL`).
- [ ] **Autodeploy de Dokploy desactivado** hasta terminar la primera verificación completa (evita que un push a `main` dispare un segundo deploy a mitad de la validación).

---

## 3. Backend (`convites`) — paso a paso

### 3.1. Crear la aplicación en Dokploy

1. Nueva aplicación → tipo **Docker Compose** (no "Application" simple — el backend necesita su propio MySQL en el mismo compose).
2. Repositorio: `convites`, rama `main` (nunca `dev` en producción — `dev` es la rama de trabajo de los agentes).
3. **Compose file:** `docker-compose.production.yml` (es el nombre canónico para Dokploy; `docker-compose.prod.yml` es un alias legacy idéntico, por si alguna plantilla vieja lo referencia).
4. **Build context:** raíz del repo (usa `Dockerfile.dokploy`, definido dentro del compose).

### 3.2. Variables de entorno (Dokploy → Environment)

| Variable | Valor | Notas |
|---|---|---|
| `APP_ENV` | `production` | Activa cachés de config/route/view en el entrypoint |
| `APP_KEY` | `base64:...` | Generado en el checklist — **nunca lo regeneres** en un redeploy |
| `APP_DEBUG` | `false` | Nunca `true` en prod (expone stack traces) |
| `APP_URL` | `https://api.tudominio.co` | Dominio final de la API |
| `DB_DATABASE` | `convites` | Usado también por el servicio `db` del compose |
| `DB_USERNAME` | `convites` | |
| `DB_PASSWORD` | *(secreto fuerte)* | **Obligatorio** — el compose falla al arrancar si no está seteado (`:?Set DB_PASSWORD...`) |
| `SANCTUM_STATEFUL_DOMAINS` | `tudominio.co,www.tudominio.co` | Dominios del **front** (no de la API) que pueden mandar cookies |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | *(de Google Cloud Console, prod)* | |
| `GOOGLE_REDIRECT_URI` | `https://api.tudominio.co/api/auth/google/callback` | Debe existir tal cual en Google Cloud Console |
| `GOOGLE_FRONTEND_CALLBACK_URL` | `https://tudominio.co/auth/google/callback` | A dónde vuelve el navegador tras el login |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` | *(SMTP real de prod)* | Mailtrap es solo dev/test |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` | *(credenciales reales)* | Usado para uploads públicos **y** para `db:backup` (dump se sube como `private` al mismo bucket, ver 3.5) |
| `APP_IMAGE_NAME` | `convites-api` (o el que prefieras) | Solo naming de la imagen local |

No hace falta setear `FILESYSTEM_UPLOAD_DISK` — con `APP_ENV=production` ya resuelve a `s3` automáticamente (`config/filesystems.php`).

### 3.3. Puerto y dominio

- **Container Port: 80** (el `Dockerfile.dokploy` expone nginx en 80, no el puerto de PHP-FPM).
- Configurar el dominio con HTTPS (Dokploy/Traefik gestiona el certificado). Verificar que el healthcheck del compose (`curl http://127.0.0.1/up`) esté verde antes de exponer el dominio al público.

### 3.4. Primer deploy — qué pasa automáticamente

El `entrypoint.sh` (ya en el repo, sin tocar) hace, en este orden:
1. Espera a que MySQL esté listo (hasta 40 intentos × 2s).
2. `php artisan migrate --force --no-interaction` — **aditivo, nunca `migrate:fresh`**. Aun así: en un primer deploy sobre una base de datos NUEVA y vacía esto es seguro por definición; en un redeploy posterior, revisar que la migración más reciente no sea destructiva (dropear una columna con datos, por ejemplo) antes de mergear a `main`.
3. `storage:link`.
4. `php artisan db:seed --class=DatabaseSeeder --force --no-interaction` — corre en **cada** deploy (no solo el primero). `DatabaseSeeder` detecta el entorno y en producción solo llama seeders idempotentes de catálogos/roles/datos oficiales (`updateOrCreate`/`firstOrCreate`), nunca usuarios demo. Ver 3.5 para el porqué de este cambio.
5. `config:cache` / `route:cache` / `view:cache` (solo si `APP_ENV=production`).

**Verificación post-deploy (obligatoria):**
```bash
curl -fsS https://api.tudominio.co/up
```
Debe responder 200. Si no, revisar logs del contenedor en Dokploy antes de seguir.

### 3.5. Seed de catálogos y datos oficiales (automático desde el entrypoint)

`DatabaseSeeder` tiene un guard de producción (`app()->environment('production', 'prod', 'staging')`): en prod **no** crea usuarios demo con password fija ni corre `DemoDataSeeder` — solo catálogos, geo, roles, datos legales y datos oficiales de producto (ej. `CensoAfectacionesSeeder`), todos vía `updateOrCreate`/`firstOrCreate`.

**Bug real (agosto 2026):** este seed se documentaba como "correr una sola vez a mano" tras el primer deploy. Cuando se agregó `CensoAfectacionesSeeder` en un commit posterior, nadie volvió a correr el comando manual en producción → los puntos oficiales del censo nunca existieron en prod aunque sí en local (que sí corre el seed completo). Los usuarios veían "sin resultados" con cualquier filtro en `/centros`.

Por eso ahora `entrypoint.sh` corre `php artisan db:seed --class=DatabaseSeeder --force --no-interaction` en **cada** arranque del contenedor (ver 3.4, paso 4). Es seguro porque todo lo que se ejecuta en la rama de producción es idempotente — nunca duplica ni sobrescribe con datos falsos. **Regla para el futuro:** cualquier seeder nuevo de datos oficiales (no demo) debe agregarse a la rama de producción de `DatabaseSeeder::run()` — no alcanza con agregarlo solo a la rama local, o quedará invisible en cada deploy limpio.

Si de todas formas necesitás correrlo a mano (ej. para forzar un backfill puntual sin reiniciar el contenedor), sigue disponible desde la consola de Dokploy:

```bash
php artisan db:seed --class=DatabaseSeeder --force
```

Después del primer deploy, el **primer usuario administrador real** hay que crearlo a mano (no hay un admin demo en prod) — vía `php artisan tinker` o un comando dedicado, asignando el rol `admin` con Spatie.

### 3.6. Verificar que el backup realmente funciona (crítico)

Este proyecto tuvo un bug real donde `db:backup` fallaba silenciosamente en cada corrida (ver `finalizados.md`, fix ya aplicado — usa `druidfi/mysqldump-php`, no el binario `mysqldump`, así que no depende de plugins del cliente CLI). Aun así, **verificar manualmente en el entorno real de Dokploy** antes de confiar en el cron:

```bash
php artisan db:backup
```

Debe responder `Backup uploaded → backups/AAAA/MM/db_backup_....sql.gz (X MB)`. Confirmar en el bucket S3 que el objeto:
- Existe.
- **Tiene ACL privada** (no pública) — es un dump completo de la base de datos.

El schedule ya corre esto solo cada 4 horas (`routes/schedule/backup.php`) vía `php artisan schedule:work`, que el `supervisor.conf` del contenedor ya deja corriendo — no hace falta configurar un cron externo en Dokploy para esto.

---

## 4. Frontend (`convites-front`) — paso a paso

### 4.1. Crear la aplicación en Dokploy

1. Nueva aplicación → tipo **Docker Compose**.
2. Repositorio: `convites-front`, rama `main`.
3. **Compose file:** `docker-compose.production.yml` (mismo criterio que el backend: alias `docker-compose.prod.yml` idéntico por compatibilidad).

### 4.2. Variables de entorno

| Variable | Valor | Notas |
|---|---|---|
| `NEXT_PUBLIC_API_URL` | `https://api.tudominio.co` | **Se hornea en build time** — cambiarla requiere rebuild de la imagen, no alcanza con reiniciar el contenedor |
| `API_URL` | `https://api.tudominio.co` | Usado server-side (rutas BFF de Next); si no se define, cae al mismo valor de `NEXT_PUBLIC_API_URL` |
| `APP_IMAGE_NAME` | `convites-front` | Naming de la imagen local |

No hace falta `FRONT_PORT` en Dokploy: el compose de producción **no publica** el puerto en el host (evita `Bind … :3000 failed: port is already allocated`). Traefik llega al contenedor por la red interna.

### 4.3. Puerto y dominio

- **Container Port: 3000** (puerto interno del contenedor; configurar así el dominio en Dokploy).
- El healthcheck ya está definido en el compose (`GET /api/health`, cada 30s) — confirmar que la ruta responde antes de exponer el dominio.

### 4.4. Primer deploy — verificación

```bash
curl -fsS https://tudominio.co/api/health
```

Luego, smoke test manual real en el navegador (no solo curl):
1. `/ingresar` — login con un usuario ya sembrado o con Google.
2. `/registrarse` — completar el wizard con una cuenta de prueba.
3. `/explorar` — confirmar que trae datos reales de la API (no pantalla de error).
4. `/admin/usuarios` (con el admin creado en 3.5) — confirmar que lista usuarios.

---

## 5. Orden recomendado de deploy

1. **Backend primero** (necesita existir antes de que el front pueda apuntarle).
2. Verificar `/up` del backend.
3. **Frontend después**, con `NEXT_PUBLIC_API_URL` ya apuntando al dominio real del backend.
4. Smoke tests de la sección 4.4.
5. Recién ahí, activar autodeploy si se desea.

---

## 6. Rollback

- **Dokploy** guarda las imágenes de builds anteriores — desde el panel, "Rollback" a la imagen previa es la vía más rápida si un deploy rompe algo.
- **Base de datos:** las migraciones de esta app son aditivas por diseño (revisar cualquier migración nueva antes de mergear a `main` si dropea columnas/tablas). Si algo sale mal después de migrar, restaurar desde el backup más reciente en S3 (`backups/AAAA/MM/db_backup_....sql.gz`) es la red de seguridad — descomprimir y `mysql < dump.sql` contra una base de recuperación antes de sobrescribir la real.

## 7. Troubleshooting conocido

| Síntoma | Causa | Fix |
|---|---|---|
| Tests o comandos locales usan la base de datos real en vez de la de test | `docker-compose.yml` inyecta `DB_DATABASE` como env var del contenedor; sin forzar `$_SERVER` además de `putenv`/`$_ENV`, `env()` de Laravel prioriza el valor del contenedor | Ya arreglado (`tests/bootstrap.php`) — no debería reaparecer, pero si se toca `phpunit.xml` de nuevo, verificar que el bootstrap siga apuntando ahí |
| `db:backup` falla con "Plugin caching_sha2_password could not be loaded" | El cliente `mysqldump` de la imagen Alpine no trae ese plugin (auth default de MySQL 8+/9+) | Ya arreglado — el comando usa `druidfi/mysqldump-php` (PDO puro), no el binario del sistema |
| `config:cache` ignora variables de entorno del contenedor | `php artisan config:cache` cachea los valores presentes en ese momento; si se corre manualmente con un `.env` desactualizado puede pisar las env vars reales | `php artisan config:clear` y volver a cachear con el entorno correcto activo |
| Backend responde pero el front no puede loguearse | `SANCTUM_STATEFUL_DOMAINS` no incluye el dominio del front, o las cookies no llegan por no ser HTTPS | Confirmar dominio exacto (sin `https://`, solo el host) en `SANCTUM_STATEFUL_DOMAINS`, y que ambos dominios tengan TLS activo (las cookies son `Secure` en producción) |

---

_Última actualización: 2026-08-18._
