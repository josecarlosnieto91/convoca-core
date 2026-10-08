# Un solo punto de salida para los correos de Convoca

Spec del issue **convoca-core#6**. Escrita antes de tocar código (09/10/2026).

## El problema, en una frase

El ajuste «Enviar copia de los correos» promete avisar a la asociación, pero solo lo cumplen
`convoca-members` y `convoca-enroll`: los correos que Shifts y Gateway mandan **a una persona**
salen por `wp_mail()` directo, sin copia y sin la identidad visual de Convoca.

## Qué ya existe (y no se toca)

- `Convoca\Core\Email_Copy::maybe_copy( $context ): bool` hace **todo** el trabajo de la copia:
  comprueba el ajuste, calcula destinatarios, le pone el prefijo `[Copia]`, antepone el aviso
  «Copia informativa · Este correo se ha enviado a X», evita copiarse a sí misma si el destino ya es
  la asociación, y **envía** con un canal inyectable (`send`), por defecto `wp_mail`.
- `Convoca\Core\Email_Layout::render()` da el envoltorio visual.
- Los dos emisores que ya funcionan (Members, Enroll) lo hacen bien y **no se tocan**: su copia no se
  puede duplicar.

## Diseño: `Convoca\Core\Mailer`

```php
Mailer::send( $to, string $subject, string $body, array $args = array() ): bool
```

`$args`:

| clave | por defecto | para qué |
|---|---|---|
| `plugin` | — | origen del correo; se usa en la copia y en el registro |
| `template` | `''` | nombre legible de la plantilla |
| `entity_id` | `0` | entidad relacionada (entrada, turno, pago) |
| `headers` | `[]` | cabeceras del correo original |
| `attachments` | `[]` | adjuntos (la copia solo avisa de que existen) |
| `layout` | `true` | envolver el cuerpo con `Email_Layout` |
| `copy` | `true` | copiar a la asociación (los correos internos lo ponen a `false`) |
| `channel` | `null` | cómo se envía: `callable( $to, $subject, $body, $headers ): bool`. Sin él, `wp_mail` |

Secuencia de `send()`:

1. Normaliza destinatarios (uno o varios correos válidos).
2. Si el cuerpo no trae etiquetas HTML, lo convierte a párrafos: los avisos de Shifts son texto
   plano con saltos de línea y el envoltorio espera HTML. El texto no se altera.
3. Si `layout` → `Email_Layout::render()`.
4. Envía al destinatario por el canal.
5. Si `copy` → `Email_Copy::maybe_copy()` con el mismo canal. `maybe_copy` ya decide si toca y
   a quién, y no se copia a sí misma si el destino ya es la asociación.
6. Registra con `Logger` el resultado de las dos cosas por separado.

## Migración (solo los correos **a personas**)

| Plugin | Correo | Fichero | Destinatario |
|---|---|---|---|
| Shifts | Recordatorio de turno | `includes/cron.php:146` | Voluntario asignado |
| Shifts | Aviso por faltas | `includes/No_Show_Manager.php:233` | Voluntario |
| Gateway | Notificación de pago | `includes/Email_Notifications.php` (`deliver()`) | Persona que paga |

Los correos **dirigidos al administrador** no se migran en este paso: su destinatario ya es la
asociación (no hay copia que dar) y el valor sería solo el envoltorio. Queda como decisión aparte.

## Criterio de hecho

1. Un correo de turno y uno de pago llegan con el envoltorio de Convoca y con copia a la asociación,
   con el **HTML real** capturado (buzón de pruebas del entorno de desarrollo), no solo el test verde.
2. Con «Enviar copia de los correos» desmarcado, ninguno de los dos se copia y los originales siguen
   saliendo.
3. Members y Enroll siguen igual: sus pruebas siguen pasando y su copia no se duplica.
4. Suite de Core en verde y sin warnings nuevos en `debug.log`.

## Lo que NO hace esta spec

- No cambia los ajustes ni los destinatarios de la copia (`Email_Copy` decide).
- No unifica los correos al administrador.
- No toca el proveedor de correo de Members (`Email_Verifier`): se usa el `channel` solo si alguien lo
  inyecta.
