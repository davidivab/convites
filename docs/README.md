# Flujo de mejoras (agentes)

## Datos dummy (obligatorio para probar flujos)

Trabajar contra **BD real**, no mocks del front. Ver `CUENTAS_DEMO.md`.
Tras cambios de dominio: `php artisan db:seed --class=DemoDataSeeder`.

## Archivos

| Archivo | Uso |
|---------|-----|
| `pendientes.md` | Cola: el otro Claude **añade** opciones aquí |
| `enproceso.md` | Lo que el agente de esta sesión está haciendo ahora |
| `finalizados.md` | Hecho (con fecha) |
| `CUENTAS_DEMO.md` | Logins + qué cubre el seeder |

Repos: `convites/docs/` (API) y `convites-front/docs/` (front).

## Reglas del agente trabajador

1. Cada ~10 min durante **5 horas**: leer `pendientes.md` en ambos repos (~30 ciclos).
2. Si hay ítems y `enproceso.md` tiene hueco: tomar el de mayor prioridad, moverlo a `enproceso.md`, implementarlo.
3. Al terminar: mover a `finalizados.md` con nota corta; dejar `enproceso.md` limpio o con el siguiente.
4. No commits ni push salvo que el usuario lo pida.
5. Preferir cambios pequeños y usabilidad (front) / API estable (backend).
6. Si un ítem es ambiguo o riesgoso (borrar datos, breaking API): dejarlo en pendientes con comentario `// bloqueado: necesita decisión` y seguir con otro.
7. **Dummy en BD**: si un flujo no se puede probar, ampliar `DemoDataSeeder` y reseedea — no inventar datos solo en el front.

## Cómo autorizar al agente (Cursor)

Para que trabaje sin pedirte OK en cada comando mientras no estás:

1. **Settings → Cursor Settings → Agents** (o Features → Agent)
2. Activá **Auto-run** / **Auto-approve** (a veces llamado *YOLO* o *Run everything*)
3. Opcional: allowlist de comandos seguros (`npm`, `php artisan`, `git status`, etc.)
4. Dejá **esta chat abierta** (el loop cada 10 min necesita la sesión activa)
5. El otro Claude puede escribir en `docs/pendientes.md` en otra terminal/chat

Si Cursor pide aprobación de sandbox/red: elegí “Always allow” para este workspace.
