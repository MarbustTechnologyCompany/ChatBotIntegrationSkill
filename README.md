# 🤖 ChatBot Integration Skill — IA para tu sitio web con Ollama Cloud

Paquete **listo para usar** que te permite poner un **chatbot de IA de ventas** en tu
web o app, usando **Ollama Cloud** con el modelo **`gpt-oss:120b`** (gratis). Es el
**mismo patrón probado en producción**: modelo grande en la nube, sin comprar hardware,
con contexto de venta por negocio, memoria de conversación y failover.

> Pensado para que **cualquier persona pueda ponerle un chatbot a su negocio** fácil y gratis.

---

## 💬 ¿Cuántos mensajes gratis por mes?

Con el **free tier de Ollama Cloud** (`gpt-oss:120b`) obtenés, en la práctica:

- **≈ 5,000 – 6,000 mensajes gratis por semana**
- **≈ 20,000 – 25,000 mensajes gratis por mes**

Más que suficiente para un negocio con tráfico normal. Si algún día te quedás corto:
1. Bajás el modelo a **`gpt-oss:20b`** (más liviano, casi la misma calidad) — gratis.
2. Usás una **segunda cuenta** de Ollama como *failover*.
3. Recién ahí, el plan Pro (~$20/mes).

> Los límites exactos (por sesión de 1 h + semanal) los define Ollama y pueden cambiar;
> las cifras de arriba son un estimado real basado en nuestro uso.

---

## 📦 Qué incluye

```
ChatBotIntegrationSkill/
├─ SKILL.md              <- la GUIA completa paso a paso (leé esto primero)
├─ README.md             <- este archivo
├─ LICENSE               <- MIT (libre para usar en tu negocio)
├─ .gitignore
└─ examples/
   ├─ server.js          <- proxy Node.js (OpenAI-compatible, streaming, failover)
   ├─ proxy.php          <- proxy PHP (para hosting cPanel/WordPress)
   ├─ widget.js          <- el chat embebible (botón flotante) + memoria, sin dependencias
   ├─ context.md         <- el "cerebro" del bot: editá con los datos de tu negocio
   ├─ index.html         <- página demo
   ├─ .env.example       <- configuración (copiá a .env y pegá tu API key)
   └─ package.json       <- dependencias del proxy Node
```

## 🚀 Arranque rápido (Ollama Cloud — recomendado)

```bash
# 1. Crea cuenta en https://ollama.com y una API key en https://ollama.com/settings/keys

# 2. Configura y levanta el proxy
cd examples
cp .env.example .env        # pegá tu OLLAMA_API_KEY y tu dominio en ALLOWED_ORIGIN
#   editá context.md con TUS datos (servicios, precios, WhatsApp)
npm install && npm start    # -> http://127.0.0.1:8080

# 3. Probar: abrí examples/index.html en el navegador
```

Después pegás el widget en tu web (ver **sección 5** del `SKILL.md`) apuntando a tu proxy.
Para producción (HTTPS, pm2, Nginx, seguridad): ver **secciones 9–10** del `SKILL.md`.

También podés correrlo **100% local** (privado, sin nube) — ver sección 8 del `SKILL.md`.

## 🧩 Cómo usarlo con Claude Code (opcional)

Copiá la carpeta a `~/.claude/skills/chatbot-ollama-web/` (o `.claude/skills/` de tu
proyecto) y pedile a Claude "ayudame a integrar el chatbot con Ollama". También funciona
como guía normal leyendo `SKILL.md`.

---

## 👤 Autoría

Creado por **[Marco Antonio Bustillos Quiroz — MarAntBQ](https://github.com/MarAntBQ)**
en conjunto con **[Marbust Technology Company](https://marbust.com)**.

## 📄 Licencia

**MIT** — libre y gratis para la comunidad. Podés usarlo, modificarlo y ponerlo en el
negocio que quieras. Ver [`LICENSE`](./LICENSE).

> Este paquete **no contiene claves ni datos privados**: `context.md` es un ejemplo
> ficticio que reemplazás por los datos de tu negocio, y tu API key va en tu `.env` (que
> no se sube a git).
