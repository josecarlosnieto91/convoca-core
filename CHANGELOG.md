# Changelog — convoca-core

## v2.3.2 (2026-09-23)

### 🔒 Seguridad y robustez
- `License_Manager`: las tres redirecciones de activación/desactivación pasan a `wp_safe_redirect` — el destino salía de `wp_get_referer()`, que es entrada del visitante.
- `Admin_Logs_List`: respaldo de orden cuando `sanitize_sql_orderby()` no valida el valor pedido (antes quedaba `ORDER BY  DESC` y la consulta fallaba).

### 🧹 Saneamiento
- Los 14 `error_log` directos de `Admin_Templates` y `Signature` van al logger del plugin (`Logger::warning`/`error`), con contexto; se elimina el prefijo `BDV`, que era el nombre de un cliente dentro del producto.
- `json_encode` → `wp_json_encode`; `@` retirado donde ya se comprobaba el retorno; `in_array` estricto; `urlencode` → `add_query_arg`; `prepare()` retirado de las consultas sin marcadores; parámetros `new`/`default` renombrados; `count()` fuera de la condición del `for`.
- Alineación corregida con `phpcbf` (63 avisos cosméticos, sin cambios de comportamiento).
- Avisos restantes de PHPCS **clasificados y justificados** en `docs/phpcs-warnings.md` (292 → 199; no se silencia ninguno).

## v2.3.1 (2026-09-23)

### 🔒 Seguridad
- **Vista previa de plantillas de correo**: el HTML del correo se pinta ahora en un iframe con `sandbox` (a través de un blob), en lugar de inyectarse en el documento del panel. Cierra tres avisos de CodeQL (`js/xss-through-dom`).
- Los workflows (`ci.yml`, `deploy.yml`) declaran `permissions: contents: read`: el token del workflow sólo necesita leer el repositorio.

### 🐛 Arreglado
- `Admin_Setup_Wizard`: las notas del diagnóstico de páginas se escapan al imprimirse (no en el momento de construirlas), que es lo que exige la comprobación de Plugin Check.

## v2.3.0 (2026-09-23)

### 🏗️ Arquitectura
- Los shortcodes de interfaz y contenido pasan a Core desde el mu-plugin privado de un sitio: `[convoca_menu]`, `[convoca_socials]`, `[convoca_relacionadas]`, `[convoca_stats]`, `[convoca_cuando]` y `[convoca_donde]` (`includes/front-shortcodes.php`). Con los plugins y el theme ya se pinta un sitio completo, sin código privado de nadie.
- Los datos de evento de una entrada (marcarla como evento, fecha de inicio y fin, lugar) se gestionan desde Core (`includes/event-meta.php`): formulario, guardado y lectura. El theme sólo publica el schema.org y pinta lo que le da Core.
- Redes del sitio: **una sola fuente**, el filtro `convoca_social_links`, que alimenta el shortcode y los tokens del theme (antes cada uno leía su filtro).
- Cifras del sitio: `\Convoca\Core\site_stats()` con el filtro `convoca_site_stats` (antes `convoca_theme_stats`, en el theme).
- Compatibilidad de datos: el mapa de claves heredadas de evento se declara desde el sitio con `convoca_event_meta_legacy_keys`; el nombre de una asociación no vive en el producto.

### ⚠️ Migración
- `convoca_theme_stats` → `convoca_site_stats`.
- `convoca_theme_social_instagram/facebook/youtube/handle` → `convoca_social_links` (array servicio => URL, más `handle`).
- `convoca_theme_get_stats()` → `\Convoca\Core\site_stats()`.

## v2.2.9 (2026-09-23)

### 🐛 Correcciones
- El asistente de configuración escribía `[mi_panel]` en la página «Mi Panel de Socio»: nadie registra ese shortcode, así que la página se publicaba con el texto literal a la vista del visitante (visto en producción). Ahora escribe `[convoca_mi_area]`.
- El paso 2 (Páginas) comprueba el contenido real de cada página, no solo que exista: avisa si a la página le falta su shortcode y el botón «Crear o reparar páginas» la repara sin tocar el texto del autor.
- El asistente anota en el registro cuándo el shortcode que va a escribir no lo registra ningún plugin activo (antes publicaba la página rota en silencio).
- Las páginas del sistema viven en una única lista (`system_pages()`) que usan el diagnóstico, la creación, el resumen y el estado de configuración (antes eran cuatro copias que podían divergir).

## v2.2.8 (2026-09-11)

### 🐛 Correcciones
- Monitor de pagos: deja de registrar como «fallo de firma» una notificación rechazada por IP.

## v2.2.7 (2026-09-11)

### ✨ Mejoras
- Traducción completa y al día del plugin y su plantilla al inglés (en_US)

### 🐛 Correcciones
- La revalidación semanal de licencia se programa correctamente y solo se ejecuta cuando hay una licencia activa

## v2.2.6 (2026-09-10)

### ✨ Mejoras
- Botón "Reparar" en la página de Salud para las comprobaciones con corrección automática
- Retención de logs por nivel configurable + export a CSV
- Reintentos de webhook con retroceso (backoff) configurable + nivel de log configurable
- Tema claro/oscuro para documentos (claro por defecto) con selector en el Asistente de configuración

### 🐛 Correcciones
- La desinstalación ya no deja tablas, opciones ni tareas programadas huérfanas (con opción de conservar los datos)
- Notificaciones y Asistente de configuración vuelven a aparecer en el menú de administración
- Corregido un error de JavaScript que rompía el script de administración (SyntaxError por comillas mixtas)
- Los CSVs del export de copias de seguridad coinciden con los nombres que busca el import
- Los gráficos vacíos del dashboard muestran "Sin datos" y el PDF de memoria ya no incluye código PHP crudo ni emojis
- La página de Salud detecta correctamente Convoca Shifts activo
- El CSS de la página de Salud se carga en el panel de administración
- El dropdown de notificaciones de la barra de administración ya no se sale del marco ni desborda con textos largos
- El parámetro de versión de los assets comunes ya no queda congelado en una versión anterior

### 📦 Infraestructura
- Dependencias actualizadas (dompdf, PHPCS, WPCS)
- Preparación para WordPress.org: Plugin Check sin errores, Chart.js local y deuda estática a cero

## v2.2.5 (2026-09-05)

### 🐛 Correcciones
- El Security Monitor reporta la contención de bloqueos también cuando no hay filas afectadas

## v2.2.4 (2026-09-05)

### 🐛 Correcciones
- Corregidas la contención de bloqueos y el hook REST en el Security Monitor

## v2.2.3 (2026-09-05)

### ✨ Mejoras
- Security Monitor: observabilidad de eventos críticos y resumen diario

### 📦 Infraestructura
- CI 100 % bloqueante (estándares de código, análisis estático y tests)

## v2.2.2 (2026-09-05)

### 🔒 Seguridad
- Corregidos la exclusión mutua de bloqueos, las capacidades del Asistente y el inicio de sesión por AJAX

## v2.2.1 (2026-09-05)

### ✨ Mejoras
- El Asistente permite editar el ID corto (slug) de cada plan, con migración de los miembros

## v2.2.0 (2026-09-05)

### ✨ Mejoras
- El paso 3 del Asistente permite editar el nombre y la modalidad de cada plan

## v2.1.9 (2026-09-05)

### 🐛 Correcciones
- Navegación libre en el Asistente: pasos clicables y botón "Anterior" en el paso 7

## v2.1.8 (2026-09-05)

### ✨ Mejoras
- El paso 3 del Asistente permite activar o desactivar planes y editar su precio

## v2.1.7 (2026-09-05)

### ✨ Mejoras
- El paso 7 "Finalización" muestra un resumen de los 6 pasos configurados

### 🐛 Correcciones
- Botón "Siguiente" visible en el paso 6 (Ecosistema) para avanzar a Finalización

## v2.1.6 (2026-09-05)

### 🐛 Correcciones
- El Asistente detecta los módulos activos y aplica las clases CSS de WordPress en los campos

## v2.1.5 (2026-08-08)

### ✨ Mejoras
- Internacionalización completa: mensajes de error y respuestas REST traducibles
- Paso 6 "Ecosistema" del Asistente: explica módulos, estado de instalación e importación
- Registro y validación de módulos externos (marketplace)
- Historial de migraciones con validación posterior y posibilidad de revertir
- Perfilador de peticiones (rendimiento)

### 🐛 Correcciones
- El enlace de adquisición de licencia apunta a la tienda
- Corregida una comilla faltante en la consulta de socios activos y el alias en el resumen de los últimos 7 días
- El shortcode del Asistente de configuración es el correcto (`convoca_alta_socio`)
- Evitada una clave duplicada en instalaciones multisitio
- El endpoint REST de métricas usa el callback existente
- Eliminados los datos de Biodevas incrustados: planes, pistas, correos y URLs ahora se configuran mediante filtros
- El límite de intentos ya no queda enmascarado por la caché de objetos
- Modo oscuro: etiquetas de formulario en naranja e inputs con fondo oscuro
- Las peticiones de licencia se firman con nonce HMAC

### 🧪 Tests
- Tests unitarios ampliados (arranque, integración y utilidades)

### 📝 Documentación
- Referencia de hooks generada desde el código
- Readme preparado para WordPress.org
- Documentación unificada en la wiki

### 📦 Infraestructura
- Preparación para WordPress.org: readme en inglés, correcciones de Plugin Check, icono y banner
- Plantillas de issues y GitHub Discussions

---

## v2.1.4 (2026-06-24)

### ✨ Improvements
- Añadido publisher a PRO_FEATURES para habilitar licenciamiento completo
- Añadidas nuevas capabilities para diagnóstico y monitoreo del sistema
- Mejoras en diagnóstico y salud del sistema

### 🧪 Tests
- Añadidos tests unitarios para Utils::validate_dni (14 casos, validación DNI/NIE)
- Añadidos tests para Features (8) + Capabilities (5)

### 📦 Infrastructure
- Updated release ZIPs on getconvoca.app
- Demo environment synchronized

---

*Entradas nuevas reconstruidas a partir del historial de git.*
