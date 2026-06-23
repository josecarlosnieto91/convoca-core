# MANUAL_USUARIO.md — Convoca Core v2.1.3

> Plugin base del ecosistema. Panel de control, logs, webhooks, backup y plantillas PDF.

## 1. Panel de Control (Dashboard)

Accede desde **Convoca → Panel de Control**. Muestra un resumen de:

- Socios activos / totales
- Próximas actividades
- Pagos recientes
- Alertas del sistema

## 2. Salud del Sistema

En **Convoca → Salud del Sistema** encontrarás un diagnóstico completo de todos los plugins Convoca instalados:

- ✅ Versiones de plugins
- ✅ Tablas de base de datos
- ✅ Configuración de cada plugin
- ✅ Conectividad con servicios externos

Usa **Forzar comprobación** para refrescar el diagnóstico.

## 3. Registros (Logs)

**Convoca → Registros** centraliza todos los logs del ecosistema:

- Filtro por plugin (core, enroll, members, gateway, shifts)
- Filtro por nivel (info, warning, error)
- Retención de 90 días (automático)
- Cada entrada muestra fecha, plugin, nivel y mensaje

## 4. Webhooks

**Convoca → Webhooks** permite configurar notificaciones salientes a sistemas externos.

### Eventos disponibles

| Evento | Se dispara cuando |
|--------|-------------------|
| `member.created` | Se da de alta un nuevo socio |
| `member.activated` | Se activa un socio |
| `member.expired` | Una membresía expira |
| `member.renewed` | Se renueva una membresía |
| `payment.completed` | Se completa un pago |
| `enrollment.created` | Nueva inscripción a actividad |
| `volunteer.hours_approved` | Se aprueban horas de voluntariado |

### Configurar un webhook

1. Haz clic en **Añadir webhook**
2. Introduce la URL de destino
3. Selecciona los eventos que quieres recibir
4. Opcional: configura un secreto para firma HMAC
5. Usa **Probar** para enviar un evento de prueba

Cada entrega incluye cabecera `X-Convoca-Signature` con HMAC-SHA256 para verificar autenticidad.

## 5. Plantillas PDF

**Ajustes → Plantillas PDF** gestiona los documentos del sistema:

| Plantilla | Uso |
|-----------|-----|
| Acuerdo de Incorporación | Para nuevos voluntarios |
| Anexo de Voluntariado | Información complementaria |
| Certificado | Certificado de horas |
| Desvinculación | Documento de baja |

Cada plantilla usa placeholders `{{nombre}}`, `{{dni}}`, `{{fecha}}`, etc. Puedes editarlas en HTML desde el panel. El sistema guarda automáticamente un backup de las últimas 5 versiones.

## 6. Backup y Restauración

**Convoca → Backup** permite:

- **Crear backup**: Descarga un ZIP con la base de datos y configuración
- **Restaurar**: Sube un backup anterior para recuperar datos
- Los backups incluyen todas las tablas de plugins Convoca

## 7. Asistente de Configuración

**Convoca → Asistente** guía paso a paso en la configuración inicial:

1. Datos de la asociación
2. Tipos de membresía
3. Configuración de pagos
4. Páginas principales (shortcodes)

## 8. Rate Limiter y Locks

El sistema incluye protección automática:

- **Rate limiter**: Previene abusos en formularios públicos (máx 10 peticiones/minuto por IP)
- **Atomic locks**: Evita condiciones de carrera en inscripciones (sobreventa de plazas)

No requieren configuración por parte del administrador.

## 9. Menú global

Convoca Core crea el menú **Convoca** en la barra lateral del admin. Los demás plugins añaden sus submenús aquí automáticamente.
