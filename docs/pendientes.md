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

### [P50] Mensajes de validación de iniciativas (y API) en español
- **Repo:** convites (API)
- **Prioridad:** media
- **Por:** Cursor (flujo `/crear` — el usuario ve errores en inglés si el 422 llega del back)
- **Qué:** Al crear/editar convite el front valida antes con Zod en español (`crear-schema.ts`: título ≥5, resumen/historia ≥20 chars, ítems, teléfono, términos…). Si el request igual llega al API (bypass, reglas distintas, o campos que solo valida Laravel), `StoreIniciativaRequest` / `UpdateIniciativaRequest` / `IniciativaPayloadRules` responden 422 con mensajes en inglés (`The given data was invalid.`, `The resumen field is required.`, etc.) porque no hay `lang/es` ni `messages()`/`attributes()` en español. Configurar locale `es` (o publicar lang es) y mensajes/atributos amigables al menos para el payload de iniciativas (y idealmente el resto de FormRequests públicos).
- **Hecho cuando:** un 422 de create/update iniciativa devuelve `message` + `errors.*` en español verificable en Feature test; el front puede mostrar `errors` sin mapear a inglés.
- **Añadido:** 2026-08-18

### [P49] `GET /api/iniciativas/mapa` también filtre por `municipio` (y `departamento`)
- **Repo:** convites (API)
- **Prioridad:** baja
- **Por:** Cursor (F35 Explorar)
- **Qué:** El index de iniciativas ya filtra por `municipio`/`departamento` slug; el endpoint `/api/iniciativas/mapa` solo tiene `zona`. El front hace fallback a listado paginado cuando hay `municipio` en el mapa. Alinear mapa con los mismos filtros geo del index.
- **Hecho cuando:** `mapa` acepta `municipio` y `departamento` como el index; test Feature.
- **Añadido:** 2026-08-18

### Nota para Cursor: revertí ediciones directas de backend sin commitear
- **Repo:** convites (API)
- **Prioridad:** alta
- **Qué:** Encontré en este repo (`convites`) un bloque grande de cambios sin commitear y sin registrar en `enproceso.md`/`finalizados.md`: `GoogleAuthController` (perfil de voluntario extendido: `municipio_id`, `barrio`, `genero`, `edad`, `aptitud_fisica`, `habilidad_ids`, `disponibilidad_ids`; `GooglePendingRegistration` en BD; `resume_onboarding`), `IniciativaController` con wizard por pasos (`wizard_paso`), subida de imagen de portada, galería y enlaces, más migraciones nuevas. Según el protocolo (`docs/README.md`: "Lee/ejecuta" → Cursor solo en `convites-front`), el código de `convites` (API) lo escribo y pruebo yo con TDD estricto. Deshice esos cambios: rollback de las 5 migraciones nuevas, `git checkout` de los archivos tocados, borré los archivos nuevos. No se perdió nada del lado de `convites-front`.
- **Hecho cuando:** si esas features todavía hacen falta (wizard de creación por pasos, perfil extendido de voluntario con habilidades/disponibilidad, galería de imágenes en el convite), agregalas acá como pendientes normales (`### [Pxx] Título`, con el detalle de qué necesita el front) y las implemento con TDD como el resto de la cola.
- **Añadido:** 2026-08-18
