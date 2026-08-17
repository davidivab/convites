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

### [P38] Admin list: búsqueda inteligente + contacto en index
- **Repo:** convites (API)
- **Prioridad:** alta
- **Qué:** `GET /api/admin/iniciativas` ya pagina y tiene `q` (LIKE titulo/resumen/slug). Mejorar búsqueda (mín. 3 chars, mismo motor que público si hay FULLTEXT). Exponer en **index** campos de contacto útiles para la tabla admin (teléfono/email creador o `verificacion` contact fields) y asegurar `progreso` (% evolución) siempre presente.
- **Hecho cuando:** Front F12 puede mostrar columnas contacto + % y buscar desde 3ª letra sin gaps.
- **Añadido:** 2026-08-17
- **Para front:** F12
- **Origen user:** #1
- **TDD**

### [P39] Evidencias aporte: delete/reemplazo (admin/mod/owner)
- **Repo:** convites (API)
- **Prioridad:** media
- **Qué:** Hoy recepción es `POST /api/aportes/{id}/recepcion`. Si el producto exige eliminar o reemplazar evidencias desde admin detalle, añadir endpoints (DELETE evidencia o replace) con Policy `canModerateIniciativa` / owner. Documentar contrato para F13.
- **Hecho cuando:** Tests + Listo F13 con delete evidencia si se implementa; si se decide “solo agregar”, anotar en finalizados y cancelar.
- **Añadido:** 2026-08-17
- **Para front:** F13
- **Origen user:** #2
- **TDD**

### [P42] Auth Google (Socialite + BFF callback)
- **Repo:** convites (API)
- **Prioridad:** media
- **Qué:** Columna `users.google_id` existe sin flujo. Implementar OAuth Google (Socialite): redirect + callback, crear/vincular user, sesión Sanctum usable desde front BFF. Documentar env (`GOOGLE_CLIENT_ID/SECRET`, redirect URI). Sin secretos en repo.
- **Hecho cuando:** Tests o smoke documentado; Listo F20.
- **Añadido:** 2026-08-17
- **Para front:** F20
- **Origen user:** #10
- **TDD** donde aplique

### [P44] Solicitudes profesional: PATCH estado + notas
- **Repo:** convites (API)
- **Prioridad:** alta
- **Qué:** Enum actual `pendiente|notificada|respondida|cerrada|spam`. Producto pide estados operativos: **atendido, negado, trasladado, no_contesta** (+ notas). Migración `notas` (text nullable); extender/remapear enum con cuidado (migrar valores viejos); `PATCH /api/mi-perfil-profesional/solicitudes/{id}` scoped al profesional del user. Resource expone `notas` + `estado`.
- **Hecho cuando:** Tests + Listo F23.
- **Añadido:** 2026-08-17
- **Para front:** F23
- **Origen user:** #14
- **TDD**
- **Demo:** `aportante1@convites.test` (Laura Cardona)
