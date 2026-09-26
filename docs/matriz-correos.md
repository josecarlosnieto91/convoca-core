# Matriz de correos de Convoca

Qué correo existe, quién lo manda, a quién le llega y por dónde sale.

**Cómo leer la columna «Verificado»:**

- `capturado` — se disparó en un sitio real con un interceptor de `pre_wp_mail` y se revisó el HTML
  que se habría enviado. No se envió nada a nadie.
- `código` — la emisión está leída en el código (fichero y línea), pero **no** se ha capturado el
  correo final. Es un dato pendiente de comprobar, no un hecho verificado.
- `capturado` + `código` — las dos cosas.

## 1. Familias con plantilla editable (Members y Enroll)

Estas dos familias son las únicas que pasan por el sistema de plantillas: el texto vive en una
opción del sitio (`convoca_email_templates` / `convoca_enroll_email_templates`), se puede editar
desde el admin y la migración de versiones lo corrige.

### Members — opción `convoca_email_templates` (13 plantillas)

| Evento | Plantilla | Asunto | Destinatario | Acción del correo | Verificado |
|---|---|---|---|---|---|
| Alta solicitada | `solicitud_recibida` | «Hemos recibido tu solicitud de {tipo_solicitud} — {sitio}» | Persona solicitante | Acceder a Mi Área | capturado |
| Alta confirmada | `bienvenida` | «¡Bienvenido/a, {nombre}! Ya formas parte de {sitio}» | Persona socia | Acceder a Mi Área | capturado |
| Aprobación con acceso | `credenciales_acceso` | «Bienvenido/a a {sitio} — Tus credenciales de acceso» | Persona socia | Acceder a Mi Área | capturado (pendiente de revisar el texto) |
| Cuota pendiente (1/3) | `recordatorio_pago` | «Recordatorio de pago (1/3) — {sitio}» | Persona socia | Pagar ahora | capturado |
| Cuota pendiente (2/3) | `pago_pendiente_2` | «Segundo aviso: pago pendiente — {sitio}» | Persona socia | Pagar ahora | código |
| Cuota pendiente (3/3) | `pago_pendiente_ultimo` | «Último aviso: suspensión inminente — {sitio}» | Persona socia | Pagar ahora | código |
| Renovación a 30 días | `renovacion` | «Tu renovación en {sitio} (30 días)» | Persona socia | Renovar ahora | código |
| Renovación a 15 días | `renovacion_15d` | «Tu renovación en {sitio} (15 días)» | Persona socia | Renovar mi cuota | código |
| Renovación a 7 días | `renovacion_7d` | «Última semana para tu renovación — {sitio}» | Persona socia | Renovar mi cuota | código |
| Renovación automática lanzada | `renovacion_automatica` | «Procesando tu renovación automática — {sitio}» | Persona socia | Ver mi membresía | código |
| Renovación cobrada | `renovacion_completada` | «Renovación completada con éxito — {sitio}» | Persona socia | Ver mi carnet (+ tarjeta PDF adjunta) | capturado |
| Recordatorio de horas | `voluntariado_recordatorio` | «Recuerda tus horas de voluntariado — {sitio}» | Persona voluntaria | (sin botón propio) | código |
| Objetivo de horas cumplido | `objetivo_voluntariado_completado` | «🎉 ¡Felicidades! Has completado tu voluntariado — {sitio}» | Persona voluntaria | Descargar Certificado | capturado |
| Confirmación de email | `confirm_email` | «Confirma tu nuevo email — {sitio}» | Titular del cambio | Confirmar email | código |
| Verificación de teléfono | `verify_phone` | «Verifica tu teléfono — {sitio}» | Titular del cambio | Verificar teléfono | código |

### Enroll — opción `convoca_enroll_email_templates` (11 plantillas)

| Evento | Plantilla | Asunto | Destinatario | Acción | Verificado |
|---|---|---|---|---|---|
| Inscripción recibida | `recepcion` | «Inscripción recibida — {actividad}» | Persona inscrita | Ver mi reserva | capturado |
| Sin plazas | `lista_espera` | «En lista de espera — {actividad}» | Persona inscrita | Ver mi inscripción | capturado |
| Se libera una plaza | `promocion_lista_espera` | «¡Tienes una plaza disponible! — {actividad}» | Persona en espera | Confirmar mi plaza | capturado |
| Plaza confirmada | `confirmacion_plaza` | «¡Plaza confirmada! — {actividad}» | Persona inscrita | Ver mi reserva | capturado |
| Inscripción cancelada | `cancelacion_reserva` | «Reserva cancelada — {actividad}» | Persona inscrita | Gestionar reserva | capturado |
| Faltan 7 días | `recordatorio_7dias` | «¡Esta semana! {actividad}» | Persona inscrita | (sin botón) | capturado |
| Falta 1 día | `recordatorio_24h` | «Recordatorio: {actividad} es mañana» | Persona inscrita | (sin botón) | capturado |
| Falta 1 hora | `recordatorio_1hora` | «{actividad} comienza en 1 hora» | Persona inscrita | (sin botón) | capturado |
| Actividad terminada | `feedback_post` | «¿Qué te pareció "{actividad}"?» | Persona inscrita | (sin botón) | capturado |
| Álbum de fotos creado | `google_photos_album_creado` | «Álbum de fotos para "{actividad}" — {sitio}» | Participantes | Subir fotos | capturado |
| Álbum compartido | `google_photos_album_compartido` | «Fotos de "{actividad}" — {sitio}» | Participantes | Ver fotos | capturado |

Capturadas las 11 en producción el 26/09/2026 con una actividad y una inscripción de prueba: se
disparó el mismo `send()` del plugin y se leyó el HTML de la cola de correo. Comprobado que ninguna
tiene filas sin valor, botones sin destino, placeholders sin sustituir, escapes literales, acentos
rotos ni botón duplicado.

## 2. Correos que NO pasan por el mecanismo común

🔴 **Hallazgo.** Cuatro plugins envían correo con `wp_mail()` directo. Consecuencias, las dos
verificadas en el código desplegado en producción:

1. **La asociación no recibe copia.** `Convoca\Core\Email_Copy` (el ajuste «Enviar copia de los
   correos», marcado por defecto) solo está enganchado en **dos** puntos:
   `convoca-members/includes/Email_Manager.php` y `convoca-enroll/includes/Email_Queue.php`.
   Un correo de turno o de pago **no se copia** a la asociación, así que el ajuste promete una
   cobertura que no da.
2. **Sin la identidad visual de Convoca.** El envoltorio HTML es `Convoca\Core\Email_Layout`, usado
   solo por Members y Enroll. Los correos de abajo salen en texto plano (o HTML suelto), distintos
   del resto del ecosistema.

| Plugin | Correo | Fichero | Destinatario | Copia | Layout |
|---|---|---|---|---|---|
| Shifts | Recordatorio de turno | `includes/cron.php:146` | Voluntario asignado | ❌ | ❌ |
| Shifts | Turno cubierto | `includes/rest-api.php:399` | Administrador | ❌ | ❌ |
| Shifts | Voluntario con faltas | `includes/No_Show_Manager.php:233` | Voluntario | ❌ | ❌ |
| Shifts | Aviso de faltas al admin | `includes/No_Show_Manager.php:259` | Administrador | ❌ | ❌ |
| Gateway | Notificaciones de pago | `includes/Email_Notifications.php:515` | Persona que paga | ❌ | ❌ |
| Core | Digest de seguridad | `includes/Security_Monitor.php:185` | Administrador | ❌ | ❌ |
| Core | Informe de memoria | `includes/Memory_Report.php:80` | Administrador | ❌ | ❌ |
| Publisher | Notificaciones de publicación | `includes/class-notifications.php:102` | Administrador | ❌ | ❌ |
| Publisher | Reintentos de publicación | `includes/class-retry.php:402` | Administrador | ❌ | ❌ |

Nota de criterio: los correos **al administrador** (digest de seguridad, informe de memoria, avisos
de faltas, reintentos de publicación) no necesitan copia — su destinatario **es** la asociación. El
problema real está en los que van **a una persona**: recordatorio de turno, turno cubierto, faltas
del voluntario y notificaciones de pago. Ahí sí falta la copia y el layout.

**Issue:** el mecanismo es de Core, así que el seguimiento vive en `convoca-core`.

## 3. Un punto suelto en todos los correos (corregido, core 2.3.7)

Capturando el HTML real apareció algo que ninguna plantilla mostraba: **todos** los correos del
ecosistema llevaban un `.` pegado a la marca del sitio en la cabecera y otro al final del cuerpo.

Venía de dos `?>.` al final de un `echo` en `Email_Layout::render()`: el `.` quedaba como texto
literal fuera del bloque PHP. No se ve en la plantilla ni en una vista previa del asunto; sí en el
HTML que se envía. Corregido y cubierto por `EmailLayoutRenderTest`, que exige que el layout no
imprima texto propio, **comprobado en negativo**: con el defecto el test falla.

## 4. Qué falta, en corto

- Capturar el HTML real de las 7 plantillas de Members que aún dicen `código` (el resto de la
  familia ya está capturada).
- Decidir el arreglo de los correos fuera del mecanismo (¿un `Core\Mailer` único?) y ejecutarlo.
- Comprobar que las plantillas **almacenadas** coinciden con las del código en **cada** sitio, no
  solo en uno: un sitio con la migración sin correr sigue enviando el texto viejo.
- La cola de correo de Enroll **no se limpia**: en producción quedan 14 filas `sent` de QA de
  sesiones anteriores. No es un fallo de entrega (todas salieron), pero la tabla crece sin límite.
