# Biodevas Hooks Documentation

Este documento lista los ganchos (hooks) de acción y filtro disponibles en el ecosistema de plugins de Biodevas. Se recomienda usar los nombres con prefijo `biodevas_` para nuevas integraciones, aunque se mantienen los nombres antiguos por compatibilidad cuando se indica.

---

## Biodevas Members

Maneja el ciclo de vida de los socios, planes y voluntariado.

### Acciones

| Hook (Estándar) | Hook (Legado) | Parámetros | Descripción |
| :--- | :--- | :--- | :--- |
| `biodevas_members_created` | `biodevas_miembro_creado` | `$post_id` | Se dispara cuando se crea un nuevo registro de miembro. |
| `biodevas_members_estado_changed` | `biodevas_estado_changed` | `$post_id`, `$new_status`, `$old_status` | Se dispara cuando cambia el estado de un miembro (activo, suspendido, etc). |
| `biodevas_members_cuota_pagada` | `biodevas_miembro_cuota_pagada` | `$origin_id`, `$pago_id` | Se dispara tras confirmar el pago de una cuota en la pasarela. |
| `biodevas_members_hours_submitted` | `biodevas_hours_submitted` | `$post_id`, `$member_id` | Se dispara cuando un voluntario registra horas desde el panel. |
| `biodevas_members_hora_aprobada` | — | `$record_id`, `$miembro_id` | Se dispara cuando un administrador aprueba un registro de horas. |
| `biodevas_members_hora_rechazada` | — | `$record_id`, `$miembro_id` | Se dispara cuando un administrador rechaza un registro de horas. |
| `biodevas_members_unsubscribe_request` | `biodevas_member_unsubscribe_request` | `$member_id` | Se dispara cuando un miembro solicita la baja desde su panel. |
| `biodevas_members_payment_reminder_sent` | — | `$post_id`, `$reminder_key`, `$days_diff` | Se dispara tras enviar un email de recordatorio de pago. |
| `biodevas_members_renewal_reminder_sent` | — | `$post_id`, `$days` | Se dispara tras enviar un email de aviso de renovación. |
| `biodevas_members_auto_renewal_created` | — | `$member_id`, `$pago_id` | Se dispara al generar un pago recurrente automático. |
| `biodevas_members_membership_expired` | — | `$member_id` | Se dispara cuando una membresía vence por falta de pago/renovación. |

### Emails (Notificaciones)

| Hook (Estándar) | Hook (Legado) | Parámetros | Descripción |
| :--- | :--- | :--- | :--- |
| `biodevas_members_email_solicitud` | `biodevas_email_solicitud` | `$post_id` | Envía email de solicitud recibida. |
| `biodevas_members_email_bienvenida` | `biodevas_email_bienvenida` | `$post_id` | Envía email de bienvenida (activación). |
| `biodevas_members_email_recordatorio_pago` | `biodevas_email_recordatorio_pago` | `$post_id` | Envía recordatorio de pago pendiente. |

---

## Biodevas Enroll

Gestión de inscripciones a actividades y control de asistencia.

### Acciones

| Hook (Estándar) | Hook (Legado) | Parámetros | Descripción |
| :--- | :--- | :--- | :--- |
| `biodevas_enroll_inscripcion_nueva` | `biodevas_inscripcion_nueva` | `$post_id`, `$actividad_id`, `$estado` | Se dispara al recibir una nueva inscripción. |
| `biodevas_enroll_inscripcion_confirmada` | `biodevas_inscripcion_confirmada` | `$inscripcion_id`, `$actividad_id` | Se dispara cuando una inscripción pasa a confirmada. |
| `biodevas_enroll_inscripcion_cancelada` | `biodevas_inscripcion_cancelada` | `$inscripcion_id`, `$actividad_id` | Se dispara al cancelar una inscripción. |
| `biodevas_enroll_inscripcion_promovida` | `biodevas_inscripcion_promovida` | `$target_id`, `$actividad_id` | Se dispara al mover a alguien de lista de espera a titular. |
| `biodevas_enroll_asistencia_cambiada` | `biodevas_asistencia_cambiada` | `$inscripcion_id`, `$asistencia` | Se dispara al marcar asistencia ('si'/'no'). |
| `bdv_after_horas_voluntario_actualizadas` | `bdv_horas_voluntario_actualizadas` | `$user_id`, `$hours` | Se dispara tras actualizar el contador global de horas de un voluntario. |
| `bdv_evaluacion_completada` | — | `$eval_id`, `$act_id`, `$user_id` | Se dispara tras completar el formulario de evaluación de actividad. |

### Filtros

| Filtro | Parámetros | Descripción |
| :--- | :--- | :--- |
| `biodevas_enroll_aportacion_label` | `$label`, `$context` | Permite personalizar el texto de "Aportación" según el contexto. |

---

## Biodevas Gateway

Pasarela de pagos centralizada (Redsys/Bizum/Efectivo).

### Acciones

| Hook (Estándar) | Hook (Legado) | Parámetros | Descripción |
| :--- | :--- | :--- | :--- |
| `biodevas_gateway_payment_completed` | `biodevas_payment_completed` | `$pago_id`, `$origin`, `$origin_id`, `$meta` | Pago completado con éxito (Redsys/Bizum). |
| `biodevas_gateway_payment_failed` | `biodevas_payment_failed` | `$pago_id`, `$code` | Pago fallido o cancelado. |
| `biodevas_gateway_payment_success` | — | `$id` | Confirmación manual de pago por administrador. |
| `biodevas_gateway_payment_refunded` | — | `$id` | Pago marcado como reembolsado. |
| `biodevas_gateway_resend_email` | — | `$id` | Solicitud de reenvío de email de confirmación de pago. |

---

## Centro Social Turnos

Gestión de turnos y disponibilidad del local.

### Acciones

| Hook | Parámetros | Descripción |
| :--- | :--- | :--- |
| `bdv_voluntario_aprobado` | `$user_id` | Se dispara cuando un voluntario es aprobado para el Centro Social. |

### Filtros

| Filtro | Parámetros | Descripción |
| :--- | :--- | :--- |
| `cst_confirm_signup` | `$confirm` | Permite desactivar la confirmación manual al apuntarse a un turno. |
| `bdv_voluntario_aprobado_attachments` | `$attachments`, `$user_id` | Permite añadir archivos (ej. PDFs) al email de aprobación de voluntario. |

---

## Biodevas Common

Utilidades y lógica compartida.

### Filtros

| Filtro | Parámetros | Descripción |
| :--- | :--- | :--- |
| `biodevas_common_webhook_sslverify` | `$verify` | Desactiva verificación SSL en webhooks (true por defecto). |
| `biodevas_members_sensitive_meta` | `$fields` | Lista de metadatos sensibles para ocultar en logs de auditoría. |
| `bdv_pdf_html_safe_keys` | `$fields` | Lista de claves de datos para PDFs que pueden contener HTML seguro. |
