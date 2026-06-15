# Matriz de Capacidades — Convoca Ecosystem

## Capacidades personalizadas

| Capacidad | Uso principal | Asignada a |
|-----------|--------------|------------|
| `manage_inscripciones` | Gestionar inscripciones, check-in, monitores de actividad | admin, monitor_actividad |
| `gestionar_miembros` | Gestionar socios, proyectos, horas de voluntariado | admin, shop_manager, monitor_actividad |
| `gestionar_documentos_voluntariado` | Ver/generar documentos PDF (acuerdos, certificados) | admin, shop_manager, monitor_actividad |
| `gestionar_mis_turnos` | Gestionar turnos propios (frontend voluntarios) | voluntario_aprobado |
| `view_reports` | Ver informes y logs | admin, monitor_actividad, shop_manager |
| `manage_convoca_templates` | Gestionar plantillas PDF | admin |
| `manage_convoca_logs` | Acceder al visor centralizado de logs | admin |
| `manage_convoca_gateway` | Configurar pasarela de pago | admin |
| `convoca_shifts_manage_turnos` | Gestionar turnos y voluntarios del centro social | admin, monitor_actividad |
| `convoca_shifts_audit_hours` | Auditoría de horas de voluntariado | admin, monitor_actividad |

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
| Inscripciones (lista) | `convoca-enroll` | `gestionar_miembros` |
| Añadir inscripción | `conv-nueva-inscripcion` | `manage_inscripciones` |
| Check-in | `conv-checkin` | `manage_inscripciones` |
| Actividades (lista) | `conv-actividades` | `gestionar_miembros` |
| Editor actividad | `conv-actividad-editor` | `gestionar_miembros` |
| Informes | `conv-informes` | `manage_options` |
| Logs | `conv-logs` | `manage_options` |
| Configuración | `conv-ajustes` | `manage_options` |
| CRM Monitores | `conv-monitor-crm` | `manage_inscripciones` |
| Evaluaciones | `conv-evaluaciones` | `gestionar_miembros` |

### Members

| Página | Slug | Capacidad |
|--------|------|-----------|
| Lista de Miembros | `conv-members` | `gestionar_miembros` |
| Editor miembro | `conv-member-editor` | `gestionar_miembros` |
| Proyectos | `conv-proyectos` | `gestionar_miembros` |
| Editor proyecto | `conv-proyecto-editor` | `gestionar_miembros` |
| Horas voluntariado | `conv-volunteer-hours` | `gestionar_miembros` |
| Editor horas | `conv-horas-editor` | `gestionar_miembros` |
| Importar CSV | `conv-import-csv` | `manage_options` |
| Logs | `conv-members-logs` | `manage_options` |
| Ajustes | `conv-members-settings` | `manage_options` |
| Webhooks | `conv-webhooks` | `manage_options` |
| Estado | `conv-members-status` | `manage_options` |

### Gateway

| Página | Slug | Capacidad |
|--------|------|-----------|
| Pagos (lista) | `conv-payments` | `manage_options` |
| Generar enlace | `conv-generador` | `manage_options` |
| Ajustes | `conv-settings` | `manage_options` |

### Turnos (Centro Social)

| Página | Slug | Capacidad |
|--------|------|-----------|
| Todos los Turnos | `conv-turnos-list` | `manage_options` |
| Añadir Turno Rápido | `convoca_shifts_turno_rapido` | `manage_options` |
| Generar Turnos | `convoca_shifts_generar_turnos` | `manage_options` |
| Gestionar Voluntarios | `convoca_shifts_voluntarios_pendientes` | `manage_options` |
| Estadísticas | `convoca_shifts_estadisticas` | `manage_options` |
| Ajustes | `convoca_shifts_settings` | `manage_inscripciones` |
| Estado | `convoca_shifts_status` | `manage_options` |

### Common

| Página | Slug | Capacidad |
|--------|------|-----------|
| Panel (Dashboard) | `conv-dashboard` | `manage_options` |
| Logs centralizados | `conv-logs-central` | `manage_convoca_logs` |
| Webhooks | `conv-webhooks-common` | `manage_options` |
| Backup | `conv-backup` | `manage_options` |
| Salud del Sistema | `conv-health` | `manage_options` |
| Asistente | `conv-setup-wizard` | `manage_options` |
| Plantillas PDF | `conv-pdf-templates` | `manage_convoca_templates` |

## Notas importantes

- Todas las páginas admin verifican capacidad mediante `add_submenu_page()` (3er argumento).
- Las exportaciones CSV deben usar la misma capacidad que la página del listado correspondiente.
- `edit_post`/`edit_posts` debe usarse en metaboxes y acciones AJAX sobre un post específico.
- Los nonces se verifican en todos los handlers AJAX (`check_ajax_referer`) y admin POST (`check_admin_referer`/`wp_verify_nonce`).
- Las queries SQL usan `$wpdb->prepare()` en todas las rutas que aceptan input de usuario.
- Los REST endpoints devuelven `WP_REST_Response` con códigos de estado HTTP adecuados.
- El rate limiting protege endpoints públicos (`check_rate_limit`).
