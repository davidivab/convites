# Objetivo de producto (agentes)

Cada rol de la plataforma debe tener **secciones claras** y poder **usar el flujo completo** sin fricción:

| Rol | Árbol / superficies |
|-----|---------------------|
| **Admin** | `/admin/**` — usuarios, auditoría de convites, visión global |
| **Moderador** | `/moderacion/**` — cola scoped por municipio + notificaciones |
| **Voluntario** | `/panel/**` + municipios asignados (participación; sin moderar) |
| **Ciudadano** (aportante / creador) | `/panel/aportante`, `/panel/creador`, `/crear`, `/perfil`, aportar |
| **Profesional** | registro, perfil profesional, solicitudes de contacto |

## Dual agentes

- **Claude** → API (`convites`)
- **Cursor** → front (`convites-front`)
- Si uno se queda corto al revisar/probar: escribe pendiente en el repo del otro.
- **Autorizado:** migraciones, seeders, tests, Docker (`docker compose`) en ambos repos sin pedir OK cada vez.
- Sin commit/push salvo que el usuario lo pida.

## Epic activo: puntos de acopio remotos (P33–P36 / F6–F8)

Un convite tiene **un municipio destino** (territorio / moderación) y puede tener **N puntos de recolección en otras ciudades** (ej. ayuda para Chocó, acopio en Bogotá y Medellín). Ver `pendientes.md` P33+.

Ver `docs/README.md` en cada repo para IDs `P#`/`F#` y formato `Resuelve:`.
