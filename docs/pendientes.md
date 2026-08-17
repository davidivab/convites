# Pendientes — API Convites

Cola de mejoras. Claude ejecuta aquí; Cursor puede añadir ítems para el API.

## Formato

```md
### [P#] Título corto
- **Repo:** convites (API)
- **Prioridad:** alta | media | baja
- **Qué:** …
- **Hecho cuando:** …
- **Añadido:** YYYY-MM-DD
```

---

## Cola

### [P46] Rediseño de roles: solo admin + ciudadano — moderador/voluntario/profesional se SOLICITAN, admin aprueba
- **Repo:** convites (API)
- **Prioridad:** alta
- **Contexto (pedido directo del usuario, cambia el modelo de dominio de P19-P21/P29):**
  1. Los únicos tipos de cuenta reales son **admin** y **ciudadano**. `member` (registro público) ES el "ciudadano" — esto ya funciona así hoy, no cambia.
  2. `moderator` y `voluntario` dejan de crearse por el admin desde cero (`AdminUserController::store` hoy crea el USER completo con email/password — eso queda obsoleto para estos 2 roles). Ahora: un ciudadano YA REGISTRADO solicita el rol, el admin aprueba o rechaza.
  3. Un ciudadano puede solicitar `moderador`, `voluntario` y `profesional` — puede tener más de uno (`o ambos`), cada solicitud es independiente.
  4. `profesional` YA tiene un formulario de solicitud (`POST /api/profesionales`, con su propio flujo de moderación aprobar/rechazar/cambios) — el problema es que HOY el rol `profesional` se asigna en el momento de registrar (`ProfesionalController::register`, línea con `$user->assignRole('profesional')`), **antes** de que el admin/moderador apruebe el perfil. Con la nueva lógica, el rol debe asignarse recién cuando se **aprueba** el perfil (mover el `assignRole` de `register()` a `moderar()`/`aprobar()`).
  5. `moderador` y `voluntario` necesitan un mecanismo de solicitud NUEVO (no existe hoy) — no reusar el modelo `Profesional` (son conceptos distintos, no tiene sentido un "perfil profesional" para moderador).
- **Qué:**
  1. Nuevo modelo/migración `SolicitudRol` (tabla `solicitudes_rol`): `user_id`, `rol` (string: `moderador`|`voluntario` — un registro por rol solicitado, no un array), `mensaje` (text nullable, motivación del ciudadano), `estado` (`pendiente`|`aprobada`|`rechazada`), `nota_revision` (text nullable), `revisado_por` (FK User nullable), `revisado_at` (timestamp nullable), timestamps. Tabla pivote `solicitud_rol_municipio` para los municipios que el ciudadano quiere cubrir (obligatorio para ambos roles, ya que los dos requieren `municipiosAsignados`).
  2. Endpoints ciudadano: `POST /api/solicitudes-rol` (`{ rol, municipio_ids[], mensaje? }` — rechazar si ya tiene ese rol asignado, o ya tiene una solicitud `pendiente` del mismo rol — un ciudadano puede tener solicitudes de moderador Y voluntario en paralelo, sin problema), `GET /api/mis-solicitudes-rol` (ve el estado de las suyas).
  3. Endpoints admin: `GET /api/admin/solicitudes-rol` (filtro por `estado`/`rol`), `POST /api/admin/solicitudes-rol/{id}/aprobar` (asigna el rol — aditivo, `assignRole`, nunca reemplaza otros — y sincroniza `municipiosAsignados` con los municipios pedidos), `POST /api/admin/solicitudes-rol/{id}/rechazar` (requiere `nota_revision`).
  4. `ProfesionalController::register`: quitar el `assignRole('profesional')` de ahí. Agregarlo en el método `moderar()` (o específicamente en `aprobar()`) del mismo controller, cuando `$nuevo === EstadoProfesional::Aprobado`. **Actualizar los tests existentes** de `P29`/`P31`/`MiPerfilProfesionalTest` que asumían el rol asignado en el registro — ahora deben crear el profesional y aprobarlo antes de poder usar `/api/mi-perfil-profesional`.
  5. **Decisión (producto):** `AdminUserController::store` se mantiene **solo para crear otro `admin`** (422 si se pide `moderator`/`voluntario`/`profesional`). Motivo: no hay flujo de “solicitud de admin”; alguien debe poder crear el siguiente admin. Moderador/voluntario solo vía `solicitudes_rol`.
- **Hecho cuando:** un `member` puede solicitar moderador y/o voluntario con municipios; el admin ve la cola y aprueba/rechaza; al aprobar, el ciudadano tiene el rol + municipios sin que el admin haya creado ninguna cuenta nueva; un profesional NO tiene el rol `profesional` hasta que su perfil es aprobado (no al registrar); tests actualizados y verdes.
- **Añadido:** 2026-08-17
- **Origen:** pedido directo del usuario (redefine P19-P21/P29)
- **TDD**
- **Para front:** ver `[F29]` en `convites-front/docs/pendientes.md`

### [P46] ← Front (Cursor) necesita esto para cablear F29
- **Estado front:** esqueleto F29/F30 en marcha; UI llama estos endpoints — **sin ellos la cola admin y el “Solicitar” fallan en runtime**.
- **Contrato esperado (no inventar otros paths):**
  1. `POST /api/solicitudes-rol` body `{ rol: "moderador"|"voluntario", municipio_ids: number[], mensaje?: string }` → 201 + resource
  2. `GET /api/mis-solicitudes-rol` → lista del user (incl. `estado`, `nota_revision`, `municipios[]`)
  3. `GET /api/admin/solicitudes-rol?estado=&rol=` → lista admin
  4. `POST /api/admin/solicitudes-rol/{id}/aprobar`
  5. `POST /api/admin/solicitudes-rol/{id}/rechazar` body `{ nota_revision: string }`
  6. Resource mínimo: `{ id, rol, estado, mensaje, nota_revision, municipios: [{id,nombre}], user?: {id,name,email}, created_at, revisado_at }`
  7. Rol Spatie al aprobar: `moderator` (no `moderador`) y `voluntario`; profesional solo al **aprobar** perfil (no en register)
  8. `POST /api/admin/users` solo `role: admin` (422 para moderator/voluntario)
- **Cuando esté Listo:** anotar en finalizados API + una línea “Listo F29” para que Cursor conecte smoke.
- **Nota Cursor (tick 2):** rutas admin ya en `route:list` + tests SolicitudRol **11 passed**. Front quitó banners “esperando P46”. Smoke E2E pendiente de API local up. Aún falta P46-3 (profesional assignRole al aprobar).

---

### [P47] Google OAuth: separar intención login vs registro (no crear cuenta al loguearse si no existe)
- **Repo:** convites (API)
- **Prioridad:** alta
- **Contexto (pedido directo del usuario, revisa `P42`):** hoy `GoogleAuthController::callback` SIEMPRE crea una cuenta nueva si no encuentra el `google_id`/email — eso pasa tanto si el botón de Google se clickeó en `/ingresar` como en `/registrarse`. El usuario quiere: si alguien usa "Ingresar con Google" en `/ingresar` y **no está ya registrado**, NO crear la cuenta ahí mismo — mandarlo a `/registrarse` para que complete los datos obligatorios (celular, términos, etc.), pudiendo reusar el email/nombre que ya confirmó Google.
- **Qué:**
  1. `GET /api/auth/google/redirect` acepta querystring `?intent=login|register` (default `login`). Pasar el intent a través del round-trip OAuth vía el parámetro `state` de Socialite (`->with(['state' => $intent])` en `stateless()`), no inventar otro mecanismo.
  2. `GET /api/auth/google/callback`: leer `state` como intent.
     - Si el `google_id` o el `email` YA matchea un user existente → comportamiento actual sin cambios (login normal, vincula si hace falta, código de intercambio de un solo uso vía `exchange` como ya está).
     - Si NO existe usuario (sea intent `login` o `register`): **no crear la cuenta todavía**. Guardar el perfil de Google (`google_id`, `email`, `name`) en cache bajo un código temporal (mismo patrón de TTL corto que ya existe para el exchange, pero separado — ej. `google-pending:{code}`, ~10 min), y redirigir al front con `?code=...&needs_registration=1`.
  3. Endpoint nuevo `POST /api/auth/google/completar-registro`: body `{ code, celular, acepta_terminos: bool, acepta_descargo: bool, ...otros campos opcionales que ya acepta /api/auth/register para completar perfil }`. Valida el `code` contra el cache pendiente (404 si no existe/expiró), crea el `User` recién ahí (con `password: null`, `google_id`, `email_verified_at: now()`, `acepta_terminos_at`/`acepta_descargo_at`), asigna rol `member`, dispara `SendWelcomeEmailJob` (mismo criterio que registro nuevo — no duplicar si ya se envió), emite token Sanctum, responde igual shape que `exchange` (`{ token, user }`).
  4. El endpoint `exchange` actual NO cambia — sigue sirviendo solo para el caso "ya había cuenta, login exitoso".
- **Hecho cuando:** click en Google desde `/ingresar` sin cuenta previa → no crea nada, el front recibe la señal de "falta registro"; completar el formulario en `/registrarse` con ese código sí crea la cuenta (sin pedir password, ya viene verificado por Google); tests con `Socialite::fake()` cubriendo los 3 casos (existe → login directo, no existe intent login → pending, completar-registro → crea cuenta).
- **Añadido:** 2026-08-17
- **Origen:** pedido directo del usuario (revisa `P42`)
- **TDD**
- **Para front:** ver `[F31]` en `convites-front/docs/pendientes.md`
