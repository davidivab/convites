# Finalizados — API Convites

Ítems completados (más recientes arriba). Incluir fecha y nota breve.
Sesión agente 2026-08-16 (loop 5h + trabajo previo en la misma chat).

---

### FIX CRÍTICO: tests vaciaban la base real de dev — 2026-08-18 (Claude)
- **Incidente:** `docker compose exec app php artisan test` corría `RefreshDatabase` (→ `migrate:fresh`) contra `convites` (la base real de dev/demo), no contra `convites_test`. Se perdieron los datos demo (usuarios, iniciativas, catálogo geo, centros) — restaurados con `php artisan db:seed --class=DatabaseSeeder`.
- **Causa raíz:** `docker-compose.yml` inyecta `DB_DATABASE=convites` como variable de entorno real del contenedor. `phpunit.xml` pedía `convites_test`, pero el `force="true"` de `<env>` solo pisa `putenv()`/`$_ENV`, no `$_SERVER` — y `env()` de Laravel prioriza `$_SERVER` en este stack.
- **Fix:** `tests/bootstrap.php` fuerza `putenv` + `$_ENV` + `$_SERVER` antes de que Laravel arranque; `phpunit.xml` usa ese bootstrap en vez de `vendor/autoload.php` directo.
- **Verificado:** suite completa corrida 2 veces después del fix — `convites` se mantuvo en 13 usuarios/9 iniciativas ambas veces; `convites_test` recibió el `migrate:fresh` normal.
- **Pendiente de verificar:** si hubo contenido creado manualmente en el demo (no vía seeders) antes del incidente, ese no se pudo recuperar.

### Búsqueda inversa por material `/api/materiales` — 2026-08-18 (Claude, TDD)
- Idea de Patricia (feedback de publicidad): "tengo este material, ¿a quién le sirve?"
- Lista ítems con `faltante > 0` de iniciativas publicadas/en curso; mismos filtros que explorar (zona/municipio/departamento/categoría/urgencia) + `q` por nombre
- Tests `MaterialesTest` (6) verdes; suite completa 102 passed
- **Listo F34** (nueva pestaña "Materiales" en Explorar)
- Nota: se revirtieron ediciones de backend sin commitear encontradas en el árbol (wizard iniciativas/perfil voluntario/galería) — ver nota en `pendientes.md`

### docs/bot: base de conocimiento para bot de soporte — 2026-08-18 (Claude)
- 5 documentos funcionales (no técnicos) basados en el código real: qué es Convites, registro/roles, crear/moderar convites, donar/aportar, puntos de acopio y censo oficial

### [P47] Google OAuth: intent login vs registro + completar-registro — 2026-08-17 (Claude, TDD)
- `?intent=`, pending cache, `POST /api/auth/google/completar-registro`; no crea cuenta en login sin registro
- Tests `GoogleAuthIntentTest` + suite GoogleAuth verdes
- **Listo F31**

### [P45] Tipo censo + puntos oficiales Pereira — 2026-08-17 (Cursor, TDD)
- `TipoCentro::Censo`; columna `url_externa`; `CensoAfectacionesSeeder` (portal + 24 puntos; municipio Pereira)
- `GET /api/centros?tipo=censo`; tests `CensoAfectacionesTest` (2)
- **Listo F26**

### [P38] Admin list: búsqueda + contacto en index — 2026-08-17 (Cursor, TDD)
- `q` ≥3 ampliado; `verificacion` ya via resource para admin; test search
- **Listo F12**

### [P41] Moderador puede PUT iniciativa — 2026-08-17 (Cursor, TDD)
- Middleware `permission:iniciativas.update_own|iniciativas.moderate`
- Tests municipio in/out; **Listo F19**
- Nota: si hay `route:cache`, correr `php artisan route:clear`

### [P38-partial] `verificacion` en IniciativaResource para owner/mod — 2026-08-17 (Cursor)
- Expone persona_responsable / quien_respalda / telefono / lugar_exacto solo a owner o `iniciativas.moderate`
- Desbloquea edición F14/F21 sin endpoint admin dedicado

### [P36] Centros: municipio_id en API — 2026-08-17 (Cursor, TDD)
- Model/resource/filtro `?municipio_id=`; create/update aceptan municipio_id opcional
- Test `CentroMunicipioTest`

### [P35] Aporte opcional con `punto_acopio_id` — 2026-08-17 (Cursor + TDD Claude)
- Migration FK en `aportes`; validación exists scoped a la iniciativa; service + resource
- Tests `AportePuntoAcopioTest` (3 passed). Front ya envía el campo en aportar.

### [P34] Seed demo Quibdó + acopio Bogotá/Medellín — 2026-08-17 (Cursor)
- `techos-para-quibdo-acopio-remoto` publicada; 2 puntos; activa Chocó/Bogotá D.C./Antioquia + municipios
- `CUENTAS_DEMO.md` actualizado; Listo F8 en front

### [P33] Puntos de acopio remotos por iniciativa — 2026-08-17 (Cursor, TDD)
- Tabla `iniciativa_puntos_acopio` + model/relation; create/update sync `puntos_acopio[]`
- `IniciativaResource.puntos_acopio`; catálogo `?incluir_inactivos=1` (Bogotá/Medellín sin activar todo el país)
- Tests `IniciativaPuntosAcopioTest` (4 passed)
- **Listo front F6:** payload documentado en pendientes front

### [P22] Notificaciones moderador por municipio — 2026-08-16
- Canal `database`; `ModeratorNotificationService` (moderador del municipio + admins)
- Eventos: iniciativa → revisión; aporte confirmado
- Inbox: `GET /api/notifications`, mark read / mark-all-read; test `ModeratorNotificationsTest`

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

### [P27] `imagen_path` de Iniciativa ahora es URL absoluta — 2026-08-17 (Claude, TDD)
- `IniciativaResource::imagenUrl()` resuelve vía `Storage::disk(UploadDisk::name())->url(...)` (mismo disco S3/public de P23); si ya viene `http(s)://` (seed con link externo) no lo reprocesa.
- Test nuevo: `IniciativaApiTest::test_imagen_path_is_resolved_to_absolute_url` (rojo→verde confirmado).
- Suite completa corrida: 29 passed, 1 pre-existente falla (no relacionado, ver nota abajo).
- **Nota (no arreglado en este ciclo):** `GeoAndAportesRecepcionTest > creador lista aportantes anonimo y marca recepcion con evidencia` falla porque el contenedor `app` no tiene la extensión PHP `gd` (`imagejpeg` no definida) — bloquea cualquier test que use `UploadedFile::fake()->image(...)`. Encolado como nuevo pendiente.

### [P30] Falta extensión PHP `gd` en la imagen Docker — bloquea tests de upload de imagen
- **Repo:** convites (API)
- **Prioridad:** alta
- **Qué:** `docker/Dockerfile` no instala la extensión `gd` de PHP. Cualquier test que use `Illuminate\Http\UploadedFile::fake()->image(...)` (ej. evidencia de aportes, futuras imágenes de iniciativa) falla con `LogicException: imagejpeg function is not defined`. Agregar `gd` a las extensiones instaladas en el Dockerfile (`docker-php-ext-install gd` o el paquete equivalente de la imagen base) y rebuildear.
- **Hecho cuando:** `GeoAndAportesRecepcionTest::test_creador_lista_aportantes_anonimo_y_marca_recepcion_con_evidencia` pasa sin skip.
- **Añadido:** 2026-08-17
- **Por:** Claude

### [P29-Cursor] Demo profesional documentada + municipio_ids voluntario confirmado — 2026-08-17 (Claude, TDD)
- `docs/CUENTAS_DEMO.md`: documentado que `aportante1@convites.test` (Camila Restrepo) es el login para probar el perfil profesional demo (Laura Cardona, aprobada) — no existe rol `profesional` dedicado todavía (eso es el `[P29]` más grande, ver abajo).
- Confirmado con test nuevo (no existía cobertura de `/api/auth/me` antes): `AuthAndPermissionTest::test_me_expone_municipio_ids_del_voluntario_demo` — el voluntario demo YA tenía 3 municipios asignados en `DatabaseSeeder` (`$voluntario->municipiosAsignados()->sync(...)`), solo faltaba el test que lo blindara.
- Suite completa: 30 passed, 1 falla preexistente (`[P30]`, no relacionada).

### [P28] TDD notificaciones: unread filter, mark-read, mark-all-read — 2026-08-17 (Claude, TDD)
- 4 tests nuevos en `ModeratorNotificationsTest`: filtro `?unread=1`, `markRead` (propia + 404 en ajena), `markAllRead`.
- Contrato confirmado estable, sin cambios de código en `NotificationController` (ya cumplía): `data[]` + `meta.{current_page,last_page,total,unread_count}`.
- Suite completa: 34 passed, 1 falla preexistente (`[P30]`, gd).

### [P29] Rol "profesional" + panel propio /api/mi-perfil-profesional — 2026-08-17 (Claude, TDD)
- Nuevo rol `profesional` en `route_permissions.php` (permisos `profesional_perfil.view_own`/`update_own`) — se asigna ADEMÁS de `member` al registrar perfil profesional (`ProfesionalController::register`), nunca lo reemplaza.
- Endpoints nuevos: `GET/PUT /api/mi-perfil-profesional`, `GET /api/mi-perfil-profesional/solicitudes` — scopeados a `auth()->user()->profesional`, 404 si no tiene perfil.
- `UpdateMiPerfilProfesionalRequest`: whitelist explícita (titulo/celular/modalidad/disponibilidad/descripcion) — `estado`/`aprobado_at`/`revisado_por` quedan fuera a propósito (exclusivo del flujo de moderación).
- `ProfesionalSolicitudResource` nuevo.
- 7 tests nuevos en `MiPerfilProfesionalTest` (incluye: rol se asigna al registrar, 404 sin perfil, no puede tocar `estado`, no ve solicitudes ajenas).

### [P30] Listo también en dev: `Dockerfile.dev` le faltaba freetype/jpeg — 2026-08-17 (Claude)
- Cursor arregló `docker/Dockerfile` + `Dockerfile.dokploy` (prod) pero `docker-compose.yml` de dev apunta a `docker/Dockerfile.dev`, que seguía sin `freetype-dev`/`libjpeg-turbo-dev` ni `docker-php-ext-configure gd --with-freetype --with-jpeg` — por eso el test seguía en rojo en local.
- Agregado el mismo fix a `Dockerfile.dev`, rebuild + recreate del contenedor `app`.
- Suite completa: **42 passed, 0 failed** (primera vez sin la falla de `gd`).

### [P31] Upload real de certificados en registro profesional — 2026-08-17 (Claude, TDD)
- `RegisterProfesionalRequest`: campo `documentos` (array, max 5, `mimes:pdf,jpg,jpeg,png`, max 5MB c/u).
- `ProfesionalController::register`: guarda cada archivo en `UploadDisk` (mismo disco S3/public de P16/P23) bajo `profesionales/documentos/`, crea `ProfesionalDocumento` (disk/path/nombre_original/mime/tamanio/checksum sha256/uploaded_by).
- `ProfesionalResource.documentos`: array con `url` resuelta (Storage::disk), gateado igual que `celular`/`email` (dueño o moderador) — puede tener datos personales.
- 3 tests nuevos en `ProfesionalDocumentoUploadTest` (persiste en disco+BD, rechaza `.exe`, visibilidad dueño/moderador sí, tercero no).

### [P32] Seed: rol `profesional` al vincular user_id — 2026-08-17 (Claude, TDD)
- `DemoDataSeeder::seedProfesionales`: si el profesional demo tiene `user_id`, `assignRole('profesional')` (aditivo).
- Re-corrida `db:seed --class=DatabaseSeeder` completa en la BD real de dev — confirmado `aportante1@convites.test` con roles `member,profesional`. De paso confirmé que `[P14]` (catálogo Colombia) está vivo: 33 departamentos, 1122 municipios, 86 activos.
- Test de regresión: `MiPerfilProfesionalTest::test_demo_aportante1...` (seed completo real, login, `/api/mi-perfil-profesional`).
- Suite completa: **46 passed, 0 failed**.

### [P37] Gotcha config:cache + DB_HOST documentado — 2026-08-17 (Claude)
- `config:clear` corrido para desbloquear (config cacheado con `DB_HOST=127.0.0.1` ignoraba la env var de Docker).
- Nota agregada a `docs/README.md`: no correr `config:cache` en dev, o `config:clear` si pasa.

### [P43] Creador cierra/detiene su propio convite — 2026-08-17 (Claude, TDD)
- `POST /api/iniciativas/{id}/cerrar` nuevo — distinto del de moderación (`/moderacion/.../cerrar`, exclusivo `iniciativas.moderate`).
- `IniciativaPolicy::close` (owner o `canModerateIniciativa`); solo desde `publicada`/`en_curso`; registra `moderacion_acciones`.
- 4 tests `IniciativaCerrarPropiaTest` (owner cierra, ajeno 403, borrador 422, moderador también puede).
- Suite completa: 60 passed.

### [P44] Solicitudes profesional: PATCH estado + notas acumulables — 2026-08-17 (Claude, TDD)
- Enum `EstadoSolicitudProfesional` remapeado (sin datos existentes que migrar, verificado 0 filas): quita `respondida`/`cerrada` ambiguos, agrega `atendida`, `negada`, `trasladada`, `no_contesta`. Mantiene `pendiente`, `notificada`, `spam`.
- Migración `notas` (text nullable) en `profesional_solicitudes`.
- `PATCH /api/mi-perfil-profesional/solicitudes/{id}` — scoped al profesional del user (404 si es de otro); `estado` reemplaza, `nota` se **acumula** con fecha (no pisa lo anterior).
- `ProfesionalSolicitudResource` expone `estado_label` + `nota` (texto acumulado).
- 4 tests `SolicitudProfesionalEstadoTest`. Suite completa: 65 passed.

### [P39] Delete evidencia de aporte (owner/moderador) — 2026-08-17 (Claude, TDD)
- `DELETE /api/aportes/{id}/evidencia` — borra el archivo del disco + limpia los campos `evidencia_*`; el `estado` (cumplido/confirmado) NO cambia, es solo quitar el archivo (para volver a subir uno mejor).
- Reusa el mismo chequeo de autorización que `marcarRecepcion` (owner de la iniciativa o `canModerateIniciativa`).
- 2 tests `AporteEvidenciaDeleteTest`. Suite completa: 67 passed.
- **Decisión tomada:** solo "eliminar", no "reemplazar en un solo paso" — reemplazo = delete + `POST recepcion` de nuevo con la nueva evidencia (ya cubierto por el endpoint existente).

### [P42] Auth Google (Socialite + BFF con código de intercambio) — 2026-08-17 (Claude, TDD)
- `laravel/socialite` instalado. `config/services.google` + 4 vars nuevas en `.env.example` (`GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI/FRONTEND_CALLBACK_URL`, sin valores reales en repo).
- `GoogleAuthController`: `GET /api/auth/google/redirect` (JSON `{url}`), `GET /api/auth/google/callback` (procesa Socialite, busca por `google_id` → por `email` vincula cuenta existente → si no existe crea user nuevo con rol `member`; **el token NUNCA va en la URL** — genera un código de intercambio random de un solo uso, 60s TTL en cache, y redirige al front con ese código), `POST /api/auth/google/exchange` (canjea el código por `{token, user}`, lo borra de cache — un solo uso confirmado con test).
- Migración: `users.password` ahora nullable (cuentas solo-Google no tienen password).
- 5 tests `GoogleAuthTest` usando `Socialite::fake()` (redirect, crear nuevo, vincular por email, exchange de un solo uso, código inválido → 404).
- Suite completa: **72 passed**.
- **Nota:** no se pudo probar end-to-end contra los servidores reales de Google (sin credenciales) — el flujo está cubierto por tests con Socialite mockeado; falta un smoke manual con credenciales reales antes de producción.

### [directo] Correo de bienvenida al registrarse (job + mailtrap) — 2026-08-17 (Claude, TDD)
- `SendWelcomeEmailJob` (ShouldQueue) + `BienvenidaMail` + vista `emails/bienvenida.blade.php` — texto cálido, explica qué es Convites, agradece sumarse a las causas.
- Se dispara al crear cuenta (una sola vez): `AuthController::register` y `GoogleAuthController::callback` (solo rama "usuario nuevo", NO cuando se vincula una cuenta existente por email).
- `.env.example`: `MAIL_MAILER=smtp`, `MAIL_HOST=sandbox.smtp.mailtrap.io`, `MAIL_PORT=2525`, `MAIL_FROM_ADDRESS=hola@convites.co` — usuario/password de Mailtrap quedan vacíos, los completa el usuario cuando tenga las credenciales.
- 4 tests `WelcomeEmailTest` (encola en registro, encola en Google-nuevo, NO encola en Google-vinculación, el job realmente renderiza y envía el mail).
- Suite completa: 78 passed.

### [P46-1/3] SolicitudRol: modelo + endpoints ciudadano (crear/listar propias) — 2026-08-17 (Claude, TDD)
- Tablas `solicitudes_rol` + pivote `solicitud_rol_municipio`. Enums `TipoSolicitudRol` (moderador/voluntario, con `rolSpatie()` mapeando a los nombres reales del rol — ojo, `moderador` → Spatie `moderator`) y `EstadoSolicitudRol`.
- `POST /api/solicitudes-rol` (rechaza si ya tiene el rol o ya tiene una pendiente del mismo; exige ≥1 municipio) y `GET /api/mis-solicitudes-rol`.
- 6 tests `SolicitudRolCiudadanoTest`. Suite completa: 84 passed.
- **Falta (próxima parte):** endpoints admin (listar/aprobar/rechazar) y mover el `assignRole('profesional')` de `register()` a la aprobación.

### [P46-2/3] SolicitudRol: endpoints admin (listar/aprobar/rechazar) — 2026-08-17 (Claude, TDD)
- `GET /api/admin/solicitudes-rol` (filtro `estado`/`rol`, default `pendiente`), `POST .../aprobar` (asigna el rol Spatie real vía `rolSpatie()`, sincroniza municipios sin borrar los que ya tenía — `syncWithoutDetaching`), `POST .../rechazar` (exige `nota_revision`).
- 5 tests `AdminSolicitudRolTest` (listar+filtro, aprobar asigna rol+municipios+conserva member, rechazar con nota, rechazar sin nota falla, ciudadano no entra).
- Suite completa: 89 passed (hubo una corrida con 5 fallas transitorias por choque de concurrencia con el otro agente en la BD del host — confirmado no relacionado, reproducido limpio después).
- **Falta (próxima parte):** mover `assignRole('profesional')` de `register()` a la aprobación del perfil + actualizar tests existentes que asumían asignación inmediata.

### [P46-3/3] Profesional: rol se asigna al aprobar, no al registrar — 2026-08-17 (Claude, TDD)
- `ProfesionalController::register` ya no asigna el rol; `moderar()` lo asigna solo cuando `$nuevo === EstadoProfesional::Aprobado`.
- Reescritos 2 tests de `MiPerfilProfesionalTest` (registrar NO asigna, aprobar SÍ) + 1 nuevo (rechazar NO asigna) — usan el endpoint real de moderación, no un `update()` directo.
- Corregido `ProfesionalDocumentoUploadTest` (aprobaba el perfil "a mano" con `update()`, ahora también asigna el rol a mano ya que no pasa por el endpoint real).
- **`[P46]` completo (3/3).** Suite completa: **91 passed**.
