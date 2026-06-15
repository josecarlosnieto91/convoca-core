# Convenciones de Metadatos - Ecosistema Convoca

Este documento establece las convenciones de nomenclatura para los metadatos (postmeta) utilizados en los plugins del ecosistema Convoca.

## Prefijos por Plugin

| Prefijo | Plugin | Uso Principal |
|---------|--------|---------------|
| `_conv_` | convoca-members | Datos de socios/miembros |
| `_conv_` | convoca-enroll | Inscripciones a actividades |
| `_conv_` | convoca-gateway | Pagos y transacciones |

## convoca-members (prefijos: `_conv_`)

### Miembro (post_type: `miembro`)
- `_conv_email` - Email del socio
- `_conv_dni` - DNI/NIE del socio
- `_conv_telefono` - Teléfono de contacto
- `_conv_fecha_nacimiento` - Fecha de nacimiento
- `_conv_direccion` - Dirección postal
- `_conv_estado_miembro` - Estado del socio (activo, pendiente, etc.)
- `_conv_fecha_alta` - Fecha de alta del socio
- `_conv_fecha_renovacion` - Fecha de última renovación
- `_conv_fecha_caducidad` - Fecha de caducidad de la membresía
- `_conv_tipo_membresia` - Tipo de membresía (anual, mensual, etc.)
- `_conv_consentimiento_gdpr` - Versión del consentimiento GDPR
- `_conv_acepta_comunicaciones` - Aceptación de comunicaciones
- `_conv_is_voluntario` - Indica si es voluntario
- `_conv_horas_voluntariado` - Horas acumuladas de voluntariado
- `_conv_access_code` - Código de acceso único

## convoca-enroll (prefijos: `_conv_`)

### Inscripción (post_type: `inscripcion`)
- `_conv_actividad_id` - ID de la actividad
- `_conv_miembro_id` - ID del socio inscrito
- `_conv_estado` - Estado de la inscripción
- `_conv_fecha_inscripcion` - Fecha de inscripción
- `_conv_qr_token` - Token QR para check-in
- `_conv_check_in` - Estado de check-in
- `_conv_fecha_check_in` - Fecha de check-in
- `_conv_pago_id` - ID del pago asociado
- `_conv_needs_manual_review` - Requiere revisión manual
- `_conv_plaza_confirmada` - Plaza confirmada

### Actividad (post_type: `actividad`)
- `_conv_plazas_total` - Total de plazas disponibles
- `_conv_plazas_disponibles` - Plazas disponibles actualmente
- `_conv_fecha_inicio` - Fecha de inicio
- `_conv_fecha_fin` - Fecha de fin
- `_conv_hora` - Hora de la actividad
- `_conv_lugar` - Lugar de la actividad
- `_conv_precio_socio` - Aportación sugerida para socios
- `_conv_precio_socio_dia` - Aportación sugerida para no socios (Trasgus)
- `_conv_es_gratuita` - Indica si es gratuita
- `_conv_edad_minima` - Edad mínima para participar
- `_conv_edad_maxima` - Edad máxima para participar
- `_conv_inscripcion_abierta` - Inscripción abierta/cerrada

## convoca-gateway (prefijos: `_conv_`)

### Pago (post_type: `pago`)
- `_conv_origen` - Origen del pago (members, enroll, etc.)
- `_conv_origen_id` - ID de la entidad origen
- `_conv_status` - Estado del pago (pending, paid, failed, refunded)
- `_conv_metodo_pago` - Método de pago utilizado
- `_conv_importe` - Importe del pago
- `_conv_order_id` - Order ID de Redsys
- `_conv_redsys_response` - Código de respuesta de Redsys
- `_conv_redsys_auth_code` - Código de autorización
- `_conv_redsys_full_log` - Log completo de la respuesta
- `_conv_paid_at` - Fecha de pago completado
- `_conv_created_at` - Fecha de creación del pago
- `_conv_notifications_sent` - Número de notificaciones enviadas

## Notas

1. **No usar prefijos inconsistentes**: Todos los metadatos deben usar el prefijo correspondiente al plugin que los crea.

2. **Metadatos de solo lectura**: Los metadatos que empiezan con `_` son privados y no se muestran en la UI de WordPress por defecto.

3. **Metadatos booleanos**: Usar `1` o `0` en lugar de `true`/`false` para consistencia con WordPress.

4. **Fechas**: Usar formato MySQL (`Y-m-d H:i:s`) o timestamp UNIX.