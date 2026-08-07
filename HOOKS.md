# Convoca Hooks Documentation

Este documento lista los ganchos (hooks) de acción y filtro disponibles en el ecosistema de plugins de Convoca. Se recomienda usar los nombres con prefijo `convoca_` para nuevas integraciones, aunque se mantienen los nombres antiguos por compatibilidad cuando se indica.

---

## Convoca Members

Maneja el ciclo de vida de los socios, planes y voluntariado.

### Acciones

| Hook (Estándar) | Hook (Legado) | Parámetros | Descripción |
| :--- | :--- | :--- | :--- |
| `convoca_members_created` | `convoca_miembro_creado` | `$post_id` | Se dispara cuando se crea un nuevo registro de miembro. |
| `convoca_members_estado_changed` | `convoca_estado_changed` | `$post_id`, `$new_status`, `$old_status` | Se dispara cuando cambia el estado de un miembro (activo, suspendido, etc). |
| `convoca_members_cuota_pagada` | `convoca_miembro_cuota_pagada` | `$origin_id`, `$pago_id` | Se dispara tras confirmar el pago de una cuota en la pasarela. |
| `convoca_members_hours_submitted` | `convoca_hours_submitted` | `$post_id`, `$member_id` | Se dispara cuando un voluntario registra horas desde el panel. |
| `convoca_members_hora_aprobada` | — | `$record_id`, `$miembro_id` | Se dispara cuando un administrador aprueba un registro de horas. |
| `convoca_members_hora_rechazada` | — | `$record_id`, `$miembro_id` | Se dispara cuando un administrador rechaza un registro de horas. |
| `convoca_members_unsubscribe_request` | `convoca_member_unsubscribe_request` | `$member_id` | Se dispara cuando un miembro solicita la baja desde su panel. |
| `convoca_members_payment_reminder_sent` | — | `$post_id`, `$reminder_key`, `$days_diff` | Se dispara tras enviar un email de recordatorio de pago. |
| `convoca_members_renewal_reminder_sent` | — | `$post_id`, `$days` | Se dispara tras enviar un email de aviso de renovación. |
| `convoca_members_auto_renewal_created` | — | `$member_id`, `$pago_id` | Se dispara al generar un pago recurrente automático. |
| `convoca_members_membership_expired` | — | `$member_id` | Se dispara cuando una membresía vence por falta de pago/renovación. |

### Emails (Notificaciones)

| Hook (Estándar) | Hook (Legado) | Parámetros | Descripción |
| :--- | :--- | :--- | :--- |
| `convoca_members_email_solicitud` | `convoca_email_solicitud` | `$post_id` | Envía email de solicitud recibida. |
| `convoca_members_email_bienvenida` | `convoca_email_bienvenida` | `$post_id` | Envía email de bienvenida (activación). |
| `convoca_members_email_recordatorio_pago` | `convoca_email_recordatorio_pago` | `$post_id` | Envía recordatorio de pago pendiente. |

---

## Convoca Enroll

Gestión de inscripciones a actividades y control de asistencia.

### Acciones

| Hook (Estándar) | Hook (Legado) | Parámetros | Descripción |
| :--- | :--- | :--- | :--- |
| `convoca_enroll_inscripcion_nueva` | `convoca_inscripcion_nueva` | `$post_id`, `$actividad_id`, `$estado` | Se dispara al recibir una nueva inscripción. |
| `convoca_enroll_inscripcion_confirmada` | `convoca_inscripcion_confirmada` | `$inscripcion_id`, `$actividad_id` | Se dispara cuando una inscripción pasa a confirmada. |
| `convoca_enroll_inscripcion_cancelada` | `convoca_inscripcion_cancelada` | `$inscripcion_id`, `$actividad_id` | Se dispara al cancelar una inscripción. |
| `convoca_enroll_inscripcion_promovida` | `convoca_inscripcion_promovida` | `$target_id`, `$actividad_id` | Se dispara al mover a alguien de lista de espera a titular. |
| `convoca_enroll_asistencia_cambiada` | `convoca_asistencia_cambiada` | `$inscripcion_id`, `$asistencia` | Se dispara al marcar asistencia ('si'/'no'). |
| `conv_after_horas_voluntario_actualizadas` | `conv_horas_voluntario_actualizadas` | `$user_id`, `$hours` | Se dispara tras actualizar el contador global de horas de un voluntario. |
| `conv_evaluacion_completada` | — | `$eval_id`, `$act_id`, `$user_id` | Se dispara tras completar el formulario de evaluación de actividad. |

### Filtros

| Filtro | Parámetros | Descripción |
| :--- | :--- | :--- |
| `convoca_enroll_aportacion_label` | `$label`, `$context` | Permite personalizar el texto de "Aportación" según el contexto. |

---

## Convoca Gateway

Pasarela de pagos centralizada (Redsys/Bizum/Efectivo).

### Acciones

| Hook (Estándar) | Hook (Legado) | Parámetros | Descripción |
| :--- | :--- | :--- | :--- |
| `convoca_gateway_payment_completed` | `convoca_payment_completed` | `$pago_id`, `$origin`, `$origin_id`, `$meta` | Pago completado con éxito (Redsys/Bizum). |
| `convoca_gateway_payment_failed` | `convoca_payment_failed` | `$pago_id`, `$code` | Pago fallido o cancelado. |
| `convoca_gateway_payment_success` | — | `$id` | Confirmación manual de pago por administrador. |
| `convoca_gateway_payment_refunded` | — | `$id` | Pago marcado como reembolsado. |
| `convoca_gateway_resend_email` | — | `$id` | Solicitud de reenvío de email de confirmación de pago. |

---

## Convoca Shifts

Gestión de turnos y disponibilidad del local.

### Acciones

| Hook | Parámetros | Descripción |
| :--- | :--- | :--- |
| `conv_voluntario_aprobado` | `$user_id` | Se dispara cuando un voluntario es aprobado. |

### Filtros

| Filtro | Parámetros | Descripción |
| :--- | :--- | :--- |
| `convoca_shifts_confirm_signup` | `$confirm` | Permite desactivar la confirmación manual al apuntarse a un turno. |
| `conv_voluntario_aprobado_attachments` | `$attachments`, `$user_id` | Permite añadir archivos (ej. PDFs) al email de aprobación de voluntario. |

---

## Convoca Core

Utilidades y lógica compartida.

### Filtros

| Filtro | Parámetros | Descripción |
| :--- | :--- | :--- |
| `convoca_common_webhook_sslverify` | `$verify` | Desactiva verificación SSL en webhooks (true por defecto). |
| `convoca_members_sensitive_meta` | `$fields` | Lista de metadatos sensibles para ocultar en logs de auditoría. |
| `conv_pdf_html_safe_keys` | `$fields` | Lista de claves de datos para PDFs que pueden contener HTML seguro. |
