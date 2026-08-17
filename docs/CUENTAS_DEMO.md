# Cuentas y datos dummy (local)

Password de todas: `password`

## Roles

| Email | Rol | Para probar |
|-------|-----|-------------|
| `admin@convites.test` | admin | Todo + `/admin` (crear moderadores/voluntarios) |
| `moderator@convites.test` | moderator | Cola moderación (solo municipios de Risaralda) |
| `voluntario@convites.test` | voluntario | Cuenta territorial (sin moderar) |
| `member@convites.test` | member | Creador principal (varias iniciativas) |
| `creador2@convites.test` | member | Otro creador (borrador + escuela) |
| `aportante1@convites.test` … `aportante8@convites.test` | member | Aportes, asistencia, paneles |

**Nota P19:** `voluntario` es un rol **nuevo** (no es `member` renombrado). Se crea solo vía admin API / panel `/admin`.

**Nota P29 (demo profesional):** todavía no existe un rol `profesional` dedicado (ver `[P29]` "Rol y panel propio para profesional" en pendientes) — hasta que exista, `aportante1@convites.test` (Camila Restrepo) es el `user_id` vinculado al `Profesional` demo **Laura Cardona** (`laura.cardona@convites.test`, área Psicología, **aprobado**). Usar ese login para probar la experiencia de "tengo un perfil profesional asociado a mi cuenta". Los demás profesionales demo (Andrés Villegas, Diana Ospina, Julián Castaño, Natalia Duque) NO tienen `user_id` — son solo registros de catálogo, sin cuenta para loguearse.

## Qué hay en BD (DemoDataSeeder)

- Iniciativas en **todos** los estados: borrador, en_revisión, publicada, en_curso, cerrada, rechazada
- **Aportes reales** (confirmado / cumplido / cancelado) con ítems → progreso recalculado
- Centros de acopio / albergue / emergencia
- **P34:** iniciativa publicada `techos-para-quibdo-acopio-remoto` (destino Quibdó) con puntos en Bogotá y Medellín
- Profesionales: aprobados + pendiente + cambios solicitados
- Solicitudes de contacto a profesional
- Bitácora de moderación

## Regenerar

```bash
cd convites
php artisan db:seed --class=DatabaseSeeder
# o solo demo (idempotente si catálogos/users ya existen):
php artisan db:seed --class=DemoDataSeeder
```

No hace falta `migrate:fresh` salvo que quieras DB limpia.
