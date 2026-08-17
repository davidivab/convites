# Finalizados — API Convites

Ítems completados (más recientes arriba). Incluir fecha y nota breve.
Sesión agente 2026-08-16 (loop 5h + trabajo previo en la misma chat).

---

### [P22] Notificaciones moderador por municipio — 2026-08-16
- Canal `database`; `ModeratorNotificationService` (moderador del municipio + admins)
- Eventos: iniciativa → revisión; aporte confirmado
- Inbox: `GET /api/notifications`, mark read / read-all; test `ModeratorNotificationsTest`

### [P26] Activity + Log genérico — 2026-08-16
- Tabla/modelo `Activity` (bigint ids, morphs, `data` json); `ActivityService` (DB + `Log::info`)
- Enganchado en moderación de iniciativas, aportes (confirmado/cancelado/recepción) y `POST /api/admin/users`
- Convive con `ModeracionAccion`; test `ActivityServiceTest`

### [P23]+[P24]+[P25] S3 uploads, db:backup, admin read — 2026-08-16
- **P23:** sin Attachment genérico; `FILESYSTEM_UPLOAD_DISK` + `UploadDisk` (s3 en prod, public en local); evidencia de aportes usa el disco; `league/flysystem-aws-s3-v3`; AWS_* en `.env.example`
- **P24:** `db:backup --keep-days=10` (mysqldump + gzip → S3 `backups/{Y/m}/`, prune); schedule `5 */4 * * *`; `mysql-client` en Docker
- **P25:** `GET /api/admin/iniciativas`, `/{slug}`, `/{slug}/aportes` (`users.manage`, sin scope municipio); admin ve nombres anónimos; test `AdminIniciativasReadTest`

### [P19]+[P20]+[P21] Voluntario, municipios N:M, moderación scoped — 2026-08-16
- **Decisión P19:** `voluntario` = rol **nuevo** (no renombrar `member`); admin crea vía `POST /api/admin/users` (roles `moderator`|`voluntario` + `municipio_ids`)
- Pivote única `usuario_municipio`; helpers `User::canModerateIniciativa` / `assignedMunicipioIds`
- Cola/acciones de moderación filtradas por municipios; admin sin filtro
- Recepción de aportes: creador **o** moderador del municipio; middleware `create|moderate`
- Tests: `AdminUsersAndMunicipioScopeTest`; demo `voluntario@convites.test`

### [P14]+[P15]+[P16]+[P17] Geo Colombia + aportantes/recepción/anónimo — 2026-08-16
- Catálogo `departamentos`/`municipios` (33 / 1122; activos Risaralda, Chocó, Valle) + `GET /api/catalogos/departamentos|municipios`
- `municipio_id` en iniciativas/users/centros/profesionales/solicitudes; `zona_id` opcional en iniciativas
- `GET iniciativas/{id}/aportantes` + `POST aportes/{id}/recepcion` (cumplido ↔ confirmado + evidencia imagen)
- `anonimo` en aportes; `AporteResource` oculta nombre salvo aportante/moderador
- Tests: `GeoAndAportesRecepcionTest`

### [P1]+[P11]+[P12] Auth BFF documentado + Larastan — 2026-08-16
- Auth del producto: Bearer Laravel + cookie httpOnly en el front (BFF); docs/cors/sanctum alineados
- Larastan level 5 + `phpstan-baseline.neon` (44 hallazgos día 1) + `composer analyse` en CI

### [P3]+[P4]+[P10]+[P12-B] Factories/422, FormRequests, purga PII, docs auth — 2026-08-16
- Factories: Zona, Categoria, Iniciativa, Profesional + `HasFactory`
- FormRequests: Store/Update Iniciativa, StoreAporte, RegisterProfesional, UpdateProfile
- `ValidationApiTest` (422 + happy path con factories)
- `convites:purge-profesional-solicitudes` (30d, REVIEW política) + schedule 03:15 + test
- P12-B absorbido luego por P1 BFF

### [P5] Dokploy multi-stage + entrypoint Comfy-style — 2026-08-16
- `Dockerfile.dokploy` multi-stage (base → composer deps → runtime) + HEALTHCHECK `/up`
- Entrypoint: wait MySQL → migrate fail-fast → storage:link → config/route/view cache → chown → `exec` supervisord
- `docker-compose.production.yml` (canonical Dokploy) + `docker-compose.prod.yml` alineado
- Supervisor: scheduler como `www-data`; logs a stdout
- README Dokploy actualizado (backups = Dokploy)

### [P7] Optimistic lock real en `iniciativas.version` — 2026-08-16
- PUT exige `version`; mismatch → **409**
- `version` expuesto en `IniciativaResource`; create setea `version: 1`
- Test Feature de conflicto; `AuthorizesRequests` en `Controller` base (faltaba para Policy)

### [R13] IniciativaPolicy + login ES — tick 11
- `IniciativaPolicy` + Gate; `update` / `enviarRevision` vía `authorize`
- Mensaje de login en español

### [profile] GET/PUT `/api/profile` — tick 10
- `ProfileController`: zona, género, edad, aptitud, habilidades, disponibilidades
- Permisos `profile.view` / `profile.update`

### [R25/R28/R30/R31/R32] Throttle + progreso + pint + docs — tick 9
- Throttle 60/min en listados públicos
- `progresoTotal()` simplificado
- `composer lint` / `lint:fix` (Pint) + CI
- Prefijo `moderacion` unificado
- README Dokploy (sin ports, `DB_PASSWORD` obligatorio)

### [CI] GitHub Actions PHPUnit — tick 8
- `.github/workflows/ci.yml` con MySQL 8.4

### [nginx] php-fpm `127.0.0.1:9000` — tick 7
- Contenedor único app+nginx

### [R14/R23/R24] Progreso N+1 + User/session — tick 6
- `IniciativaProgresoService` con query agrupada
- `User` fillable sin campos legales / `email_verified_at`
- `SESSION_SECURE_COOKIE` default en production

### [R12/R18] Token expiry + throttle login — tick 5
- Sanctum expiration 7 días (`SANCTUM_TOKEN_EXPIRATION`)
- Login throttle `5/min`

### [R2/R4/R7/R9/R11] Prod compose/entrypoint — tick 4
- `DB_PASSWORD` obligatorio (sin `changeme`)
- `migrate --force` fail-fast
- Healthcheck `GET /up`
- `LOG_CHANNEL=stderr`
- Supervisor: `schedule:work`

### [R1/R3/R5] Seed + dockerignore — tick 3
- `.dockerignore` (excluye `.env*`, vendor, tests…)
- `.gitignore` cubre `.env.*`
- `DatabaseSeeder` no crea users demo en production/staging

### Datos dummy (antes / durante el loop)
- `DemoDataSeeder` ampliado: 8 aportantes + creador2, iniciativas en todos los estados, aportes reales, pros pendientes, solicitudes, bitácora
- `docs/CUENTAS_DEMO.md`
- Seed corrido: ~12 users, 9 iniciativas, 16 aportes, 5 profesionales

### En código (tick 12, migración no aplicada aún)
- Migración FULLTEXT `titulo`/`resumen` + `applyTituloResumenSearch`
- `nota_moderacion` en resource (dueño/moderador)
