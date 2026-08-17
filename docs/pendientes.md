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

### [P33] Epic: puntos de acopio remotos por iniciativa (modelo + API)
- **Repo:** convites (API)
- **Prioridad:** alta
- **Contexto:** Hoy un convite tiene un solo `municipio_id` (territorio beneficiario / lugar del convite) y un `lugar_convite`/`lugar_exacto`. Los `centros` del catálogo **no** se vinculan a iniciativas. Caso real: ayuda para un municipio de Chocó con recolección en Bogotá y Medellín.
- **Decisión de dominio (acordada Cursor→ambos):**
  1. `iniciativas.municipio_id` sigue siendo el **destino / territorio del convite** (moderation + notificaciones siguen scoped ahí).
  2. Nueva tabla hija `iniciativa_puntos_acopio` (N por iniciativa), **no** exigir FK a `centros` (el catálogo global es otra cosa).
  3. Cada punto: `municipio_id` propio (puede ser otra ciudad), `nombre`, `direccion`, `horario` nullable, `contacto` nullable, `notas` nullable, `orden`, `lat`/`lng` opcionales, `centro_id` nullable (si se elige uno del catálogo).
  4. Municipios de acopio pueden estar `activo=false` en el catálogo UI general: validar con `exists:municipios,id` (no exigir `activo`) para puntos.
  5. Recepción de aportes **no** cambia en P33 (sigue confirmado→cumplido); el punto es informativo + selección opcional en aporte (P35).
- **Qué:** migración + model `IniciativaPuntoAcopio` + relación `Iniciativa::puntosAcopio()`; create/update de iniciativa aceptan `puntos_acopio[]` (máx razonable, p.ej. 20); `IniciativaResource` expone `puntos_acopio` con municipio anidado; TDD.
- **Hecho cuando:** tests crean iniciativa Chocó + 2 puntos (Bogotá, Medellín); GET detalle los trae; update reemplaza/sync la lista; sin romper create actual sin puntos.
- **Añadido:** 2026-08-17
- **Por:** Cursor (epic compartido; Claude puede tomarlo)

### [P34] Seed demo: convite Chocó con acopio Bogotá + Medellín
- **Repo:** convites (API)
- **Prioridad:** alta
- **Depende de:** P33
- **Qué:** en `DemoDataSeeder`, al menos 1 iniciativa publicada cuyo `municipio_id` sea de Chocó (o crear municipio activo si hace falta) con ≥2 `iniciativa_puntos_acopio` en Bogotá D.C. y Medellín. Activar esos municipios en catálogo **o** documentar que puntos usan `exists` sin `activo`. Actualizar `CUENTAS_DEMO.md` con el slug/título del convite demo.
- **Hecho cuando:** `db:seed --class=DemoDataSeeder` deja el caso usable; Listo en front pendientes para F6/F7.
- **Añadido:** 2026-08-17
- **Por:** Cursor

### [P35] Aporte opcional: elegir punto de acopio
- **Repo:** convites (API)
- **Prioridad:** media
- **Depende de:** P33
- **Qué:** `aportes.punto_acopio_id` nullable FK → `iniciativa_puntos_acopio` (mismo iniciativa_id que el aporte). Validar en `POST .../aportes`. Exponer en resource de aporte/aportantes. No obliga punto (compat).
- **Hecho cuando:** test aporta con punto válido; rechaza punto de otra iniciativa; front puede mostrar cuál eligió el aportante.
- **Añadido:** 2026-08-17
- **Por:** Cursor

### [P36] Centros catálogo: exponer `municipio_id` (higiene)
- **Repo:** convites (API)
- **Prioridad:** baja
- **Qué:** la columna `centros.municipio_id` existe pero el model/resource/API no la usan. Fillable + relation + resource; filtros `?municipio_id=` en index. No sustituye P33 (puntos por iniciativa).
- **Hecho cuando:** GET centros incluye municipio; filtro funciona; tests.
- **Añadido:** 2026-08-17
- **Por:** Cursor
