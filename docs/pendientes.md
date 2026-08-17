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

