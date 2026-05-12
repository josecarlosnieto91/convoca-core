# Matriz de Capacidades — Biodevas Ecosystem

## Capacidades personalizadas

| Capacidad | Uso principal | Asignada a |
|-----------|--------------|------------|
| `manage_inscripciones` | Gestionar inscripciones, check-in, monitores de actividad | admin, monitor_actividad |
| `gestionar_miembros` | Gestionar socios, proyectos, horas de voluntariado | admin, shop_manager, monitor_actividad |
| `gestionar_documentos_voluntariado` | Ver/generar documentos PDF (acuerdos, certificados) | admin, shop_manager, monitor_actividad |
| `gestionar_mis_turnos` | Gestionar turnos propios (frontend voluntarios) | voluntario_aprobado |
| `view_reports` | Ver informes y logs | admin, monitor_actividad, shop_manager |
| `manage_biodevas_templates` | Gestionar plantillas PDF | admin |
| `manage_biodevas_logs` | Acceder al visor centralizado de logs | admin |
| `manage_biodevas_gateway` | Configurar pasarela de pago | admin |
| `cst_manage_turnos` | Gestionar turnos y voluntarios del centro social | admin, monitor_actividad |
| `cst_audit_hours` | Auditoría de horas de voluntariado | admin, monitor_actividad |

## Roles existentes

| Rol | Plugins | Capacidades clave |
|-----|---------|-------------------|
| `administrator` | Todos | Todas las capacidades personalizadas + `manage_options` |
| `monitor_actividad` | Enroll, Common, Members | `manage_inscripciones`, `gestionar_miembros`, `gestionar_documentos_voluntariado`, `view_reports` |
| `shop_manager` | Members | `gestionar_miembros`, `gestionar_documentos_voluntariado`, `view_reports` |
| `voluntario_aprobado` | Turnos, Members | `gestionar_mis_turnos`, `read` |

## Mapa de páginas → Capacidad requerida

### Enroll

| Página | Slug | Capacidad |
|--------|------|-----------|
| Inscripciones (lista) | `biodevas-enroll` | `gestionar_miembros` |
| Añadir inscripción | `bde-nueva-inscripcion` | `manage_inscripciones` |
| Check-in | `bde-checkin` | `manage_inscripciones` |
| Actividades (lista) | `bde-actividades` | `gestionar_miembros` |
| Editor actividad | `bde-actividad-editor` | `gestionar_miembros` |
| Informes | `bde-informes` | `manage_options` |
| Logs | `bde-logs` | `manage_options` |
| Configuración | `bde-ajustes` | `manage_options` |
| CRM Monitores | `bde-monitor-crm` | `manage_inscripciones` |
| Evaluaciones | `bde-evaluaciones` | `gestionar_miembros` |

### Members

| Página | Slug | Capacidad |
|--------|------|-----------|
| Lista de Miembros | `bdv-members` | `gestionar_miembros` |
| Editor miembro | `bdv-member-editor` | `gestionar_miembros` |
| Proyectos | `bdv-proyectos` | `gestionar_miembros` |
| Editor proyecto | `bdv-proyecto-editor` | `gestionar_miembros` |
| Horas voluntariado | `bdv-volunteer-hours` | `gestionar_miembros` |
| Editor horas | `bdv-horas-editor` | `gestionar_miembros` |
| Importar CSV | `bdv-import-csv` | `manage_options` |
| Logs | `bdv-members-logs` | `manage_options` |
| Ajustes | `bdv-members-settings` | `manage_options` |
| Webhooks | `bdv-webhooks` | `manage_options` |
| Estado | `bdv-members-status` | `manage_options` |

### Gateway

| Página | Slug | Capacidad |
|--------|------|-----------|
| Pagos (lista) | `bdg-payments` | `manage_options` |
| Generar enlace | `bdg-generador` | `manage_options` |
| Ajustes | `bdg-settings` | `manage_options` |

### Turnos (Centro Social)

| Página | Slug | Capacidad |
|--------|------|-----------|
| Todos los Turnos | `cst-turnos-list` | `manage_options` |
| Añadir Turno Rápido | `cst_turno_rapido` | `manage_options` |
| Generar Turnos | `cst_generar_turnos` | `manage_options` |
| Gestionar Voluntarios | `cst_voluntarios_pendientes` | `manage_options` |
| Estadísticas | `cst_estadisticas` | `manage_options` |
| Ajustes | `cst_settings` | `manage_inscripciones` |
| Estado | `cst_status` | `manage_options` |

### Common

| Página | Slug | Capacidad |
|--------|------|-----------|
| Panel (Dashboard) | `bdv-dashboard` | `manage_options` |
| Logs centralizados | `bdv-logs-central` | `manage_biodevas_logs` |
| Webhooks | `bdv-webhooks-common` | `manage_options` |
| Backup | `bdv-backup` | `manage_options` |
| Salud del Sistema | `bdv-health` | `manage_options` |
| Asistente | `bdv-setup-wizard` | `manage_options` |
| Plantillas PDF | `bdv-pdf-templates` | `manage_biodevas_templates` |

## Notas importantes

- Todas las páginas admin verifican capacidad mediante `add_submenu_page()` (3er argumento).
- Las exportaciones CSV deben usar la misma capacidad que la página del listado correspondiente.
- `edit_post`/`edit_posts` debe usarse en metaboxes y acciones AJAX sobre un post específico.
- Los nonces se verifican en todos los handlers AJAX (`check_ajax_referer`) y admin POST (`check_admin_referer`/`wp_verify_nonce`).
- Las queries SQL usan `$wpdb->prepare()` en todas las rutas que aceptan input de usuario.
- Los REST endpoints devuelven `WP_REST_Response` con códigos de estado HTTP adecuados.
- El rate limiting protege endpoints públicos (`check_rate_limit`).
