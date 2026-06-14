# Convoca Core Utilities

Plugin base del ecosistema Convoca. Proporciona utilidades compartidas, logging centralizado, webhooks, dashboard, backup/restore, y gestión de capacidades.

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Main Features

- DNI/NIE validation
- Centralized Logger with rotation
- Webhook manager with retries + dedup
- Atomic lock system
- Rate limiter
- Unified Dashboard (Convoca > Panel)
- Centralized Log Viewer (Convoca > Logs)
- Setup Wizard (Convoca > Asistente)
- Backup/Restore (Convoca > Backup)
- System Health (Convoca > Salud)
- Upgrade Manager
- REST API

## Dependencies

WordPress 6.4+, PHP 8.1+

## Webhook Manager

Register and manage webhooks from **Convoca > Webhooks**.

### Available events

| Event | Description |
|-------|-------------|
| `member.created` | New member registered |
| `member.activated` | Member activated |
| `member.suspended` | Member suspended |
| `member.expired` | Membership expired |
| `member.renewed` | Membership renewed |
| `payment.completed` | Payment completed |
| `payment.failed` | Payment failed |
| `payment.reminder_sent` | Payment reminder sent |
| `enrollment.created` | New inscription |
| `enrollment.cancelled` | Inscription cancelled |
| `enrollment.checkin` | Check-in performed |
| `volunteer.hours_logged` | Volunteer hours logged |
| `volunteer.hours_approved` | Volunteer hours approved |

### HMAC signature

If a secret is configured, each request includes `X-Convoca-Signature` header
with `hash_hmac('sha256', $body, $secret)`. Verify on your endpoint:

```php
$computed = hash_hmac('sha256', file_get_contents('php://input'), $secret);
if (hash_equals($computed, $_SERVER['HTTP_X_CONVOCA_SIGNATURE'])) {
    // valid
}
```

### Delivery

- Blocking mode, 15s timeout.
- Deduplication via transient (10s TTL, md5 payload hash).
- Each delivery gets a unique `X-Convoca-Delivery` UUID.
- Retries with exponential backoff: 60s, 120s, 240s (max 3 attempts).
- Delivery logs: last 50 entries per webhook.

### Testing

Click "Test" on any webhook to send a `test.ping` event. The delivery is
logged identically to real events.

## Gestión de Plantillas PDF

Administra las plantillas desde **Ajustes > Plantillas PDF**.

### Plantillas disponibles

| Clave | Descripción |
|-------|-------------|
| `acuerdo_incorporacion` | Acuerdo de Incorporación |
| `anexo_voluntariado` | Anexo de Voluntariado |
| `certificado` | Certificado de voluntariado |
| `desvinculacion` | Acuerdo de Desvinculación |

### Uso

1. Cada plantilla es HTML puro con placeholders `{{nombre}}`, `{{dni}}`,
   `{{fecha}}`, etc. (varían según el documento).
2. Puedes editarlas directamente desde el panel de administración y ver
   los placeholders disponibles en cada una.
3. Al guardar, se crea automáticamente un backup versionado (últimas 5
   versiones).
4. Si la plantilla se corrompe (HTML inválido), se restaura a los valores
   por defecto y se guarda una copia de seguridad.
5. El sistema valida: tamaño máximo 500KB, profundidad DOM < 30, máximo
   10 bloques `<style>`, sin `@import` ni `expression()`.

### Restaurar valores por defecto

Usa el botón "Restaurar valores por defecto" en la página de Plantillas
PDF. Se solicita confirmación antes de proceder.

## Version

2.1.1

## Changelog

### 2.1.1
- **Fix:** Webhook retries ya no se acumulan infinitamente (respeta MAX_RETRIES).
- **Fix:** Fatal error en visor de logs al renderizar en WP-CLI y contextos sin admin screen.

### 2.1.0
- Added atomic lock system and rate limiter
- Added Setup Wizard and Backup/Restore modules
- Added System Health dashboard
- API breaking: Webhook_Manager dispatch signature changed

### 2.0.0
- Major refactor to namespaced classes
- New centralized Logger with DB rotation
- New Webhook Manager with retry + dedup
- REST API endpoints for logs and health

### 1.4.0
- Added BDV_Signature for PDF generation
- Added backward-compat hooks (do_action/apply_filters dual dispatch)
