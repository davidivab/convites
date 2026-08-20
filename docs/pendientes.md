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

### [P55] Selector de rango + crop/transcode server-side para video de avances
- **Repo:** convites (API) + convites-front
- **Prioridad:** media
- **Depende de / sigue a:** P54 (introduce el media tipo `video` en `iniciativa_avance_media`; P54 se entrega primero solo con video completo ≤120s/50MB, sin recorte).
- **Qué:** hoy P54 exige que el video ya venga ≤120s; muchas personas no saben editar video antes de subirlo. Agregar, estilo WhatsApp: el **front** muestra una franja/slider sobre el `<video>` para elegir el rango (inicio/fin) sin editar nada del lado del cliente; se sube el archivo original + los dos timestamps elegidos; el **backend** hace el crop y transcodifica a un formato estándar/eficiente (mp4 H.264, bitrate/resolución acotados) antes de guardarlo como `iniciativa_avance_media`.
- **Bloqueadores de infra a resolver en propuesta/diseño de P55 (no asumir nada, decidir ahí):**
  - `ffmpeg` no está instalado en el proyecto (verificado 2026-08-20); decidir binario del sistema vs librería PHP (ej. `php-ffmpeg/php-ffmpeg`) vs servicio externo.
  - Queue driver actual es `database`; transcodificar es CPU-intensivo — decidir si alcanza así o si necesita otra queue/worker dedicado, y timeout del job.
  - Storage: ¿se conserva el archivo original subido o se descarta tras el crop? ¿Dónde vive mientras se procesa (temp vs disco definitivo)?
  - Mientras el video procesa, el avance queda en algún estado intermedio (¿"procesando", no publicable hasta terminar?) — definir UX de esa espera.
- **Hecho cuando:** el usuario puede recortar visualmente el rango antes de subir, el backend entrega siempre un video ya recortado/estandarizado en `iniciativa_avance_media`, sin regresar a exigir que el usuario edite el archivo por su cuenta.
- **Añadido:** 2026-08-20

### [P54] Avances de convite + uuid en iniciativas
- **Repo:** convites (API)
- **Prioridad:** alta
- **Resuelve / alinea con:** F41 (front en curso). Producto: reportes de avance (general o por ítem) con media, link y notificación opcional a aportantes.
- **Prod-safe:** migraciones **aditivas** solamente. Sin truncate/seed destructivo / `migrate:fresh`. Mismo criterio que P52.

#### 1) UUID en `iniciativas`
- Columna `uuid` CHAR(36) (o tipo uuid), **unique**, not null tras backfill.
- Backfill idempotente de filas existentes (`Str::uuid()`).
- En `creating`, asignar uuid si falta. **No** cambiar PK `id`.
- Exponer `uuid` en `IniciativaResource` (y admin show).
- Las URLs del **front** siguen usando `slug`. Solo la familia de endpoints de **avances** identifica la iniciativa por `uuid` en esta entrega (no reescribir todos los endpoints legacy).

#### 1b) Slug único de iniciativa (ya hay lógica; endurecer + reusar)
- Hoy `IniciativaController::uniqueSlug()` ya hace: base `Str::slug(titulo)` → si existe → `base-1`, `base-2`, … (incluye soft-deleted).
- **Pedir:** extraer a helper/servicio reutilizable; **TDD** que cubra duplicados consecutivos (`casa` → `casa`, segundo `casa` → `casa-1`, tercero → `casa-2`).
- Mismo helper para **slug de avance** dentro de la iniciativa (`unique` por `(iniciativa_id, slug)`): título → slug; si choca, `-1`, `-2`, …
- Al crear iniciativa seguir usando este helper (no aceptar slug crudo del client que pueda chocar).

#### 2) Tablas
**`iniciativa_avances`**
- `iniciativa_id` FK cascade
- `iniciativa_item_id` nullable FK nullOnDelete (null = avance general)
- `user_id` autor
- `slug` string; unique compuesto `(iniciativa_id, slug)`
- `titulo` string
- `cuerpo` text nullable
- `porcentaje` unsignedTinyInteger nullable (0–100; solo si hay item)
- `enlace_externo` string nullable URL
- `notificar_aportantes` bool default false
- `notificado_at` nullable
- `publicado_at` nullable (null = borrador)
- timestamps + index `(iniciativa_id, publicado_at)`

**`iniciativa_avance_media`**
- `iniciativa_avance_id` FK cascade
- `path`, `tipo` enum/string `imagen|video`, `orden`, `ancho`/`alto` nullable, `duracion_segundos` nullable
- Mirror de `iniciativa_galeria` (disk `UploadDisk`)

#### 3) Permisos Spatie (`RolesAndPermissionsSeeder` + `config/route_permissions.php`)
| Permission | Roles |
|---|---|
| `iniciativas.avances.view` | member, voluntario, moderator, profesional (+ admin all) |
| `iniciativas.avances.manage` | member, voluntario, moderator (+ admin all) |

Mutaciones: middleware `permission:iniciativas.update_own|iniciativas.moderate` **y** policy `update` de la iniciativa (owner / moderador con municipio / admin). Lectura pública de publicados: **sin** auth.

#### 4) Endpoints (iniciativa por **uuid**)
Binding `{iniciativa_uuid}` → `Iniciativa::where('uuid', $value)->firstOrFail()`.

| Método | Ruta | Auth |
|---|---|---|
| GET | `/api/iniciativas/{iniciativa_uuid}/avances?limit=&page=` | público; solo `publicado_at` not null; orden `publicado_at desc` |
| GET | `/api/iniciativas/{iniciativa_uuid}/avances/{avanceSlug}` | público |
| POST | `/api/iniciativas/{iniciativa_uuid}/avances` | auth + policy |
| PATCH | `/api/iniciativas/{iniciativa_uuid}/avances/{id}` | auth + policy |
| DELETE | `/api/iniciativas/{iniciativa_uuid}/avances/{id}` | auth + policy |
| POST | `/api/iniciativas/{iniciativa_uuid}/avances/{id}/media` | multipart `archivo` |
| DELETE | `.../media/{mediaId}` | auth + policy |

**Payload create/update:**
```json
{
  "titulo": "...",
  "cuerpo": "...",
  "tipo": "general|item",
  "iniciativa_item_id": null,
  "porcentaje": null,
  "enlace_externo": "https://...",
  "notificar_aportantes": false,
  "publicado": true
}
```
- `tipo=item` → `iniciativa_item_id` required (de esa iniciativa) + `porcentaje` 0–100 required.
- `tipo=general` → item y porcentaje null.
- **Importante:** el % del ítem es **solo narrativa** del reporte; **no** mutar `cantidad_aportada` / `progreso_cache`.

**Media:** image max 5MB; video MIME + `duracion_segundos ≤ 120` (rechazar 422 si > 2 min). Fotos como galería.

**Job:** `SendAvanceAportantesJob` — si `notificar_aportantes=true` al publicar y `notificado_at` null: emails distintos de aportes `confirmado|cumplido`; set `notificado_at`. Mailable al estilo `AporteAprobadoMail`.

**Resource:** `id`, `slug`, `titulo`, `cuerpo`, `tipo`, `item` resumido, `porcentaje`, `enlace_externo`, `media[]`, `autor` (nombre), `publicado_at`, `created_at`.

#### 5) TDD
- uuid backfill + unique; create/list/show por uuid; 404 uuid inválido
- validation general vs item; video >120s → 422
- notify job una sola vez; policy 403
- **slug único:** dos creates con mismo título → segundo termina en `-1`; tercer create → `-2`; avance slug colisiona → `-1` dentro de la misma iniciativa

- **Hecho cuando:** front F41 puede listar/crear avances con `ini.uuid`; migrate en local/prod sin tocar datos de usuarios/aportes; suite verde.
- **Añadido:** 2026-08-20
- **Nota Cursor:** front URLs `/iniciativa/{slug}/avances[...]`; API siempre `{uuid}`.
- **Nota slug (David):** si el slug base está ocupado, sufijo `-1`, `-2`, `-3`… (ya existe en create; endurecer con tests + reusar en avances).