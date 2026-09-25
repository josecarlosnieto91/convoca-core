# Spec — Copia al administrador (o al monitor) de todos los correos de Convoca

**Estado:** aprobada · **Componentes:** convoca-core (mecanismo), convoca-members y convoca-enroll (puntos de envío)
**Origen:** 2026-09-25. Hoy los correos salen **solo al interesado**: un alta, una renovación, un
certificado o una tarjeta puede emitirse y la asociación no se entera por correo. El panel sí lo
refleja (`Convoca\Core\Notifications`, campana del admin), pero fuera del panel no hay aviso.

## Regla de negocio

**Todo correo que Convoca envía a una persona se notifica también a la asociación:**

- correo de una **actividad** → a su **monitor** (los responsables de la actividad);
- cualquier otro correo → al **correo de administración** configurado.

La notificación es una **copia informativa**: mismo contenido, asunto marcado y cabecera que dice a
quién se le envió. No sustituye al correo del interesado ni cambia su contenido.

## Alcance

**Se hace**
1. Mecanismo único en **convoca-core**: `Convoca\Core\Email_Copy`.
2. Enganche en los **dos** puntos por los que salen todos los correos del ecosistema (verificado:
   Gateway y Shifts no envían correo propio):
   - `convoca-members` → `Email_Manager::send()` (plantillas de socio: alta, bienvenida,
     credenciales, recordatorios, renovación, certificado, tarjeta, voluntariado…).
   - `convoca-enroll` → `Email_Queue::process_queue()` (correos de inscripciones y actividades).
3. Resolución del destinatario, en este orden:
   - actividad con responsables → los **monitores** (usuarios de `_convoca_responsables`);
   - si no hay monitor → correo de administración;
   - correo de administración = `convoca_members_settings['admin_email']` → `get_option('admin_email')`.
4. Interruptor global (activado por defecto) para poder apagarlo sin tocar código.
5. Filtros para que cada sitio ajuste destinatarios y comportamiento:
   `convoca_email_copy_enabled`, `convoca_email_copy_recipients`, `convoca_email_copy_context`.
6. La copia **no lleva los adjuntos** (el aviso indica que el correo original los lleva): duplicar
   certificados y tarjetas en cada aviso engorda la bandeja sin aportar nada.

**No se hace**
- Copias de correos de WordPress ajenos a Convoca (recuperar contraseña, comentarios, avisos de
  actualización): el mecanismo solo lo invocan los plugins de Convoca.
- Copias internas entre destinatarios del mismo correo (un correo que ya va a la administración no
  se copia a sí mismo).
- BCC silencioso: la copia es visible y explicada, para que nadie crea que el correo va dirigido a él.

## Criterios de aceptación

1. Alta de socio → el interesado recibe su correo **y** la administración recibe la copia.
2. Correo de una actividad → la copia va a los **responsables** de esa actividad.
3. Actividad sin responsables → la copia va a la administración (no se pierde).
4. Certificado y tarjeta → la administración recibe el aviso (sin el adjunto).
5. Un correo que ya se envía a la administración **no** se copia a sí mismo (sin duplicados).
6. Interruptor apagado → no se genera ninguna copia; el correo original sale igual.
7. Sin destinatario válido → el correo original sale igual y queda el aviso en el log.
8. `phpunit`, PHPStan, PHPCS y PCP en verde en los tres plugins.
