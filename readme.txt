=== Convoca Core ===
Contributors: josecarlosnietoramos
Tags: common, utilities, logging, webhooks, licenses
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.2.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Base plugin for the Convoca ecosystem. Provides logging, rate limiting, webhooks, license management, and a dashboard for associations and NGOs.

== Description ==

Base plugin for the Convoca ecosystem. Provides shared functionality for all other Convoca plugins:

* Logging system — Centralized logging with levels, context, and automatic purge
* Rate limiting — Abuse protection with atomic UPSERT
* Webhooks — Inbound and outbound webhook management with retries
* Atomic locks — Race condition prevention
* License manager — Local and remote validation (optional external service), PRO features
* Setup Wizard — 6-step configuration assistant
* Centralized Dashboard — System metrics from a single panel
* Log viewer — WP_List_Table with filters
* System Health page — Dependency and configuration diagnostics
* Backup/Restore — ZIP export with import preview
* Shared capabilities — Constants for all Convoca capabilities
* PDF generation — Dompdf integration for certificates and reports

= External services =

This plugin may connect to an external license validation service at `getconvoca.app/api/license.php` to verify PRO license keys. This connection is entirely optional and only occurs when the administrator enters a license key in the admin panel. The plugin works without this connection with all free features.

= Privacy =

This plugin is the technical core of the Convoca ecosystem and provides infrastructure functionality. It does not directly collect personal data, but its centralized logging system may record user and administrator actions that indirectly include personal data (IP addresses, timestamps, performed actions, request content). The rate limiting system temporarily stores IP addresses to prevent abuse.

Logs are stored in the local WordPress database (`wp_convoca_logs` table) and are automatically purged according to the plugin's retention settings (default 30 days). Rate limiting data is automatically cleared when time windows expire.

Webhooks managed by this plugin may send data to externally configured URLs; it is the site administrator's responsibility to review what data is included in webhooks and ensure regulatory compliance.

No personal data is shared with third parties by this plugin.

Users have the right to:
* Request access to logs concerning them
* Request deletion of personal logs (subject to security retentions)
To exercise these rights, contact the site administrator.

== Installation ==

1. Upload the convoca-core folder to /wp-content/plugins/
2. Run composer install inside the plugin directory (required for Dompdf)
3. Activate the plugin from the Plugins menu

== Frequently Asked Questions ==

= Is this plugin required? =

Yes, all other Convoca plugins require Convoca Core to be active.

= Does it require Composer? =

Yes, for PDF generation with Dompdf.

= Does the plugin send data to external servers? =

The plugin includes an optional license validation system that contacts getconvoca.app only when the administrator manually enters a license key. Without this action, no external connection is made.

== Screenshots ==

1. Convoca Dashboard with system metrics
2. Log viewer with filters
3. Setup Wizard step
4. License settings page
5. Backup/Restore panel

== Changelog ==

= 2.2.4 =
* Fix: la contención de locks ahora se reporta al Security Monitor (Utils::acquire_lock) — la alerta de bloqueos recurrentes del digest diario funcionaba sobre un hook que nadie invocaba.
* Fix: el Security Monitor usa rest_post_dispatch, no rest_request_after_callbacks — los 403 por permission_callback (el caso principal de acceso no autorizado) jamás llegaban al monitor con el hook anterior.

= 2.2.3 =
* New: Security Monitor — observabilidad de eventos críticos: registra accesos no autorizados a rutas REST de Convoca (401/403), cuenta en ventanas de 24h los fallos de firma Redsys (Ds_Signature), la contención de locks en convoca_locks y los rate limits excedidos, y envía un digest diario por email con umbrales configurables y cooldown anti-spam.
* CI: PHPUnit/PHPStan/PHPCS bloqueantes en GitHub Actions (antes con || true no detectaban regresiones).

= 2.2.2 =
* Security: fix crítico en acquire_lock — la comparación de expiración usaba $expires (futuro) en vez de time(), permitiendo que dos procesos obtuvieran el mismo lock (exclusión mutua rota; dedup de pagos/cron ineficaz).
* Security: handle_save/handle_complete del wizard ahora exigen manage_options (antes solo nonce).
* Security: ajax_dismiss de notificaciones exige is_user_logged_in().

= 2.2.1 =
* Feat: paso 3 del wizard permite editar el ID corto (slug) de cada plan. Al renombrarlo se migran automáticamente los miembros que lo referencian (_convoca_plan / _convoca_sub_plan). Slugs reservados: familiar, juvenil.

= 2.2.0 =
* Feat: paso 3 del wizard permite editar nombre del plan y modalidad (además de activo y precio).

= 2.1.9 =
* Fix: navegación libre del asistente — los pasos ya recorridos del stepper son clicables (volver a cualquier paso) y el paso 7 Finalización incluye botón Anterior.

= 2.1.8 =
* Fix: paso 3 del wizard permite activar/desactivar planes y editar su precio (se guarda en convoca_members_plans; los selectores Familiar/Juvenil del alta quedan intactos).
* Fix: resumen (paso 7) muestra solo planes activos reales con su precio, sin los selectores de modalidad.

= 2.1.7 =
* Fix: paso 7 Finalización muestra resumen de los 6 pasos configurados (estado ✓/⚠ + detalle) y título "7. Finalización".

= 2.1.6 =
* Fix: wizard Ecosistema detecta módulos con is_plugin_active (antes class_exists con nombres incorrectos → solo veía Members).
* Fix: campos y botones del wizard con clases CSS de WordPress (form-table, regular-text, button).

= 2.1.5 =
* Fix: contador de socios activos en el Panel de Control (comilla SQL faltante en Admin_Analytics).
* Fix: alias SQL en consulta last_7_days de pagos (Notice).
* Fix: shortcode correcto [convoca_alta_socio] en el asistente de configuración.

= 2.1.4 =
* Added: MANUAL_USUARIO.md with complete guide
* Improvement: Unit tests — bootstrap fixed for standalone execution
* Improvement: Test coverage increased to 65 tests, 235 assertions
* Dev: Added phpstan.neon for static analysis

= 2.1.3 =
* Fix: Logo in get_branding_html() now applies inline style

= 2.1.2 =
* Fix: Log viewer protected against null screen in WP-CLI

= 2.1.1 =
* Fix: Webhook retries no longer accumulate indefinitely

= 2.1.0 =
* New: Centralized Dashboard with metrics, log viewer, Setup Wizard, Backup/Restore
* New: Atomic lock system, rate limiting, and webhook dedup

= 2.0.0 =
* First release of the Convoca Core plugin

== Upgrade Notice ==

= 2.1.4 =
* Improved tests and documentation. Compatible with WordPress 7.0.
