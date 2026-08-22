# API Bot / MCP — diseño (solo lectura, aislada)

Objetivo: que el agente de WhatsApp (n8n) y, más adelante, un **MCP server**
consulten datos vivos de Convites **sin tocar** las rutas públicas/autenticadas
actuales (`/api/iniciativas`, Sanctum, etc.).

Estado: **implementado** en Laravel (`/api/bot/v1`, middleware `bot.token`).

Env:
- `CONVITES_BOT_TOKEN` — Bearer obligatorio
- `CONVITES_BOT_FRONTEND_URL` (opcional) o `FRONTEND_URL` — links en respuestas
- n8n: `CONVITES_API_URL` — base de la API sin slash final

---

## Principios

1. **Fachada nueva** bajo `/api/bot/v1/*` — no se reutilizan Resources “gordos”
   del front; payloads mínimos pensados para el LLM.
2. **Solo lectura.** Cero POST/PUT/DELETE de dominio (aportes, moderación).
3. **Auth propia del bot**, no sesión de usuario: header
   `Authorization: Bearer {CONVITES_BOT_TOKEN}` (env). Token distinto de Sanctum.
4. **Sin PII de aportantes** ni de **profesionales**: la API bot **no** lista
   perfiles profesionales (nombres, contactos, áreas). Manos profesionales solo
   por el sitio. Tampoco email/celular de aportantes, notas privadas,
   `lugar_exacto` de verificación, ni aportes ajenos.
5. **Solo convites activos** (`publicada` | `en_curso`). Cerrados/borrador = 404
   o ausentes del listado.
6. **Misma fuente de verdad** que la app (Eloquent/servicios), no SQL ad-hoc
   desde n8n — así soft-deletes y `progreso_cache` no mienten.
7. **Rate limit** agresivo por token/IP (`throttle:bot`, p.ej. 60/min).
8. **Idempotente y cacheable** (Cache-Control corto o Redis 30–60s en listados).

```
WhatsApp → cola n8n → fachada agente
                ↓
         AI Agent + tools
                ↓
    GET /api/bot/v1/...  (+ Bearer bot)
                ↓
         Laravel (queries controladas)
                ↓
              MySQL

Más adelante el mismo contrato lo consume un MCP:
  tool "buscar_convites" → HTTP GET /api/bot/v1/convites?...
```

---

## Auth

| Header | Valor |
|--------|--------|
| `Authorization` | `Bearer <CONVITES_BOT_TOKEN>` |
| `Accept` | `application/json` |

Middleware: `bot.token` (compara token en `config/services.php` → `bot.token` /
env `CONVITES_BOT_TOKEN`). 401 si falta o no coincide.

No usar el token de un admin humano. Rotación sin redeploy de rutas.

---

## Endpoints (v1)

Base: `https://{API}/api/bot/v1`

### `GET /health`

Smoke para n8n. `{ "ok": true, "service": "convites-bot" }`

### `GET /convites`

Buscar convites **activos** (`publicada` | `en_curso`).

| Query | Tipo | Notas |
|-------|------|--------|
| `q` | string? | título/resumen |
| `municipio` | string? | nombre o slug |
| `departamento` | string? | nombre o slug |
| `urgencia` | `alta`\|`media`\|`baja`? | |
| `limit` | int 1–10, default 5 | |

Respuesta (ejemplo):

```json
{
  "data": [
    {
      "slug": "techos-para-quibdo",
      "titulo": "Techos para Quibdó",
      "resumen": "…",
      "urgencia": "alta",
      "progreso": 35,
      "municipio": "Quibdó",
      "departamento": "Chocó",
      "fecha_convite": "2026-09-01",
      "url": "https://convites.co/iniciativa/techos-para-quibdo",
      "faltantes_resumen": "cemento 40 bultos; hierro 20 varillas"
    }
  ],
  "meta": { "count": 1, "limit": 5 }
}
```

### `GET /convites/{slug}`

Detalle bot-safe: historia corta, items (nombre/unidad/meta/aportado/faltante),
puntos de acopio del convite (nombre, ciudad, dirección, horario — **sin**
teléfono de organizador si es PII sensible; contacto del punto sí si es del
punto público), proveedores solo nombre+ciudad+instrucciones (sin celular
privado si aplica política).

`url` absoluta al front.

404 si no está activa (`publicada` / `en_curso`).

### `GET /centros`

Centros **activos** (acopio, albergue, censo, etc.).

| Query | Tipo |
|-------|------|
| `tipo` | enum existente? |
| `municipio` | string? |
| `departamento` | string? |
| `solo_emergencia` | bool? |
| `limit` | 1–15, default 8 |

Incluye: nombre, tipo, estado, dirección, horario, teléfono **si está en el
registro público del centro**, necesita[], no_recibe[], municipio, url mapa
(`https://convites.co/centros` o deep-link si existe).

### Profesionales — **fuera de alcance del bot**

No hay `GET /profesionales` en `/api/bot/v1`. El agente explica el proceso y
remite a `https://convites.co/manos-profesionales`. El directorio público del
front (`/api/profesionales`) no se usa desde n8n.

### `GET /materiales` (opcional v1.1)

Necesidades abiertas agregadas (lo que hoy alimenta `/api/materiales`), slim.

---

## Qué NO va en esta API

- Aportantes / cancelar / recepción
- Admin, moderación, asignar creador
- Perfil de usuario, “mis aportes”
- Datos de profesionales / Manos profesionales
- Escritura de cualquier tipo
- SQL arbitrario / “query” param

Identidad WhatsApp → usuario (v2) sería **otro** prefijo `/api/bot/v1/me/*` con
verificación explícita; fuera de alcance v1.

---

## Contrato para tools (n8n / MCP)

| Tool name | HTTP | Cuándo usarla |
|-----------|------|----------------|
| `buscar_convites` | `GET /convites` | “¿hay convites en…?”, “qué puedo apoyar” |
| `detalle_convite` | `GET /convites/{slug}` | tras elegir uno; qué falta, dónde entregar |
| `buscar_centros` | `GET /centros` | acopio, albergue, censo, horarios **vivos** |

El MCP (futuro) solo envuelve estas 3 tools; no abre la BD.

En n8n v1 se usan **HTTP Request Tool** del Agent (mismo contrato). Cuando exista
MCP server, el Agent puede cambiar a MCP Client Tool sin cambiar Laravel.

---

## Impacto en el prompt de David

> Solo citas dirección, horario, estado o teléfono de centro/punto si **vinieron
> en la respuesta de una tool en este turno**. Si la tool falla o viene vacía,
> remites a https://convites.co/ sin inventar.

Manos profesionales: solo proceso + link; **cero** listados ni PII.

Anti prompt-injection: el mensaje del usuario llega delimitado
(`<<<USER_MESSAGE>>>…<<<END_USER_MESSAGE>>>`) desde Build Agent Input; el system
prompt trata ese bloque como datos, no como instrucciones.

---

## Observabilidad

- Log Laravel: `bot.v1` + tool path + status + latencia (sin body PII).
- n8n: ya tiene `formato_ok` / handoff; añadir en Normalize opcional
  `tools_used` si el agent lo expone.

---

## Compatibilidad

| Existente | Cambio |
|-----------|--------|
| `/api/iniciativas` | ninguno |
| `/api/profesionales` (público) | ninguno (bot no lo usa) |
| Sanctum / roles | ninguno |
| Front Next | ninguno |
| Migrations | ninguna para v1 (solo código + env) |
