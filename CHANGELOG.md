## [2.3.33] — 2026-10-15

### Cambiado
- **`Utils::get_payment_link()` se llama `create_payment()` (issue #16).** Tenía **el mismo nombre** que
  `Gateway\Payment_Handler::get_payment_link()` —hoy `build_payment_url()`— y no hacen lo mismo: esta **crea**
  el pago y devuelve un array o un `WP_Error`; aquella **construye la URL** de un pago que ya existe. Dos cosas
  distintas, con el mismo nombre, en el camino del cobro: quien llamaba a una creyendo llamar a la otra no lo
  descubría hasta que fallaba.
  - El nombre viejo queda como **alias de compatibilidad** que delega: es API publicada y ninguna integración
    existente tiene por qué romperse. Sin cambios de comportamiento.

## [2.3.32] — 2026-10-15

### Añadido
- **El sitio deja de anunciar la versión de PHP en cada respuesta (issue #11).** La demo enviaba
  `x-powered-by: PHP/8.5.10` en todas las cabeceras: no es explotable por sí mismo, pero es información regalada
  a quien esté buscando qué atacar. `Convoca\Core\Postura_HTTP` la retira en `send_headers` —antes de que se
  envíe nada, que es cuando quitarla sirve de algo— y **no toca la `Content-Security-Policy`**, que sigue en modo
  informe por decisión tomada: pasarla a aplicada exige revisar antes que nada legítimo se rompa (la política
  lleva `unsafe-inline` y `unsafe-eval`, y el sitio usa bloques y scripts propios).

### Pruebas
- `tests/Unit/PosturaHTTPTest.php`: que la retirada esté en `send_headers`, que esté enganchada (una clase que
  nadie llama no quita nada) y que **no** se toque la CSP. La comprobación de la CSP mira el **código sin
  comentarios**: un comentario que la menciona no es tocar la CSP. **Negativos probados**: sin el enganche cae
  una prueba; metiendo una cabecera CSP en la clase, caen dos.
- Suite 212 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.3.31] — 2026-10-15

### Arreglado
- **La desinstalación no borraba todas las opciones del plugin (issue #10).** Borraba lo principal y se dejaba
  tres que él mismo crea. La clave de las claves se **lee del código**, no de una lista escrita a mano —una lista
  así se queda vieja en cuanto alguien añade una opción, que es exactamente cómo apareció el defecto—, y al
  hacerlo aparecieron **siete**, no una:
  - `convoca_log_retention_days` (la del informe), `convoca_document_theme` y `convoca_utils_fired` → se borran.
  - `convoca_members_plans`, `convoca_gateway_settings`, `convoca_shifts_hora_apertura` y `convoca_shifts_hora_cierre`
    → **no se borran, y es lo correcto**: son ajustes de **otros** plugins de la familia. Borrarlas desde aquí
    significaría perder los ajustes de un plugin que sigue instalado. Van como excepciones documentadas en la
    prueba, con su motivo.

### Pruebas
- `tests/Unit/OpcionesSeBorranAlDesinstalarTest.php`, hermano del de ganchos programados: toda opción que el
  plugin crea tiene que borrarse al desinstalarlo. **Negativo probado**: quitando una opción del desinstalador,
  la prueba cae y **la nombra**.
- Suite 207 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.3.30] — 2026-10-15

### Arreglado
- **Las copias de ajustes y plantillas ya no crecen sin límite (issue #9).** Antes de sobrescribir unos ajustes
  el plugin guardaba una copia —está bien guardarla—, pero en una **opción nueva** con la fecha en el nombre y
  **nunca** la borraba: cada importación o restauración dejaba una más. En la demo había cuatro; en un sitio que
  importe ajustes con frecuencia eso crece sin techo, y cada copia lleva dentro los ajustes completos con sus
  plantillas de correo.
  - Las copias viven ahora en **una sola opción por ajuste** (`…_backups`), con **las cinco últimas** —lo
    acordado—, de la más reciente a la más antigua. Es el patrón que el propio plugin ya usaba para las
    plantillas (`create_versioned_backup()`): no se poda lo que crece sin fin, se deja de crear opciones sin fin.
  - `Convoca\Core\Backups::anadir()` y `::de()` son el único sitio donde se guardan y se leen, así que los dos
    puntos que las creaban (`Admin_Backup` y `Admin_Templates`) dicen lo mismo y no pueden divergir.

### Pruebas
- `tests/Unit/CopiasConRetencionTest.php`: que se conserven las cinco acordadas, que lo que sobreviva sea lo más
  reciente y que no se cree una opción por copia. **Negativos probados**: quitando la poda caen dos pruebas;
  guardando por el final en vez de por el principio, cae una.
- Suite 206 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.3.29] — 2026-10-15

### Añadido
- **Origen `responsable` en el libro de horas.** Las horas de una actividad pueden acreditarse a quien la
  imparte sin estar inscrito (enroll#19), y el libro identifica cada registro por origen + `origen_id`; aquí el
  hecho es «esta persona en esta actividad», así que el id codifica los dos (`actividad × 1.000.000 + usuario`).

# Changelog — convoca-core

## [2.3.28] - 2026-10-15

### Corregido

- **El asistente buscaba las páginas del sistema solo por su slug** (#14): una instalación cuya página de
  socio se llame de otra forma recibía un aviso permanente de que no existía, y al pulsar «Crear páginas»
  el asistente creaba una SEGUNDA página con el mismo shortcode, dejando dos áreas de socio en el mismo
  sitio. Ahora la página se reconoce por su slug **o por el shortcode que lleva dentro**, en los cuatro
  puntos donde se resolvía, así que deja de avisar en falso y de duplicar.
- **Restos de la migración de prefijo** (#12): quedaban tablas `wp_conv_*` junto a sus `wp_convoca_*`
  equivalentes, una con datos. El paso 2.3.28 las retira, y lo hace de forma conservadora: solo borra la
  vieja cuando puede demostrar que la nueva tiene al menos tantas filas; si la vieja tiene más, no la toca
  y lo deja anotado en el log para que lo mire una persona.

## [2.3.27] - 2026-10-15

### Corregido

- **El QR del carnet apuntaba a una página que no existía** (auditoría de navegador): el carnet digital
  imprime un código que lleva a `/verificar-socio/`, y esa página no la creaba nadie —404—, así que
  verificar un carnet era imposible. Las dos páginas de verificación (`verificar-socio` y
  `verificar-certificado`) entran en la lista del asistente de puesta en marcha, que ya avisa si el
  shortcode no está registrado.

## [2.3.26] — 2026-10-14

### Arreglado
- **Ningún correo del ecosistema sale sin remitente.** El auditor independiente capturó 83 correos de
  los sitios y **69 salían sin cabecera `From`**: el `Mailer` arrancaba con `headers => []` y solo añadía
  el `Content-Type`, así que el remitente quedaba en manos de PHP (`wordpress@localhost`), que muchos
  servidores rechazan — y el registro decía «enviado», así que nadie se enteraba.
  Ahora el remitente se pone en el **punto de salida único** (`Mailer::from_header()`), no en cada
  plugin: siete sitios donde olvidarlo son siete sitios donde se olvida. Orden del respaldo: ajuste del
  sitio → correo de administración → `noreply@<dominio del sitio>`. Y ya no puede salir
  `From:  <correo>` cuando el ajuste del nombre está guardado en blanco.
- Filtro nuevo `convoca_email_from` para cambiar el remitente de todo el ecosistema de una vez.
- **Prueba:** `tests/Unit/MailerRemitenteTest.php` (5 casos) con dos negativos comprobados: quitando el
  bloque que añade el `From` fallan 4 comprobaciones; rompiendo el respaldo del nombre en blanco falla 1.

## [2.3.25] - 2026-10-14

### Retirado

- **`assets/js/convoca-monitor.js`** (código muerto, pasada 9 del gauntlet). Comprobado antes de
  borrarlo: **ningún PHP lo encola** (ni por nombre de fichero ni por handle), **no existe el HTML
  que necesita** (`#conv-live-dot`, `.conv-metric-card`, `#conv-monitor-label` no se pintan en
  ninguna parte) y no lo carga nada fuera del repo —ni la batería del ecosistema, ni la
  documentación, ni los mu-plugins de demo, Lugg y biodevas—. Era el resto de un panel de métricas
  cuya parte de servidor (la ruta `/convoca/v1/admin/metrics`) sigue viva y en uso.

  El endpoint **no se toca**: está en la API congelada (`docs/openapi.yaml`), en el inventario de la
  batería y lo consulta `observability.php`. Lo que sobraba era solo el fichero que no cargaba nadie.

### Pruebas

- La comprobación de «ningún recurso viaja sin que nadie lo use» entra en la batería del ecosistema
  (`convoca-health-check`), que es donde vive lo que afecta a más de un plugin.

## v2.3.18 (2026-10-13)

### Arreglado
- **Los fallos de las llamadas AJAX decían «Error desconocido.» o hablaban en inglés.** La causa no
  estaba en los llamadores: `conv.ajaxPost` / `convAdmin.ajaxPost` no traducían las respuestas de
  protocolo de WordPress (`0` = acción inexistente, `-1` = nonce caducado, que llegan como JSON
  válido, así que el llamador recibía un número) y devolvían frases que el usuario no puede
  seguir: `Authentication required. Please log in.`, `Service temporarily unavailable.`,
  `Redirect detected. Please log in.` y el texto técnico
  `Error HTTP Fetch al portal wp-admin /admin-ajax.php`; además, un cuerpo no-JSON se pintaba tal
  cual en pantalla.
- Ahora hay **un único punto** que traduce el fallo a un texto que dice QUÉ HACER
  (`conv.mensajes` + `conv.normalizarError`, y su gemelo `convAdmin.mensajes` /
  `convAdmin.normalizarError` para la administración), y los textos tienen **fuente única en PHP**
  (`convoca_common_js_strings()`, envueltos en `__()`), inyectados por `wp_localize_script` en
  `convoca-common-js` (`window.convocaI18n`) y `convoca-common-admin-js`
  (`window.convocaAdminI18n`).
- Prueba de contrato `tests/Unit/MensajesDeErrorTest.php`: los textos retirados no vuelven, los
  dos bundles traducen `0` y `-1`, el PHP declara las mismas claves que el JS usa y los dos
  handles las reciben. Probada **en negativo** (con el fichero de HEAD falla y lo nombra).

## v2.3.17 (2026-10-12)

### Cambiado
- **Los dobles de `register_taxonomy` y `register_post_type` ahora guardan la llamada**
  (`$GLOBALS['_wp_taxonomias_registradas']` / `['_wp_post_types_registrados']`), como ya
  hacia `_wp_cron`. Permite comprobar los **argumentos** del registro, no solo que se
  registre: hay defectos que solo se ven ahi. El primero que se caza asi, en convoca-members:
  una taxonomia declarada con `public => false` y sin `show_ui` deja su pantalla de terminos
  en HTTP 500 en WordPress 7.

## v2.3.16 (2026-10-12)

### Arreglado
- **PHPCS a 0 avisos y 0 errores** en todo el ecosistema, sin el silenciador
  `ignore_warnings_on_exit` en el CI. Entre los avisos habia defectos reales:
  un `var_dump` alcanzable por URL (`?debug=1`) que volcaba los metadatos de una
  inscripcion sin escapar, una clase `CLI_Webhooks` declarada en dos ficheros
  (fatal si se cargaban ambos) y 30 `wp_redirect` que pasan a `wp_safe_redirect`.
- 11 `@` que ocultaban errores de PHP, sustituidos por comprobaciones reales: un `@`
  no arregla nada, esconde el sintoma y deja el registro de avisos mintiendo.
- 14 `error_log()` que solo escribian en el log del servidor pasan al `Logger` del
  ecosistema, que ademas los muestra en el panel.
- `urlencode()` -> `rawurlencode()` en 4 sitios (en una query string, `urlencode`
  codifica el espacio como `+`), `in_array` con comparacion estricta, comparaciones
  `===`, `current_time('timestamp')` y `serialize()` sustituido por `wp_json_encode`.

### Notas
- Sin cambios de comportamiento en los flujos de usuario: lo arreglado son avisos
  del analizador, seguridad de redirecciones y registro de errores.

## v2.3.15 (2026-10-11)

### Arreglado
- **Regresión cazada midiendo en los sitios reales**: el arreglo anterior de contraste puso
  `#b05500` como fallback GLOBAL del fondo del botón primario. En Lugg, cuyo tema **no declara la
  paleta de Convoca** y en cambio **ajusta el texto del botón a su uva oscuro** (`color:#320028`),
  eso bajó el contraste de **7.47:1 a 3.55:1** — un arreglo en la demo que rompía producción.
  Ahora los fallbacks van **encadenados**: donde la paleta declare `naranja-oscuro` se usa, y donde
  no se conserva el naranja de marca de siempre, que es el que esos temas esperan. Igual en el
  `:hover` (`naranja-oscuro-hover` → `naranja-intenso`).
- Lección: un color de la paleta de Convoca **no existe** en los sitios con tema propio; el
  fallback hay que elegirlo pensando en el sitio que NO define la variable, no en el que sí.

## v2.3.14 (2026-10-11)

### Arreglado
- **Contraste de los botones (WCAG 2.1 AA)** en `convoca-common.css`, medido con axe:
  - `.convoca-btn-primary`: texto blanco sobre `#ff8700` = **2.41:1** (mínimo 4.5:1). Pasa a
    `naranja-oscuro` (`#b05500`, **5.07:1**); el hover, de `#ff500a` (3.28:1) a `#8f4400` (**7.01:1**).
    Afectaba a **todos** los botones primarios del ecosistema.
  - `.convoca-btn-outline`: el texto y el borde en naranja brillante no llegaban ni al 4.5:1 del
    texto (1.4.3) ni al 3:1 del límite del control (1.4.11). Pasan a naranja-oscuro. **axe no lo
    veía** porque el botón «← Atrás» está oculto hasta avanzar de paso: lo encontró el recorrido.
  - `.convoca-check-group a`: mismo caso en el texto de los enlaces.
- Cubierto por `tests/Unit/BotonPrimarioContrasteTest.php`, probado **en negativo**.

## v2.3.13 (2026-10-11)

### Corregido
- **La página de Salud del núcleo registraba un aviso de deprecación en cada carga con PHP 8.5.** `includes/admin-global-menu.php` llamaba a `ReflectionMethod::setAccessible( true )` dos veces (para leer los chequeos de sistema de Members y de Enroll). Ese método está **obsoleto desde PHP 8.5** y además era innecesario: desde PHP 8.1 todas las propiedades y métodos son accesibles por reflexión sin marcar nada. Se eliminan las dos llamadas, que es lo que pide el propio PHP (no un `@` ni un silenciador). Visto en la demo, que corre PHP 8.5.10; en Lugg (PHP 8.4) no se manifestaba, y por eso hay que medir en los tres sitios.

## v2.3.12 (2026-10-11)

### Corregido
- **Los oyentes internos del núcleo escuchaban nombres de hook retirados.** `Notifications`
  registraba `convoca_payment_failed` y `convoca_hours_submitted`, dos nombres que el
  ecosistema retiró en la 1.1.0 a favor de `convoca_gateway_payment_failed` y
  `convoca_members_hours_submitted`. Como `Utils::do_action()` dispara el nombre nuevo y,
  si queda alguien en el viejo, lo dispara también con aviso, cada pago fallido dejaba una
  línea `PHP Deprecated` en el log y la campanita de avisos se alimentaba por un camino
  obsoleto. Ahora se registran solo los nombres nuevos.

## v2.3.11 (2026-10-09)

### Añadido
- **`Convoca\Core\Mailer`**: un único punto de salida para los correos que van a personas. Envuelve
  el cuerpo con `Email_Layout`, saca copia con `Email_Copy` y entrega por el canal que se le pase
  (`wp_mail` por defecto). Ya lo usan los tres correos a personas del ecosistema: recordatorio de
  turno y aviso por faltas (Shifts) y notificación de pago (Gateway).

### Corregido
- **La copia de un correo no heredaba el remitente del original.** Cuando el correo fijaba su propio
  `From`, la copia caía al remitente por defecto del sitio y el servidor la rechazaba: la asociación
  no se enteraba aunque el registro dijera que se había intentado. La copia hereda ya el remitente.
- **Los avisos por correo al administrador pasan por el punto único** con la identidad visual común y
  **sin copia** (`copy => false`): su destinatario ya es la asociación, así que copiarlos sería
  duplicarlos.
- **Enlaces muertos del panel de control.** El listado de pagos se registra sin interfaz de
  administración (`show_ui=false`), así que `edit.php?post_type=pago` devuelve un error, y el slug de
  inscripciones es `convoca-enroll`. Los tres enlaces llevan ahora a pantallas que existen y responden
  200.

### Cambiado
- Higiene del producto: retirados el nombre del cliente y de la asociación de CHANGELOGs, readmes,
  documentación, comentarios y datos de prueba, además de las referencias a proyectos reales que
  quedaban en el núcleo. Solo cambian comentarios y documentación; no se toca el comportamiento.

### Internamente
- Ajustes del banco de pruebas: el bootstrap carga sus propias clases, el simulador deja de dar por
  buenos cuatro supuestos equivocados, el mock de permisos admite overrides por prueba y la
  configuración ejecutaba una suite que no puede correr.

## v2.3.10 (2026-09-26)

### Corregido
- **Un botón cuyo enlace sigue siendo un placeholder ya no se imprime.** Si el dato no llega al
  cuerpo, `{link_pago}` sobrevive a la sustitución y el escape lo convertía en `http://link_pago`: un
  enlace inventado con apariencia de botón. `prune_empty_html()` lo retira igual que un enlace vacío.
  Detectado con la captura real de los correos, no leyendo el código.


## v2.3.9 (2026-09-26)

### Añadido
- **`Convoca\Core\Email_Links`**: los enlaces de los correos se resuelven contra las páginas REALES
  del sitio en vez de escribirse a mano. `panel()` encuentra la página del área privada por su
  shortcode (`[convoca_mi_area]`) o por sus slugs habituales; `footer()` construye los enlaces del pie
  con el **título real** de cada página encontrada. Todo pasa por `convoca_email_panel_url` y
  `convoca_email_footer_links`, para que un sitio pueda apuntar donde quiera sin tocar el plugin.

### Corregido
- **El CTA principal de todos los correos y el pie llevaban a un 404** en cualquier sitio cuyo panel no
  se llame `/mi-area/`: `render()` escribía `home_url('/mi-area/')`, `home_url('/contacto/')` y
  `home_url('/aviso-legal/')` a mano. Ahora los tres salen de `Email_Links::footer()`, y **lo que no
  resuelve no se imprime** (mejor sin enlace que con un 404).

### Cambiado
- El banco de pruebas del core registra filtros de verdad: `apply_filters()` devolvía el valor tal cual
  y `add_filter()` era un no-op, así que **cualquier contrato basado en filtros pasaba sin comprobar
  nada**. Con el registro real, los tests de filtros comprueban algo.


## v2.3.8 (2026-09-26)

### Corregido
- **Los botones de los correos llegaban rotos.** `Email_Layout::button_html()` escapaba el enlace en el
  momento de **construir** la plantilla, y `esc_url()` destruye un placeholder: `{link_pago}` se
  convertía en `http://link_pago`, la sustitución posterior ya no encontraba nada que reemplazar y el
  botón salía apuntando a una dirección inventada. **10 de las 15 plantillas de fábrica** estaban
  afectadas (`login_url`, `link_pago` ×6, `link_confirmacion` ×2, `certificado_url_verificacion`).
  Invisible hasta ahora porque en los sitios existentes mandaba la plantilla guardada antigua y el CTA
  de respaldo —que sí sustituye antes de escapar—; en una instalación nueva los 10 botones salían
  muertos.
  Ahora `button_html()` respeta el placeholder y el escape se hace en `render()`, el único punto donde
  el valor ya está sustituido (`escape_button_urls()`). Escapar dos veces una URL válida no la cambia,
  así que las plantillas literales siguen igual.


## v2.3.7 (2026-09-26)

### Corregido
- **Un punto suelto en todos los correos.** Dos `?>.` al final de un `echo` en `Email_Layout::render()`
  imprimían un `.` literal: salía pegado a la marca del sitio en la cabecera y al final del cuerpo de
  **todos** los correos del ecosistema (Members y Enroll). Se ve mirando el HTML que se envía, no la
  plantilla, y lo cubre `EmailLayoutRenderTest` («el layout no imprime texto propio»), comprobado en
  negativo contra el código con el defecto.

## v2.3.6 (2026-09-26)

### Corregido
- **`Email_Layout` ya no imprime datos ausentes.** El cuerpo de una plantilla se sustituye
  **después** de montarse, así que una fila cuyo valor venía vacío se imprimía igual: el
  destinatario leía «Nueva fecha renovación: —» y un botón cuyo enlace era un placeholder llegaba
  con `href="—"` (un enlace roto con la apariencia de un botón).
  `Email_Layout::prune_empty_html()` retira, ya sustituido el cuerpo, el botón sin destino, el
  párrafo que se queda sin texto, la fila sin valor y la caja de datos entera cuando acaba vacía.
  `Email_Layout::is_missing()` define qué cuenta como ausente (`''`, `—`, `-`, `--`, `#`): un `0`
  es un dato real y **se conserva**.

## v2.3.5 (2026-09-25)

### Añadido
- **`Convoca\Core\Hour_Ledger`**: ciclo de vida único de las horas de voluntariado. Un hecho
  (asistencia o turno) tiene **un solo** `registro_hora`, vinculado a su origen
  (`_convoca_origen` / `_convoca_origen_id`); retirarlo lo **invalida** (`_convoca_estado = 'anulada'`)
  y volver a marcarlo lo **reactiva**, así que marcar/desmarcar no acumula horas. Los consumidores
  (Members, certificados, renovación) exigen `aprobada`, de modo que invalidar basta para que las
  horas dejen de contar. Los registros históricos sin vínculo se reutilizan solo cuando la
  correspondencia es inequívoca.


## v2.3.4 (2026-09-25)

### Añadido
- **Copia al administrador (o al monitor) de los correos**: `Convoca\Core\Email_Copy`. Todo correo que
  un plugin de Convoca envía a una persona se notifica también a la asociación — a los **responsables**
  de la actividad si el correo es de una actividad, y al **correo de administración** en cualquier otro
  caso. Asunto marcado `[Copia]`, con cabecera que indica a quién se envió y su origen, y sin duplicar
  los adjuntos del original. Activada por defecto; se apaga con el ajuste o el filtro
  `convoca_email_copy_enabled`, y los destinatarios se ajustan con `convoca_email_copy_recipients`.


## v2.3.3 (2026-09-24)

### Añadido
- **Precio del evento** en el modelo de datos y en el formulario de la entrada: `_convoca_event_price` entra en `event_meta_keys()` y el metabox «Event» gana un campo numérico (`step="0.01"`, `min="0"`), con nota de que vacío significa evento gratuito.
- `event_price_sanitize()`: normaliza el precio (acepta coma decimal, exige número válido y no negativo, guarda dos decimales). Un valor inválido no se inventa: se borra el meta y el evento queda como gratuito.
- Pruebas: `EventPriceSanitizeTest` cubre once casos (coma, negativos, texto, cero, vacío) y comprueba que la clave canónica está en el inventario.

### Contexto
Cierra el issue #5: el theme leía el precio desde el meta (theme 2.9.18) pero no había manera de declararlo desde el editor, así que un curso de pago podía publicarse como gratuito en el JSON-LD del evento.

## v2.3.2 (2026-09-23)

### 🔒 Seguridad y robustez
- `License_Manager`: las tres redirecciones de activación/desactivación pasan a `wp_safe_redirect` — el destino salía de `wp_get_referer()`, que es entrada del visitante.
- `Admin_Logs_List`: respaldo de orden cuando `sanitize_sql_orderby()` no valida el valor pedido (antes quedaba `ORDER BY  DESC` y la consulta fallaba).

### 🧹 Saneamiento
- Los 14 `error_log` directos de `Admin_Templates` y `Signature` van al logger del plugin (`Logger::warning`/`error`), con contexto; se elimina el prefijo `BDV`, que era el nombre de un cliente dentro del producto.
- `json_encode` → `wp_json_encode`; `@` retirado donde ya se comprobaba el retorno; `in_array` estricto; `urlencode` → `add_query_arg`; `prepare()` retirado de las consultas sin marcadores; parámetros `new`/`default` renombrados; `count()` fuera de la condición del `for`.
- Alineación corregida con `phpcbf` (63 avisos cosméticos, sin cambios de comportamiento).
- Avisos restantes de PHPCS **clasificados y justificados** en `docs/phpcs-warnings.md` (292 → 199; no se silencia ninguno).

## v2.3.1 (2026-09-23)

### 🔒 Seguridad
- **Vista previa de plantillas de correo**: el HTML del correo se pinta ahora en un iframe con `sandbox` (a través de un blob), en lugar de inyectarse en el documento del panel. Cierra tres avisos de CodeQL (`js/xss-through-dom`).
- Los workflows (`ci.yml`, `deploy.yml`) declaran `permissions: contents: read`: el token del workflow sólo necesita leer el repositorio.

### 🐛 Arreglado
- `Admin_Setup_Wizard`: las notas del diagnóstico de páginas se escapan al imprimirse (no en el momento de construirlas), que es lo que exige la comprobación de Plugin Check.

## v2.3.0 (2026-09-23)

### 🏗️ Arquitectura
- Los shortcodes de interfaz y contenido pasan a Core desde el mu-plugin privado de un sitio: `[convoca_menu]`, `[convoca_socials]`, `[convoca_relacionadas]`, `[convoca_stats]`, `[convoca_cuando]` y `[convoca_donde]` (`includes/front-shortcodes.php`). Con los plugins y el theme ya se pinta un sitio completo, sin código privado de nadie.
- Los datos de evento de una entrada (marcarla como evento, fecha de inicio y fin, lugar) se gestionan desde Core (`includes/event-meta.php`): formulario, guardado y lectura. El theme sólo publica el schema.org y pinta lo que le da Core.
- Redes del sitio: **una sola fuente**, el filtro `convoca_social_links`, que alimenta el shortcode y los tokens del theme (antes cada uno leía su filtro).
- Cifras del sitio: `\Convoca\Core\site_stats()` con el filtro `convoca_site_stats` (antes `convoca_theme_stats`, en el theme).
- Compatibilidad de datos: el mapa de claves heredadas de evento se declara desde el sitio con `convoca_event_meta_legacy_keys`; el nombre de una asociación no vive en el producto.

### ⚠️ Migración
- `convoca_theme_stats` → `convoca_site_stats`.
- `convoca_theme_social_instagram/facebook/youtube/handle` → `convoca_social_links` (array servicio => URL, más `handle`).
- `convoca_theme_get_stats()` → `\Convoca\Core\site_stats()`.

## v2.2.9 (2026-09-23)

### 🐛 Correcciones
- El asistente de configuración escribía `[mi_panel]` en la página «Mi Panel de Socio»: nadie registra ese shortcode, así que la página se publicaba con el texto literal a la vista del visitante (visto en producción). Ahora escribe `[convoca_mi_area]`.
- El paso 2 (Páginas) comprueba el contenido real de cada página, no solo que exista: avisa si a la página le falta su shortcode y el botón «Crear o reparar páginas» la repara sin tocar el texto del autor.
- El asistente anota en el registro cuándo el shortcode que va a escribir no lo registra ningún plugin activo (antes publicaba la página rota en silencio).
- Las páginas del sistema viven en una única lista (`system_pages()`) que usan el diagnóstico, la creación, el resumen y el estado de configuración (antes eran cuatro copias que podían divergir).

## v2.2.8 (2026-09-11)

### 🐛 Correcciones
- Monitor de pagos: deja de registrar como «fallo de firma» una notificación rechazada por IP.

## v2.2.7 (2026-09-11)

### ✨ Mejoras
- Traducción completa y al día del plugin y su plantilla al inglés (en_US)

### 🐛 Correcciones
- La revalidación semanal de licencia se programa correctamente y solo se ejecuta cuando hay una licencia activa

## v2.2.6 (2026-09-10)

### ✨ Mejoras
- Botón "Reparar" en la página de Salud para las comprobaciones con corrección automática
- Retención de logs por nivel configurable + export a CSV
- Reintentos de webhook con retroceso (backoff) configurable + nivel de log configurable
- Tema claro/oscuro para documentos (claro por defecto) con selector en el Asistente de configuración

### 🐛 Correcciones
- La desinstalación ya no deja tablas, opciones ni tareas programadas huérfanas (con opción de conservar los datos)
- Notificaciones y Asistente de configuración vuelven a aparecer en el menú de administración
- Corregido un error de JavaScript que rompía el script de administración (SyntaxError por comillas mixtas)
- Los CSVs del export de copias de seguridad coinciden con los nombres que busca el import
- Los gráficos vacíos del dashboard muestran "Sin datos" y el PDF de memoria ya no incluye código PHP crudo ni emojis
- La página de Salud detecta correctamente Convoca Shifts activo
- El CSS de la página de Salud se carga en el panel de administración
- El dropdown de notificaciones de la barra de administración ya no se sale del marco ni desborda con textos largos
- El parámetro de versión de los assets comunes ya no queda congelado en una versión anterior

### 📦 Infraestructura
- Dependencias actualizadas (dompdf, PHPCS, WPCS)
- Preparación para WordPress.org: Plugin Check sin errores, Chart.js local y deuda estática a cero

## v2.2.5 (2026-09-05)

### 🐛 Correcciones
- El Security Monitor reporta la contención de bloqueos también cuando no hay filas afectadas

## v2.2.4 (2026-09-05)

### 🐛 Correcciones
- Corregidas la contención de bloqueos y el hook REST en el Security Monitor

## v2.2.3 (2026-09-05)

### ✨ Mejoras
- Security Monitor: observabilidad de eventos críticos y resumen diario

### 📦 Infraestructura
- CI 100 % bloqueante (estándares de código, análisis estático y tests)

## v2.2.2 (2026-09-05)

### 🔒 Seguridad
- Corregidos la exclusión mutua de bloqueos, las capacidades del Asistente y el inicio de sesión por AJAX

## v2.2.1 (2026-09-05)

### ✨ Mejoras
- El Asistente permite editar el ID corto (slug) de cada plan, con migración de los miembros

## v2.2.0 (2026-09-05)

### ✨ Mejoras
- El paso 3 del Asistente permite editar el nombre y la modalidad de cada plan

## v2.1.9 (2026-09-05)

### 🐛 Correcciones
- Navegación libre en el Asistente: pasos clicables y botón "Anterior" en el paso 7

## v2.1.8 (2026-09-05)

### ✨ Mejoras
- El paso 3 del Asistente permite activar o desactivar planes y editar su precio

## v2.1.7 (2026-09-05)

### ✨ Mejoras
- El paso 7 "Finalización" muestra un resumen de los 6 pasos configurados

### 🐛 Correcciones
- Botón "Siguiente" visible en el paso 6 (Ecosistema) para avanzar a Finalización

## v2.1.6 (2026-09-05)

### 🐛 Correcciones
- El Asistente detecta los módulos activos y aplica las clases CSS de WordPress en los campos

## v2.1.5 (2026-08-08)

### ✨ Mejoras
- Internacionalización completa: mensajes de error y respuestas REST traducibles
- Paso 6 "Ecosistema" del Asistente: explica módulos, estado de instalación e importación
- Registro y validación de módulos externos (marketplace)
- Historial de migraciones con validación posterior y posibilidad de revertir
- Perfilador de peticiones (rendimiento)

### 🐛 Correcciones
- El enlace de adquisición de licencia apunta a la tienda
- Corregida una comilla faltante en la consulta de socios activos y el alias en el resumen de los últimos 7 días
- El shortcode del Asistente de configuración es el correcto (`convoca_alta_socio`)
- Evitada una clave duplicada en instalaciones multisitio
- El endpoint REST de métricas usa el callback existente
- Eliminados los datos de Asociacion incrustados: planes, pistas, correos y URLs ahora se configuran mediante filtros
- El límite de intentos ya no queda enmascarado por la caché de objetos
- Modo oscuro: etiquetas de formulario en naranja e inputs con fondo oscuro
- Las peticiones de licencia se firman con nonce HMAC

### 🧪 Tests
- Tests unitarios ampliados (arranque, integración y utilidades)

### 📝 Documentación
- Referencia de hooks generada desde el código
- Readme preparado para WordPress.org
- Documentación unificada en la wiki

### 📦 Infraestructura
- Preparación para WordPress.org: readme en inglés, correcciones de Plugin Check, icono y banner
- Plantillas de issues y GitHub Discussions

---

## v2.1.4 (2026-06-24)

### ✨ Improvements
- Añadido publisher a PRO_FEATURES para habilitar licenciamiento completo
- Añadidas nuevas capabilities para diagnóstico y monitoreo del sistema
- Mejoras en diagnóstico y salud del sistema

### 🧪 Tests
- Añadidos tests unitarios para Utils::validate_dni (14 casos, validación DNI/NIE)
- Añadidos tests para Features (8) + Capabilities (5)

### 📦 Infrastructure
- Updated release ZIPs on getconvoca.app
- Demo environment synchronized

---

*Entradas nuevas reconstruidas a partir del historial de git.*
