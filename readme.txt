=== Convoca Core ===
Contributors: josecarlosnietoramos
Tags: common, utilities, logging, validation, webhooks, licenses
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.1.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Funcionalidades comunes, utilidades, logging y validación para el ecosistema Convoca.

== Description ==

Plugin base del ecosistema Convoca. Proporciona funcionalidades compartidas para todos los demás plugins Convoca:

* Sistema de logging — Registro centralizado con niveles, contexto y purga automática
* Rate limiting — Protección contra abusos con UPSERT atómico
* Webhooks — Gestión de webhooks entrantes y salientes con reintentos
* Locks atómicos — Prevención de race conditions (tabla dedicada + fallback wp_options)
* Gestor de licencias — Validación local y remota, funciones PRO
* Setup Wizard — Asistente de configuración en 6 pasos
* Dashboard centralizado — Métricas del sistema desde un solo panel
* Visor de logs — WP_List_Table con filtros (contexto, nivel, fecha, búsqueda)
* Página de Salud del Sistema — Diagnóstico de dependencias y configuración
* Backup/Restore — Exportación ZIP con previsualización de importación
* Capacidades centralizadas — Constantes para todas las capabilities de Convoca
* Generación de PDF — Integración con Dompdf para certificados y memorias

== Installation ==

1. Sube la carpeta convoca-core a /wp-content/plugins/
2. Ejecuta composer install dentro del directorio del plugin (requerido para Dompdf)
3. Activa el plugin desde el menú Plugins

== Frequently Asked Questions ==

= ¿Es necesario tener este plugin activo? =

Sí, todos los demás plugins de Convoca requieren Convoca Core activo.

= ¿Requiere Composer? =

Sí, para la generación de PDF con Dompdf.

== Changelog ==

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
* Primera versión del plugin Convoca Core (refactorizado desde Biodevas Common)

== Upgrade Notice ==

= 2.1.3 =
Actualización de mantenimiento.
