# Tu primer día en ChatBot Integration Skill

Guía para quien empieza a mantener este paquete. Si algo no alcanza, es un error de la guía: dilo en un issue.

## 1. Qué es, en un minuto

Un paquete **listo para usar** (skill + ejemplos + plugin de WordPress) para ponerle un **chatbot de IA de ventas** a una web, usando **Ollama Cloud** (`gpt-oss:120b`, gratis), con contexto de venta por negocio, memoria y failover. El contenido: `SKILL.md`, `examples/` (`proxy.php`, `server.js`, `widget.js`, config) y `wordpress-plugin/mb-chatbot/`.

- 🔴 **La API key va solo en el servidor** (patrón proxy): el widget nunca la ve.
- **Repo público:** sin API keys ni secretos reales; solo `.env.example`.

## 2. Qué leer, en este orden

1. [`README.md`](../README.md) — qué es e instalación.
2. Esta guía.
3. [`CONTRIBUTING.md`](../CONTRIBUTING.md) — el recorrido de cada cambio (incluye externos).
4. [`AGENTS.md`](../AGENTS.md) — estructura, seguridad (key server-side) y skills.
5. [`SKILL.md`](../SKILL.md) — el contenido de la skill.
6. Las skills de colaboración en [`.claude/skills/`](../.claude/skills) (copia para colaboradores nuevos; los oficiales usan el directorio de la empresa).
7. [`SECURITY.md`](../SECURITY.md).

## 3. Accesos que debes pedir

Los concede Marco Antonio Bustillos (indica tu usuario de GitHub y correo):

| Acceso | Para qué |
|---|---|
| Colaborador del repo `MarbustTechnologyCompany/ChatBotIntegrationSkill` (equipo Marbust) | Ramas y PRs (externos: fork + PR) |
| Tablero de Trello | Mover tus tarjetas |
| Una API key de prueba de Ollama Cloud (propia) | Probar el chat |

## 4. Herramientas

- **git** y **GitHub CLI** (`gh`). **PHP** y/o **Node** para los ejemplos. Un WordPress de prueba si tocas el plugin.

## 5. Tu primer issue

Sigue la skill [`trabajar-un-issue`](../.claude/skills/trabajar-un-issue/SKILL.md): elige uno chico, confirma en el issue, rama desde `main` (o fork), PR borrador, prueba el ejemplo/plugin contra Ollama Cloud con tu key de prueba, revisa tu diff con `revisar-codigo`, pasa el QA, responde la revisión hasta el squash.

## 6. Cuentas en GitHub

`MarbustTechnologyCompany` crea los issues y aprueba los PRs. Quien implementa (`MarAntBQ`, el equipo o un externo desde un fork) hace ramas/forks y PRs. Nadie aprueba su propio PR. Merge por squash.

## 7. Lo que nunca se hace

- Exponer la API key al navegador/widget (va solo por el proxy).
- API keys o secretos reales en el repo, issues o PRs.
- Dejar un ejemplo o el plugin roto.
- Push directo a `main`; trabajar sin issue.
- Co-autoría de IA en commits o PRs.
