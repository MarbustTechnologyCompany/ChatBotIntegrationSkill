=== Chatbot IA (Ollama) ===
Contributors: marantbq
Author URI: https://marbust.com
Tags: chatbot, ai, ollama, chat, asistente, ventas
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT

Chatbot de IA de ventas para tu sitio. Con Ollama Cloud (gpt-oss:120b, gratis) o tu propio backend.

== Description ==

Agrega un chatbot de IA de ventas a tu WordPress. Dos modos:

* **Ollama directo (self-contained):** pones tu API key de Ollama Cloud y el bot corre con el modelo gpt-oss:120b (gratis, ~20.000-25.000 mensajes/mes en el free tier). El plugin llama a Ollama desde el servidor; la clave NUNCA sale al navegador.
* **Backend externo:** apuntas el plugin a tu propia API (que recibe {message, history, site} y devuelve {reply}). Ideal si ya tenes un backend de chatbot.

Incluye: widget flotante configurable (nombre, bienvenida, color), contexto de venta editable, memoria de conversacion, rate limit y mensaje de respaldo si el proveedor falla.

Hecho por MarAntBQ en conjunto con Marbust Technology Company. Licencia MIT.

== Installation ==

1. Subi la carpeta `mb-chatbot` a `/wp-content/plugins/` (o instala el .zip desde Plugins > Anadir nuevo > Subir plugin).
2. Activa el plugin.
3. Ve a **Ajustes > Chatbot IA**.
4. Elegi el modo:
   * *Ollama directo*: crea una API key en https://ollama.com/settings/keys, pegala, y escribi el contexto de tu negocio.
   * *Backend externo*: pega la URL de tu API y (opcional) el site key.
5. Guarda. El chatbot aparece como boton flotante en tu sitio.

== Frequently Asked Questions ==

= Necesito un servidor aparte? =
No en modo "Ollama directo": WordPress mismo llama a Ollama Cloud. Solo necesitas la API key.

= Es gratis? =
El free tier de Ollama Cloud con gpt-oss:120b alcanza ~20.000-25.000 mensajes al mes. Si te quedas corto, baja a gpt-oss:20b o usa el plan Pro.

= Mi API key es segura? =
Si. Se guarda en la base de datos de WordPress y solo se usa desde el servidor; nunca se envia al navegador del visitante.

== Changelog ==

= 1.0.0 =
* Version inicial: modos Ollama directo y Backend externo, widget flotante, contexto configurable, memoria y rate limit.
