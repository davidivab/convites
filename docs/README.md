# Flujo de mejoras (agentes)

## Objetivo

Ver [`OBJETIVO.md`](./OBJETIVO.md): todos los roles (admin, voluntario, moderador, ciudadano aportante/creador, profesional) con secciones definidas y flujos usables.

## Roles (sesión dual)

| Agente | Repo de trabajo | Lee/ejecuta | Escribe tareas en |
|--------|-----------------|-------------|-------------------|
| **Claude** | `convites` (API) | `convites/docs/pendientes.md` | ambos repos |
| **Cursor** | `convites-front` | `convites-front/docs/pendientes.md` | ambos repos |

IDs: API `P#`, front `F#`. Misma máquina = sync por archivos (sin commit obligatorio).

## Datos dummy

Trabajar contra **BD real**. Ver `CUENTAS_DEMO.md`.
Tras cambios de dominio: `php artisan db:seed --class=DemoDataSeeder`.

## Archivos

| Archivo | Uso |
|---------|-----|
| `OBJETIVO.md` | Meta de producto / roles |
| `pendientes.md` | Cola |
| `enproceso.md` | En curso |
| `finalizados.md` | Hecho |
| `CUENTAS_DEMO.md` | Logins demo |

## Cruce / dependencia

1. Si necesitas algo del otro: añade un pendiente en **su** repo con `// bloqueado: necesita …` o `**Depende de:** P#|F#`.
2. Cuando lo resuelvas para el otro, añade en **su** cola un ítem:
   ```md
   ### [Pxx|Fxx] Listo: <qué quedó disponible>
   - **Resuelve:** <ID del pendiente del otro>
   - **Qué:** contrato / rutas / shape JSON
   - **Prioridad:** alta
   ```
3. El otro lo lee, integra y mueve a finalizados.

## Loop + permisos

Cada **10 min × 5 h** (~30 ticks): leer cola propia → tomar alta prioridad → `enproceso` → implementar → `finalizados`.
**Autorizado sin pedir OK:** migraciones, seeders, tests, Docker en ambos repos.
Sin commit/push salvo pedido del usuario. No inventar trabajo si la cola está vacía (idle).
