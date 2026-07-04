=== Convoca Core ===
Contributors: josecarlosnietoramos
Tags: common, utilities, logging, validation, webhooks, licenses, asociaciones, ONG, voluntariado
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.1.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin base del ecosistema Convoca. Proporciona logging, rate limiting, webhooks, licencias y dashboard para asociaciones y ONGs.

== Description ==

Plugin base del ecosistema Convoca. Proporciona funcionalidades compartidas para todos los demás plugins Convoca:

* Sistema de logging — Registro centralizado con niveles, contexto y purga automática
* Rate limiting — Protección contra abusos con UPSERT atómico
* Webhooks — Gestión de webhooks entrantes y salientes con reintentos
* Locks atómicos — Prevención de race conditions
* Gestor de licencias — Validación local y remota (servicio externo opcional), funciones PRO
* Setup Wizard — Asistente de configuración en 6 pasos
* Dashboard centralizado — Métricas del sistema desde un solo panel
* Visor de logs — WP_List_Table con filtros
* Página de Salud del Sistema — Diagnóstico de dependencias y configuración
* Backup/Restore — Exportación ZIP con previsualización de importación
* Capacidades centralizadas — Constantes para todas las capabilities de Convoca
* Generación de PDF — Integración con Dompdf para certificados y memorias

= Servicios externos =

Este plugin puede conectar con un servicio externo de validación de licencias en `getconvoca.app/api/license.php` para verificar claves de licencia PRO. Esta conexión es completamente opcional y solo ocurre cuando el administrador introduce una clave de licencia en el panel de administración. El plugin funciona sin esta conexión con todas las funcionalidades gratuitas.

= Privacidad =

Este plugin es el núcleo técnico del ecosistema Convoca y proporciona funcionalidades de infraestructura. No recopila datos personales de forma directa, pero su sistema de logging centralizado puede registrar acciones de usuarios y administradores que incluyan datos personales de forma indirecta (direcciones IP, marcas de tiempo, acciones realizadas, contenido de peticiones). El sistema de rate limiting almacena direcciones IP temporalmente para prevenir abusos.

Los logs se almacenan en la base de datos local de WordPress (tabla wp_convoca_logs) y se purgan automáticamente según la configuración de retención del plugin (por defecto 30 días). Los datos de rate limiting se limpian automáticamente al expirar las ventanas de tiempo.

Los webhooks gestionados por este plugin pueden enviar datos a URLs configuradas externamente; es responsabilidad del administrador del sitio revisar qué datos se incluyen en los webhooks y asegurar el cumplimiento normativo.

No se comparten datos personales con terceros por parte de este plugin.

Los usuarios tienen derecho a:
* Solicitar acceso a los logs que les conciernan
* Solicitar la eliminación de logs personales (sujeto a retenciones de seguridad)
Para ejercer estos derechos, contacte con el administrador del sitio.

== Installation ==

1. Sube la carpeta convoca-core a /wp-content/plugins/
2. Ejecuta composer install dentro del directorio del plugin (requerido para Dompdf)
3. Activa el plugin desde el menú Plugins

== Frequently Asked Questions ==

= ¿Es necesario tener este plugin activo? =

Sí, todos los demás plugins de Convoca requieren Convoca Core activo.

= ¿Requiere Composer? =

Sí, para la generación de PDF con Dompdf.

= ¿El plugin envía datos a servidores externos? =

El plugin incluye un sistema opcional de validación de licencias que contacta con getconvoca.app solo cuando el administrador introduce manualmente una clave de licencia. Sin esta acción, no se realiza ninguna conexión externa.

== Changelog ==

= 2.1.4 =
* Añadido: MANUAL_USUARIO.md con guía completa
* Mejora: Tests unitarios — bootstrap corregido para ejecución standalone
* Mejora: Cobertura de tests aumentada a 65 tests, 235 aserciones
* Dev: Añadido phpstan.neon para análisis estático

= 2.1.3 =
* Fix: Logo en get_branding_html() ahora aplica inline style

= 2.1.2 =
* Fix: Visor de logs protegido contra screen nulo en WP-CLI

= 2.1.1 =
* Fix: Webhook retries no se acumulan infinitamente

= 2.1.0 =
* Nuevo: Dashboard centralizado con métricas, visor de logs, Setup Wizard, Backup/Restore
* Nuevo: Sistema de locks atómico, rate limiting y webhook dedup

= 2.0.0 =
* Primera versión del plugin Convoca Core

== Upgrade Notice ==

= 2.1.4 =
* Tests y documentación mejorados. Compatible con WordPress 7.0.
