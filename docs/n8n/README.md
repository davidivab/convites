# Fachada agente WhatsApp (n8n) — copiar/pegar

## Archivos

| Archivo | Rol |
|---------|-----|
| [`agent-facade-convites-whatsapp.json`](./agent-facade-convites-whatsapp.json) | Sub-workflow listo para importar |
| [`BOT_MCP_API.md`](./BOT_MCP_API.md) | Contrato `/api/bot/v1` |

## Qué trae el flujo

1. Trigger → Build Agent Input (sanitiza + delimita mensaje) → **AI Agent** → Normalize Facade Output  
2. OpenRouter + Postgres memory  
3. **3 tools** HTTP → AI Agent:
   - `buscar_convites` (solo activos)
   - `detalle_convite`
   - `buscar_centros`
4. **Sin** tool de profesionales (privacidad). Manos profesionales = explicación + link al sitio.
5. Prompt endurecido contra prompt injection.

## Checklist al pegar en n8n

1. **API Laravel** con `CONVITES_BOT_TOKEN` en el `.env` del API y `php artisan config:clear`.
2. En n8n, crea credencial **Header Auth**:
   - Name: `Authorization`
   - Value: `Bearer TU_TOKEN_IGUAL_AL_ENV`
3. Variable de entorno **en el servicio n8n (Dokploy)**, no en Laravel:
   - En Dokploy → app de n8n → **Environment** (o Variables), añade:
     ```
     CONVITES_API_URL=https://api.convites.co
     N8N_BLOCK_ENV_ACCESS_IN_NODE=false
     ```
     (sin `/` final; en local/staging usa la URL real de la API).
   - Redeploy / reinicia el contenedor de n8n.
   - El rojo `[ERROR: not accessible via UI, please run node]` en el campo URL
     suele ser **preview del editor**; al ejecutar el nodo sí resuelve `$env`.
   - Alternativa sin env: en cada tool cambia la URL a fija, p.ej.
     `https://api.convites.co/api/bot/v1/centros`.
4. Importa el JSON (o reemplaza el sub-workflow de fachada).  
   Si ves *Could not find property option*, re-descarga este archivo: las query
   params de las HTTP tools deben ir en `parametersQuery.values` (no `.parameters`).
5. En cada tool, abre credenciales y elige **Convites Bot Bearer** (sustituye el placeholder `REPLACE_WITH_N8N_CREDENTIAL_ID`).
6. Prueba: escribe al bot “¿hay convites en Pereira?” — debe llamar `buscar_convites`.

## Probar la API a mano

```bash
export TOKEN='tu-token'
export API='http://localhost:8095'

curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$API/api/bot/v1/health"

curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$API/api/bot/v1/convites?municipio=Pereira&limit=3"
```

## Nota

Las rutas públicas `/api/iniciativas` etc. **no cambian**. El bot solo usa `/api/bot/v1/*`.
