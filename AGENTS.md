# Reglas del repositorio — ChatBot Integration Skill

Este archivo es para **todas las personas y agentes de IA** que trabajan en este paquete (Claude Code, Codex, Cursor, Copilot u otros). Si usas Claude Code, se carga solo a través de `CLAUDE.md`.

- Si es tu primer día: [`docs/ONBOARDING.md`](docs/ONBOARDING.md).
- Cómo colaborar paso a paso: [`CONTRIBUTING.md`](CONTRIBUTING.md).
- Qué es y cómo se instala: [`README.md`](README.md). El contenido de la skill: [`SKILL.md`](SKILL.md).
- El estándar común de todos los repos de Marbust: `MarbustTechnologyCompany/.github` → `ESTANDAR-REPOSITORIOS.md`.

**Clase del repo: producto de la empresa** (`.github/marbust.json`), **público y open-source (MIT)**. Puede publicar novedades; ver *Novedades* en `CONTRIBUTING.md`.

**Qué es:** un paquete **listo para usar** para ponerle un **chatbot de IA de ventas** a una web o app, usando **Ollama Cloud** (`gpt-oss:120b`, gratis): contexto de venta por negocio, memoria de conversación y **failover**. Incluye la **skill** (`SKILL.md`), **ejemplos** (`examples/` — `proxy.php`, `server.js`, `widget.js`, config) y un **plugin de WordPress** (`wordpress-plugin/mb-chatbot/`).

> 🔴 **La API key va SOLO en el servidor (patrón proxy).** El navegador/widget nunca ve la key: habla con `proxy.php`/`server.js`, que añaden la key del lado del servidor. Un cambio que exponga la key al cliente es un `[bug]` que bloquea. Repo público: cero secretos reales.

## Estructura

```
SKILL.md                 — la skill (cómo integrar el chatbot)
examples/                — integración de referencia: proxy.php, server.js, widget.js,
                           chatbot-config.example.ini, context.md, .env.example, .htaccess
wordpress-plugin/mb-chatbot/  — plugin de WordPress (mb-chatbot.php, widget, uninstall)
README.md · LICENSE (MIT) · LICENSE.es.md
```

## Idiomas

- Contenido y documentación en **español** (Ecuador, tuteo); código en inglés.
- Issues, PRs y commits en español.

## Reglas duras

1. **Flujo:** tarjeta → issue (lo abre `MarbustTechnologyCompany`) → rama → PR en borrador → QA → aprobación de la empresa → squash. Nadie hace push directo a `main`. Detalle en [`CONTRIBUTING.md`](CONTRIBUTING.md). (Contribuciones externas: fork + PR.)
2. **El issue se autocontiene.** Skill `escribir-un-issue`.
3. **Revisión obligatoria.** Antes del PR, corre `revisar-codigo`. **Codex participa siempre.**
4. **API key server-side (regla que manda):** el patrón proxy mantiene la key en el servidor; el widget nunca la lleva. No romper eso.
5. **Público: cero secretos.** Ninguna API key real (Ollama u otra) en el repo o el diff; solo `.env.example`/placeholders.
6. **Los ejemplos funcionan:** `proxy.php`/`server.js`/`widget.js` y el plugin WP se mantienen funcionales; si cambian, se prueban contra Ollama Cloud con una key de prueba propia.
7. **Verificar:** `php -l`/`node --check` sin errores y prueba real (chat responde, failover OK, key no expuesta). El output va pegado en el PR.
8. **Commits** con tipo (`feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `perf`) y en español. **Prohibido** co-autoría de IA en commits y en PRs.
9. **Nada de estado escrito a mano** en el README (el avance vive en issues/releases).

## Seguridad (regla dura)

- **API key solo en el servidor** (proxy); nunca en el navegador/widget.
- **Público:** sin API keys ni secretos reales en el repo; solo placeholders.
- Una vulnerabilidad se reporta en privado: ver [`SECURITY.md`](SECURITY.md).

## Skills del repositorio (para contribuir)

Hay **dos rutas** (esto es aparte de la skill que **publica** este repo):

1. **Colaboradores nuevos o externos** usan las skills de colaboración en [`.claude/skills/`](.claude/skills). Son una **copia sincronizada** desde el directorio oficial por el Action `sync-skills`; **no se editan a mano aquí**.
2. **Colaboradores oficiales de Marbust** usan el **directorio oficial** (`MarbustTechnologyCompany/ClaudeSkills`, en `~/.claude/skills`). **Es la fuente de verdad.**

| Skill | Cuándo |
|---|---|
| `escribir-un-issue` | Al crear o corregir un issue |
| `trabajar-un-issue` | Al tomar un issue, de principio a fin |
| `revisar-codigo` | **Obligatoria** antes del PR y al revisar el de otro |
