# Cómo colaborar — ChatBot Integration Skill

Esta guía es el recorrido de cada cambio. Las reglas están en [`AGENTS.md`](AGENTS.md); si es tu primer día, parte de [`docs/ONBOARDING.md`](docs/ONBOARDING.md).

Este repo es **público y open-source**. Las contribuciones externas son bienvenidas: abre un issue o un PR desde un fork. El mantenedor (Marbust Technology Company) revisa y mergea.

## El flujo (equipo Marbust)

1. **Tarjeta en Trello** — marca Marbust Dev, severidad, ejecutor, cómo probar.
2. **Issue** — lo abre `MarbustTechnologyCompany` con la plantilla. Se autocontiene. Skill `escribir-un-issue`.
3. **Rama** — desde `main`, nunca push directo a `main`.
4. **PR en borrador** — con la plantilla (`Refs #N`); al terminar, `Closes #N` y listo.
5. **Verificación real** — prueba el ejemplo/plugin y **pega el output** en el PR.
6. **Revisión / QA** — antes del PR corre `revisar-codigo`. **Codex participa siempre.** Si el QA encuentra bugs, la empresa comenta **solicitando cambios**; se corrige, se responde, y recién si pasa se aprueba.
7. **Aprobación** — la da `MarbustTechnologyCompany` (el autor no se auto-aprueba).
8. **Squash** — un issue, un PR, un commit.

## Pruebas locales

- Una **API key propia de prueba** de Ollama Cloud (nunca una real en el repo).
- Levantar el ejemplo (`proxy.php` con PHP, o `server.js` con Node) y abrir el widget; o instalar el plugin en un WordPress de prueba.

## Verificación (antes de pedir revisión)

| Qué | Para qué |
|---|---|
| `php -l` / `node --check` | sintaxis sin errores en lo que tocaste |
| Prueba contra Ollama Cloud | el chat responde, failover OK, con una key de prueba propia |
| Key no expuesta | el widget no lleva la API key; va solo por el proxy |
| Plugin WP | instala/activa sin errores (si lo tocaste) |

## Novedades (producto público)

Cada PR declara su **Novedad** (pública / interna / hito). La línea pública la puede leer cualquiera: **no** incluye API keys ni secretos. El check "Checks del PR" exige las tres líneas.

## Reglas que no se discuten dentro de un PR

- **API key server-side** (patrón proxy): el widget nunca la lleva.
- **Cero secretos reales** en el repo; solo `.env.example`/placeholders.
- Los ejemplos y el plugin se mantienen funcionales.
- Commits con tipo, en español. **Prohibida la co-autoría de IA** en commits y PRs.
