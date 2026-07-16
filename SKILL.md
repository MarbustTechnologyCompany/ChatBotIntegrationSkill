---
name: chatbot-ollama-web
description: Integrar un chatbot de IA de VENTAS en un sitio web usando Ollama Cloud (modelo gpt-oss:120b gratis) via API OpenAI-compatible, con failover, contexto de venta por negocio y memoria de conversacion. Es el MISMO patron probado en produccion. Cubre el proxy (Node/PHP), el widget embebible, como escribir el contexto que vende (no da soporte gratis, no inventa), el free tier de Ollama y el despliegue con HTTPS. Usar cuando alguien quiere un asistente/chatbot en su web sin pagar por token ni comprar GPU.
---

# Chatbot de IA (Ollama Cloud) para tu sitio web

Guía para poner un **asistente de ventas** en una web usando **Ollama Cloud** con el modelo **`gpt-oss:120b`** (gratis). Es el **mismo patrón que ya montamos en producción** y funciona muy bien: un modelo grande en la nube, **sin hardware**, con contexto de venta por negocio, memoria y failover.

> **Por qué Ollama Cloud y no local:** `gpt-oss:120b` es un modelo grande (de OpenAI, abierto) que en una PC normal **no corre** (necesitaría mucha GPU). En Ollama Cloud corre gratis dentro del free tier. Un modelo grande responde mucho mejor para atender clientes que uno chico de 3–8B corriendo en tu máquina. La opción local existe (sección 8) para privacidad total, pero el objetivo acá es replicar lo de producción: **Cloud primero.**

Ejemplos **funcionales** en `examples/`:

| Archivo | Para qué |
|---|---|
| `examples/server.js` | Proxy **Node.js** (OpenAI-compatible, streaming, failover, rate limit) |
| `examples/proxy.php` | Proxy **PHP** (para hosting cPanel/WordPress) |
| `examples/widget.js` | **Widget** embebible (botón flotante + chat + memoria), sin dependencias |
| `examples/context.md` | El **contexto de venta** del bot (editá con los datos del negocio) |
| `examples/index.html` | Página demo |
| `examples/.env.example` | Config (Cloud primario + failover + alternativa local) |
| `examples/package.json` | Dependencias del proxy Node |

---

## 0. El concepto en 30 segundos

```
Navegador (widget.js)  ->  TU proxy (server.js / proxy.php)  ->  Ollama Cloud (gpt-oss:120b)
   pregunta + historial      + contexto de venta                   genera la respuesta
                             + failover / rate limit / API key
```

**Regla de oro:** el navegador **nunca** habla directo con Ollama. Siempre pasa por *tu* proxy. Por qué:
- La **API key** vive en el server, no en el HTML (si la ponés en el navegador te la roban y consumen tu cuota).
- El "cerebro" del bot (precios, tono, reglas) queda del lado del server.
- Podés frenar abusos (rate limit), limitar tamaños y restringir por dominio (CORS), y hacer **failover** a un segundo proveedor.

---

## 1. Crear la cuenta de Ollama Cloud y la API key

1. Cuenta gratis en **https://ollama.com**.
2. Generá una **API key** en **https://ollama.com/settings/keys**.
3. Esa key va en el `.env` del proxy (nunca en el navegador ni en git).

**El free tier alcanza de sobra para tráfico modesto:** hay un límite por sesión (1 hora) y uno semanal. En la práctica equivale a **~5,000–6,000 mensajes gratis por semana (unos 20,000–25,000 al mes)** con `gpt-oss:120b` — más que suficiente para un negocio con tráfico normal. (Los límites exactos los define Ollama y pueden cambiar; es un estimado real de nuestro uso.) Si algún día se te queda corto:
- Primero bajá el modelo a **`gpt-oss:20b`** (más liviano, casi la misma calidad para atención) — es la palanca antes de pagar.
- Recién si el tráfico se dispara, el plan Pro (~$20/mes).

---

## 2. La API (lo mínimo que hay que saber)

Usamos el endpoint **OpenAI-compatible**: `POST {baseUrl}/chat/completions`.

- `baseUrl` = `https://ollama.com/v1` (Cloud) **o** `http://127.0.0.1:11434/v1` (local). **Es el mismo código**: solo cambia el `.env`.
- Header `Authorization: Bearer TU_API_KEY`.
- Body: `{ model, messages:[{role,content}], stream, temperature }`.
- `role` = `system` (instrucciones), `user` (persona), `assistant` (respuestas previas = memoria).
- Con `stream:true` responde SSE: líneas `data: {json}` donde `choices[0].delta.content` es cada pedacito, y termina en `data: [DONE]`.

Todo esto ya está resuelto en `server.js` / `proxy.php`. No tocás la API a mano.

Prueba rápida (verifica cuenta + modelo):
```bash
curl https://ollama.com/v1/chat/completions \
  -H "Authorization: Bearer TU_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-oss:120b","messages":[{"role":"user","content":"Deci hola en una frase"}],"stream":false}'
```

---

## 3. Qué modelo elegir (comparativa probada)

Para un chatbot de **ventas** querés un modelo **grande, que respete instrucciones, que no mezcle idiomas ni escupa JSON**. Esto salió de probar una batería de casos difíciles:

| Modelo | Veredicto |
|---|---|
| **`gpt-oss:120b` (Ollama Cloud)** ⭐ | Español/inglés impecable, respeta reglas, no inventa, no da tutoriales, sin "leak" de otros idiomas ni JSON. **El que usamos.** |
| `gpt-oss:20b` (Ollama Cloud) | Casi igual de bueno, más liviano. Ideal como **failover** o si aprieta el free tier. |
| Modelos 7–8B locales (qwen, llama, mistral) | Andan, pero cada uno con un defecto que **ningún prompt arregla**: mezclan chino, devuelven JSON de "tool call", o se van al inglés. Sirven de respaldo, no de titular. |

**Regla:** titular = `gpt-oss:120b`; failover = `gpt-oss:20b` (u otra cuenta / un local). El `server.js` ya viene así.

---

## 4. Levantar el proxy

### Opción A — Node.js (recomendada, con streaming)
Requiere Node 18+.
```bash
cd examples
cp .env.example .env          # pegá tu OLLAMA_API_KEY y ajustá ALLOWED_ORIGIN
#   editá context.md con los datos del negocio
npm install
npm start                     # -> http://127.0.0.1:8080
curl http://127.0.0.1:8080/health   # {"ok":true, providers:[...]}
```

### Opción B — PHP (hosting compartido / WordPress)
1. Subí `proxy.php` y `context.md` a una carpeta del sitio (ej. `/public_html/chat/`).
2. Poné la API key y el modelo (por variables de entorno o editando el arriba del `proxy.php`).
3. Endpoint: `https://tudominio.com/chat/proxy.php`. (El PHP no hace streaming; el texto llega de golpe. El widget lo soporta igual.)

---

## 5. Poner el widget en la web

Antes de `</body>`:
```html
<script>
  window.CHATBOT_API     = 'https://tudominio.com/chat';   // URL de TU proxy
  window.CHATBOT_NAME    = 'Asistente de Mi Negocio';
  window.CHATBOT_WELCOME = 'Hola! Soy el asistente virtual. En que te ayudo?';
  window.CHATBOT_ACCENT  = '#2563eb';                        // color de tu marca
</script>
<script src="widget.js" defer></script>
```

Aparece un botón 💬 abajo a la derecha. El widget **recuerda la conversación** de esa visita (manda el historial al backend) y muestra la respuesta en vivo. Para WordPress: pegá ese bloque en el footer del tema.

> **¿Tu sitio es WordPress?** Usá el **plugin instalable** en `wordpress-plugin/mb-chatbot/` (panel en Ajustes → Chatbot IA): más fácil que pegar scripts, con configuración visual y soporte para *Ollama directo* o *Backend externo*. Ver el README.

---

## 6. El contexto que VENDE (lo más importante)

El 90% del resultado depende de `context.md`. Estas reglas son las que aprendimos que **sí funcionan** (están aplicadas en el ejemplo):

- **VENDE, no da soporte gratis.** Ante "mi PC está lenta / cómo hago X" → NO expliques los pasos (eso es un servicio pagado); ofrecé el servicio/plan y cerrá por WhatsApp.
- **No inventa.** Solo precios/datos que estén en el contexto. Si no está → deriva a WhatsApp/tienda. (Los modelos débiles inventan precios; por eso hay que ser explícito.)
- **Solo el negocio.** Off-topic (una tarea de mates) → reencauza con amabilidad, no la respondas.
- **Idioma del cliente.** Regla al INICIO y explícita (responder en inglés si escriben en inglés).
- **Enlaces clicables.** Correo/WhatsApp/web siempre en markdown (`[texto](url)`), nunca crudos.
- **No parrotear.** "Redactá con tus propias palabras, no digas 'por ejemplo'." (Los modelos débiles copian los ejemplos literal.)
- **Nada de JSON.** Prohibir objetos/llamadas a funciones (por si el modelo es "tool-happy").
- **Estructura:** `[REGLAS CRÍTICAS] + [datos del negocio] + [REGLAS GENERALES]` — las reglas duras al principio (alta prioridad) y al final (refuerzo). Mirá `examples/context.md`.

---

## 7. Memoria de conversación

Ya viene resuelta: el **widget** guarda los mensajes de la visita y los manda como `history`; el **proxy** los antepone (últimos 12 turnos) antes de tu pregunta. Así el bot entiende seguimientos ("¿y cuánto cuesta eso?") y mantiene el idioma. No hay que hacer nada extra.

---

## 8. Alternativa 100% local (opcional)

Si el amigo prefiere que **nada salga a la nube** (privacidad total) o no quiere depender del free tier:
```bash
curl -fsSL https://ollama.com/install.sh | sh   # Linux/Mac (Windows: instalador web)
ollama pull llama3.2                            # modelo chico que corre en CPU
```
Y en el `.env` descomentá el bloque local (`OLLAMA_BASE_URL=http://127.0.0.1:11434/v1`, `OLLAMA_MODEL=llama3.2`, `OLLAMA_API_KEY=ollama`). **Ojo:** un modelo local chico responde peor que `gpt-oss:120b`. Buena idea usarlo como **failover** del Cloud, no como titular.

---

## 9. Seguridad y despliegue

- **API key SOLO en el server** (en el `.env`, nunca en git ni en el navegador). Si se filtra, revocala en ollama.com y generá otra.
- **CORS al dominio real:** `ALLOWED_ORIGIN=https://tudominio.com` (no `*` en vivo).
- **Rate limit** (ya viene, 20/min por IP) + tope de mensaje/historial (control de cuota).
- **Failover + fallback:** si el primario falla, prueba el segundo proveedor; si ambos fallan, responde "escribinos por WhatsApp" en vez de romper.
- **HTTPS siempre.** Serví el proxy detrás de Nginx/Apache con certificado (Let's Encrypt).
- **Producción (Node):**
  ```bash
  npm i -g pm2 && pm2 start server.js --name chatbot && pm2 save && pm2 startup
  ```
  Nginx (para que fluya el streaming):
  ```nginx
  location /chat {
      proxy_pass         http://127.0.0.1:8080/chat;
      proxy_http_version 1.1;
      proxy_set_header   X-Forwarded-For $remote_addr;
      proxy_buffering    off;      # sin esto el streaming no fluye
      proxy_read_timeout 120s;
  }
  ```

---

## 10. Verificación (checklist)

1. `curl .../v1/chat/completions` con tu key responde (sección 2). ✅
2. `curl http://127.0.0.1:8080/health` da `{"ok":true}` con los proveedores. ✅
3. Abrís `index.html`, escribís "Hola" y **responde en vivo**. ✅
4. Preguntás por un servicio del `context.md` → contesta con esos datos, **no inventa precios**. ✅
5. Escribís en inglés → **responde en inglés**. ✅
6. En producción: HTTPS, CORS al dominio real, la API key no aparece en el navegador (F12 → Network). ✅

---

## 11. Problemas comunes

| Síntoma | Causa / arreglo |
|---|---|
| `401 Unauthorized` de Ollama | Falta/errada la `OLLAMA_API_KEY`, o el `baseUrl` no es `https://ollama.com/v1`. |
| El widget no responde | ¿El proxy corre? ¿`CHATBOT_API` apunta bien? Mirá la consola del navegador (F12). |
| `CORS error` | `ALLOWED_ORIGIN` no coincide con el dominio (ponelo exacto, con `https://`). |
| Se agotó el free tier | Bajá a `gpt-oss:20b`, o activá el failover (segunda cuenta/local), o pasá a Pro. |
| Inventa datos / da tutoriales | Reforzá `context.md`: "vende, no da soporte; si no sabés no inventes, derivá a WhatsApp". `temperature` ya está en 0.4. |
| Streaming aparece de golpe | Falta `proxy_buffering off;` en Nginx, o estás con el proxy PHP (no hace streaming). |
| Respuesta en el idioma equivocado | La regla de idioma tiene que ir al INICIO del `context.md` y explícita. |

---

## Resumen para el amigo

1. Cuenta en ollama.com + **API key**.
2. En `examples/`: `cp .env.example .env` (pegá la key), editá `context.md` con tus datos, `npm install && npm start`.
3. Pegá el bloque del widget (sección 5) en tu web apuntando a tu proxy.
4. Producción: pm2 + Nginx con HTTPS, CORS a tu dominio.

Titular **`gpt-oss:120b` gratis en la nube**, failover a `gpt-oss:20b`, contexto que vende y memoria de conversación — el mismo patrón que ya corre en producción.
