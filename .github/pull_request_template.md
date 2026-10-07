<!-- Ábrelo como borrador (Draft) apenas empieces, con `Refs #<número>`. Cuando esté listo, cámbialo a `Closes #<número>` y márcalo como listo para revisión. La plantilla se llena completa también en borrador. Este repo es PÚBLICO y open-source. -->

## Resumen

<!-- Qué cambió (skill, ejemplos, plugin de WordPress) y por qué. -->

## Novedad

<!-- Sale sola de este PR cuando se mergea. Una línea por público; "ninguna" si no aplica.
La línea pública la puede leer cualquiera (es un repo público): nada de API keys ni secretos. El check "Checks del PR" exige las tres líneas. -->

- pública:
- interna:
- hito: no

## Issue vinculado

<!-- `Closes #123` / `Refs #123`, en inglés. -->

## Cómo probar

<!-- Qué parte cambió (ejemplo PHP/JS o plugin WP) y cómo confirmar que funciona con Ollama Cloud (usando una API key de prueba propia, nunca una real en el repo). -->

1.

## Verificación

<!-- Solo lo que SÍ se corrió, con el output pegado. N/A con el motivo si no aplica. -->

- [ ] `php -l proxy.php` / `node --check server.js` (o el archivo que tocaste) sin errores de sintaxis
- [ ] Probado contra Ollama Cloud con una API key **propia de prueba** (chat responde, failover OK)
- [ ] La **API key va solo en el servidor** (proxy): no se expone en el navegador ni en el widget
- [ ] Si tocó el plugin de WordPress: instala/activa sin errores

## Seguridad y operaciones

- [ ] No hay API keys ni secretos en el repo (solo `.env.example`/placeholders)
- [ ] El patrón proxy sigue manteniendo la key del lado del servidor

---

> Este PR entra a `main` por **squash**. Un issue, un PR, un commit. Sin co-autoría de IA en commits ni en PRs.
