# Changelog - Biodevas Common Utilities

## 2.1.2
- **Fix:** Logo en get_branding_html() ahora aplica inline style cuando se usa imagen, permitiendo controlar tamaño desde las llamadas (ej: tarjeta socio).

## 2.1.1
- **Fix:** Webhook retries no se acumulan infinitamente — `process_retries()` respeta `MAX_RETRIES` y elimina entradas agotadas.
- **Fix:** Fatal error en visor de logs al renderizar paginación sin admin screen (WP-CLI). Asegurado que `$this->items` siempre sea array y protegido `display()` contra screen nulo.
- **Mantenimiento:** Limpieza de webhook retries stale en BD.

## 2.1.0
- Nuevo: Dashboard centralizado con métricas (Biodevas > Panel)
- Nuevo: Visor de logs con WP_List_Table, filtros (contexto/nivel/fecha/búsqueda) y purge
- Nuevo: Asistente de Configuración en 6 pasos (Setup Wizard)
- Nuevo: Backup/Restore con exportación ZIP y previsualización de importación
- Nuevo: Página de Salud del Sistema
- Nuevo: Sistema de locks atómico (tabla biodevas_locks + fallback wp_options)
- Nuevo: Rate limiting con UPSERT atómico y almacenamiento empaquetado
- Nuevo: Capacidades centralizadas como constantes en Utils
- Nuevo: Webhook dedup por payload hash (transient 10s)
- Nuevo: Cabecera X-Biodevas-Delivery UUID para idempotencia
- Nuevo: Tabla bdv_member_sequence para asignación atómica de números de socio
- Actualización: Columna 'whatsapp_reminder_sent' añadida a la tabla biodevas_logs (upgrade 1.0.1)
- Seguridad: Bloqueo CRUD en webhooks con acquire_lock/release_lock
- Seguridad: .htaccess + index.php en directorio de importaciones
- Seguridad: TOCTOU eliminado en handle_export (tempnam+unlink → temp dir + random name)
- Seguridad: XSS en visor de logs (alert → modal con data-log-message)
- Seguridad: register_shutdown_function en Upgrade_Manager para evitar locks huérfanos
- Seguridad: $cap_checks corregido (proyectos → manage_inscripciones, no gestionar_miembros)
- Seguridad: wp_date() en purge_30 logs (timezone consistente)
- Rendimiento: update_meta_cache en handle_export (elimina N+1 queries)
- Rendimiento: ID map reemplazado por _bdv_old_import_id postmeta (ahorra RAM)
- Rendimiento: COUNT logs desde information_schema (sin filtros)
- Corrección: Fechas date() → wp_date() en purge
- Corrección: acquire_lock CASE WHEN invertido en fallback wp_options
- Corrección: Duplicado foreach eliminado en handle_import_run
