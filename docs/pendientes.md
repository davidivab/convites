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

### [P42] Auth Google (Socialite + BFF callback)
- **Repo:** convites (API)
- **Prioridad:** media
- **Qué:** Columna `users.google_id` existe sin flujo. Implementar OAuth Google (Socialite): redirect + callback, crear/vincular user, sesión Sanctum usable desde front BFF. Documentar env (`GOOGLE_CLIENT_ID/SECRET`, redirect URI). Sin secretos en repo.
- **Hecho cuando:** Tests o smoke documentado; Listo F20.
- **Añadido:** 2026-08-17
- **Para front:** F20
- **Origen user:** #10
- **TDD** donde aplique

