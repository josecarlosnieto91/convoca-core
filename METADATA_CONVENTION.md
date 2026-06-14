# Convenciones de Metadatos - Ecosistema Convoca

Este documento establece las convenciones de nomenclatura para los metadatos (postmeta) utilizados en los plugins del ecosistema Convoca.

## Prefijos por Plugin

| Prefijo | Plugin | Uso Principal |
|---------|--------|---------------|
| `_bdv_` | convoca-members | Datos de socios/miembros |
| `_bde_` | convoca-enroll | Inscripciones a actividades |
| `_bdg_` | convoca-gateway | Pagos y transacciones |

## convoca-members (prefijos: `_bdv_`)

### Miembro (post_type: `miembro`)
- `_bdv_email` - Email del socio
- `_bdv_dni` - DNI/NIE del socio
- `_bdv_telefono` - Teléfono de contacto
- `_bdv_fecha_nacimiento` - Fecha de nacimiento
- `_bdv_direccion` - Dirección postal
- `_bdv_estado_miembro` - Estado del socio (activo, pendiente, etc.)
- `_bdv_fecha_alta` - Fecha de alta del socio
- `_bdv_fecha_renovacion` - Fecha de última renovación
- `_bdv_fecha_caducidad` - Fecha de caducidad de la membresía
- `_bdv_tipo_membresia` - Tipo de membresía (anual, mensual, etc.)
- `_bdv_consentimiento_gdpr` - Versión del consentimiento GDPR
- `_bdv_acepta_comunicaciones` - Aceptación de comunicaciones
- `_bdv_is_voluntario` - Indica si es voluntario
- `_bdv_horas_voluntariado` - Horas acumuladas de voluntariado
- `_bdv_access_code` - Código de acceso único

## convoca-enroll (prefijos: `_bde_`)

### Inscripción (post_type: `inscripcion`)
- `_bde_actividad_id` - ID de la actividad
- `_bde_miembro_id` - ID del socio inscrito
- `_bde_estado` - Estado de la inscripción
- `_bde_fecha_inscripcion` - Fecha de inscripción
- `_bde_qr_token` - Token QR para check-in
- `_bde_check_in` - Estado de check-in
- `_bde_fecha_check_in` - Fecha de check-in
- `_bde_pago_id` - ID del pago asociado
- `_bde_needs_manual_review` - Requiere revisión manual
- `_bde_plaza_confirmada` - Plaza confirmada

### Actividad (post_type: `actividad`)
- `_bde_plazas_total` - Total de plazas disponibles
- `_bde_plazas_disponibles` - Plazas disponibles actualmente
- `_bde_fecha_inicio` - Fecha de inicio
- `_bde_fecha_fin` - Fecha de fin
- `_bde_hora` - Hora de la actividad
- `_bde_lugar` - Lugar de la actividad
- `_bde_precio_socio` - Aportación sugerida para socios
- `_bde_precio_socio_dia` - Aportación sugerida para no socios (Trasgus)
- `_bde_es_gratuita` - Indica si es gratuita
- `_bde_edad_minima` - Edad mínima para participar
- `_bde_edad_maxima` - Edad máxima para participar
- `_bde_inscripcion_abierta` - Inscripción abierta/cerrada

## convoca-gateway (prefijos: `_bdg_`)

### Pago (post_type: `pago`)
- `_bdg_origen` - Origen del pago (members, enroll, etc.)
- `_bdg_origen_id` - ID de la entidad origen
- `_bdg_status` - Estado del pago (pending, paid, failed, refunded)
- `_bdg_metodo_pago` - Método de pago utilizado
- `_bdg_importe` - Importe del pago
- `_bdg_order_id` - Order ID de Redsys
- `_bdg_redsys_response` - Código de respuesta de Redsys
- `_bdg_redsys_auth_code` - Código de autorización
- `_bdg_redsys_full_log` - Log completo de la respuesta
- `_bdg_paid_at` - Fecha de pago completado
- `_bdg_created_at` - Fecha de creación del pago
- `_bdg_notifications_sent` - Número de notificaciones enviadas

## Notas

1. **No usar prefijos inconsistentes**: Todos los metadatos deben usar el prefijo correspondiente al plugin que los crea.

2. **Metadatos de solo lectura**: Los metadatos que empiezan con `_` son privados y no se muestran en la UI de WordPress por defecto.

3. **Metadatos booleanos**: Usar `1` o `0` en lugar de `true`/`false` para consistencia con WordPress.

4. **Fechas**: Usar formato MySQL (`Y-m-d H:i:s`) o timestamp UNIX.