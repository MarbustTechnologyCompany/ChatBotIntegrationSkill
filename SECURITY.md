# Seguridad

Este paquete integra un chatbot de IA con **Ollama Cloud**; su punto más sensible es la **API key**. Si encuentras una falla de seguridad (o una key/secreto filtrado), gracias por avisarnos. **No la publiques en un issue:** escríbenos de forma privada (es un repo público).

## Cómo reportarla

Escribe a **supportcenter@marbust.com** con el asunto "Seguridad ChatBot Integration: …".

Incluye qué encontraste y dónde (archivo, línea, commit) y el impacto.

## Lo que pedimos

- Prueba con una API key **propia de prueba**, nunca con una real de un tercero.
- No degrades el servicio.
- Danos tiempo razonable para corregir antes de hacerlo público.

## Cómo se protege

- **API key solo en el servidor (patrón proxy):** el navegador/widget habla con `proxy.php`/`server.js`, que añaden la key del lado del servidor; la key **nunca** llega al cliente.
- **Repo público sin secretos:** no hay API keys reales en el código; solo `.env.example`/placeholders. El `.htaccess` del ejemplo ayuda a proteger archivos sensibles.
- Si un cambio expusiera la key al cliente, es un `[bug]` que bloquea el merge.

## Versiones con soporte

Solo la rama `main` (última versión publicada).
