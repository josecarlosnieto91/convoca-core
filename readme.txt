=== Convoca Core ===
Contributors: josecarlosnietoramos
Tags: common, utilities, logging, webhooks, licenses
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.1.6
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
