## [2.4.9] — 2026-11-08

### Corregido

- **los correos y el admin ya no llevan la paleta de un cliente** (#37): `Email_Layout` y el aviso de
  copia leen el acento del ajuste del sitio (con el neutro como respaldo) y ya no clavan `#FF8700` y
  `#320028`. Las pantallas de administración (apariencia, informe de memoria, asistente de puesta en
  marcha) pasan a los colores de WordPress.
- Nuevo `Site_Settings::color()`: un solo sitio decide el color, porque los correos necesitan el valor en
  hexadecimal y no pueden usar variables CSS.

Dos pruebas nuevas: el correo usa el color configurado y, sin ajuste, cae al neutro.

## [2.4.8] — 2026-11-08

### Corregido

- **el color del sitio no ganaba a las hojas del plugin**: el `<style>` salía en `wp_head` antes de los
  estilos encolados y, con la misma especificidad, ganaba el `:root` del plugin. Se imprime al final
  (`priority 999`) y con `:root:root`, que gana por especificidad y no por orden.

## [2.4.7] — 2026-11-08

### Añadido

- **colores del sitio en Convoca → Sitio**: dos campos (acento y acento fuerte) que se imprimen como
  `--conv-accent` y `--conv-accent-strong` en la cabecera. Un sitio con un tema ajeno puede poner su
  color sin tocar CSS; vacío = el valor neutro del producto. La casilla «Usar el valor neutro» lo
  devuelve. Solo se imprime el CSS cuando hay color configurado.

Tres pruebas nuevas: saneado (hex válido, basura descartada, casilla de limpiar) y que sin colores no se
imprime nada.

## [2.4.6] — 2026-11-08

### Cambiado

- **el CSS deja de depender de la paleta del tema**: El CSS común usa tokens neutros (`--conv-*`) en vez de **44 presets del tema** y **17 colores fijos** de la paleta de un cliente. En un tema que no define esos presets, la interfaz se pintaba con la paleta de otro sitio. Los plugins usan tokens `--conv-*` con valor
  neutro y el tema de Convoca los rellena con su paleta, así que su aspecto no cambia. Prueba nueva
  (`CssTokensTest`) que falla si vuelven los presets o un color de cliente.

## [2.4.5] — 2026-11-08

### Cambiado

- **El panel de diagnóstico dice cuántos problemas hay de cada tipo.** Antes, con un solo aviso, el
  resumen decía «problemas críticos que impiden el funcionamiento»; ahora cuenta los errores y los
  avisos por separado («1 problema crítico… y 2 avisos de configuración pendientes»). Un sitio que
  funciona deja de parecer roto por una página opcional sin configurar (issue convoca-enroll#63).

## [2.4.4] — 2026-11-08

### Cambiado

- La misma corrección de 2.4.3, escrita sin añadir una rama a `prune_empty_html()`: una imagen cuenta
  como contenido marcándola antes de quitar etiquetas. Sin cambio de comportamiento; deja el ratchet de
  PHPMD donde estaba.

## [2.4.3] — 2026-11-08

### Corregido

- **La poda del correo ya no borra los párrafos que solo llevan una imagen.** `prune_empty_html()`
  decidía «vacío» por el texto, así que un párrafo con el QR de check-in (solo `<img>`) desaparecía y
  el correo prometía un QR que no llegaba. Ahora un párrafo con `<img>` se conserva; los vacíos de
  verdad (una raya, un valor ausente) siguen fuera.

### Pruebas

- 2 nuevas: un párrafo con imagen no se poda y uno vacío de verdad sí.

## [2.4.2] — 2026-11-05

### Cambiado

- La pantalla del sitio se divide en filas reutilizables (`fila_mapa`, `fila_texto`, `fila_area`) para no
  pasar el ratchet de PHPMD. Sin cambios de comportamiento: el marcado es el mismo.

## [2.4.1] — 2026-11-04

### Añadido

- **Las redes sociales y el aviso del pie también salen de la opción del sitio**: `convoca_social_links`
  se alimenta de la pantalla y `copyright_extra` admite el HTML permitido del pie (CC BY-NC, etc.).

## [2.4.0] — 2026-11-04

### Añadido

- **Pantalla «Convoca → Sitio»**: redes, etiquetas de esos enlaces, botón y bloque CTA, correo de
  voluntariado y aviso del pie, guardados en la opción `convoca_site_settings`. Los filtros del tema se
  mantienen y leen la opción como valor por defecto; un filtro del sitio añadido después manda sobre
  ella. Los datos dejan de vivir en un mu-plugin por sitio.
- **`wp convoca-core sitio claves-legado --claves="canonica=antigua,..."`**: el mapa de claves heredadas
  de los datos de evento se carga una vez y el filtro `convoca_event_meta_legacy_keys` lo lee.
- Comandos `sitio mostrar` y `sitio limpiar-claves-legado`.

### Pruebas

- 6 nuevas: saneado de enlaces y etiquetas, el filtro devuelve lo guardado, sin opción no pisa el valor
  del tema, un filtro del sitio manda, las claves heredadas salen de la opción y el comando está
  registrado.

## [2.3.57] — 2026-11-04

### Corregido

- **`[convoca_menu]` pinta un `<nav>` con nombre accesible.** El shortcode no aceptaba etiqueta y el
  contenedor salía sin `aria-label`, así que en una página con otra navegación un lector de pantalla
  anunciaba «navegación» dos veces sin poder distinguirlas (axe `landmark-unique`, medido en las 28
  páginas de biodevas.org). Ahora acepta `label` y, si no se le pasa, usa `Enlaces rápidos` para la
  barra superior y `Navegación` para el resto; el valor viaja en `container_aria_label`.
- **El arnés de pruebas pinta el contenedor del menú como WordPress**, en vez de devolver un HTML fijo:
  sin eso, una prueba del `aria-label` habría medido el doble y no el producto.

## [2.3.56] — 2026-11-02

### Añadido

- **`Utils::set_document_theme()`**: el tema de documentos tenía **dos escritores** (el asistente de
  configuración de Core y los ajustes de Members), cada uno con su copia de la validación. Ahora la
  regla vive en un sitio y los dos pasan por ella: lo que se guarda es exactamente lo que el lector
  va a devolver. Con su prueba (válido, inválido y vacío).

## [2.3.55] — 2026-11-02

### Añadido

- **`chillerlan/php-qrcode` pasa a vivir en Core**: la usaban tres plugins (Members, Courses y
  Enroll) y viajaba tres veces en cada sitio. Una sola copia compartida ahorra 1,1 MB de paquete.

### Cambiado

- **suelo de PHP 8.1 → 8.2**, que es el que ya pedía la librería de QR. Declararlo evita prometer
  compatibilidad con una versión en la que el plugin no puede funcionar.

## [2.3.54] — 2026-11-01

### Corregido

- **eventos de cron huérfanos**: las instalaciones que vienen del prefijo `bdv_`/`bde_`
  conservaban cuatro eventos sin oyente. Ocupan sitio en el array de cron (que WordPress
  carga en cada petición) y despiertan WP-Cron para nada. Se retiran al arrancar.
  Medido en la demo: 50 eventos programados, 4 huérfanos, 7 contando los de plugins
  de terceros que no se tocan.
- **`Diagnostico` cumple el gate de calidad**: el pintado se parte en cabecera y fila y
  `resultado()` baja a cuatro parámetros, con `con_arreglo()` para el texto del arreglo.

## [2.3.53] — 2026-11-01

### Cambiado

- **un solo diagnóstico**: `Admin\Diagnostico` en Core centraliza las comprobaciones
  genéricas (PHP, tablas, cron) y el pintado de la pantalla de estado. `Admin_Status`
  de Members y de Shifts dejan de duplicar el HTML y la lógica; cada plugin aporta sus
  datos. Sin cambios de comportamiento ni de textos visibles.

## [2.3.52] — 2026-11-01

### Cambiado

- **los contadores cuentan los mismos estados que los listados**

## [2.3.51] — 2026-10-30

### Cambiado

- **arranque defensivo, salud con cobertura y registro sin ruido**

## [2.3.50] — 2026-10-26

### Cambiado

- **cobertura completa y cierre de los issues del repositorio**
- El arnes emula meta_query estricto y resuelve get_users por usermeta.
- Los CSV neutralizan formulas antes de escribirlas.

## [2.3.49] — 2026-10-21

### Arreglado

- **La puerta de cobertura medía la mitad del núcleo (spec 001, cerrada).** El trabajo medía solo la suite
  unitaria (17,4 %) y la integración no la miraba nadie; peor aún, con cobertura activada el trabajo
  **fallaba y no medía nada**: las catorce anotaciones de `tests/Integration/` estaban sin cualificar y
  PHPUnit 10 las declara inválidas (`«@covers ::get_all» is invalid`), con el clover en
  `0,0 % (0/6.432)` sobre 25 avisos. La anotación `@coversDefaultClass` estaba en el docblock del
  **fichero**, donde PHPUnit no la lee.
  Ahora las dos suites se miden en una sola invocación (`--testsuite unit,integration`), las anotaciones
  van cualificadas con su clase y el porcentaje es el total del proyecto: **18,9 %** en local y **19,2 %**
  en el CI, con el suelo en 16,9 % (antes 15 %). 316 → 349 tests, 953 → 1.279 aserciones, cero fallos
  nuevos; los 8 casos que necesitan WordPress se saltan con el motivo escrito en el propio test.

## [2.3.48] — 2026-10-21

### Cambiado

- **El núcleo sólo nombra clases de plugins en `Features`.** Los tres informes de analítica comprobaban si
  el módulo estaba con `class_exists( '\Convoca\Members\CPT_Miembro' )` y compañía; ahora preguntan a
  `Features::has()`, que ya era el único detector. El día que un addon cambie el nombre de su clase, el
  núcleo no se entera.

## [2.3.47] — 2026-10-21

### Añadido

- **`Core\Member_Identity`: la identidad del socio, como contrato.** El núcleo necesita saber si hay un
  socio con la sesión abierta y si una inscripción tiene prioridad; hasta hoy eso lo resolvía cada plugin
  llamando a las clases de Convoca Members con un `class_exists` delante y su propio camino de reserva
  detrás. Ahora se pregunta a `Member_Identity` y contesta quien gestiona los socios, por los filtros
  `convoca_member_identity` y `convoca_member_priority`.
- Los valores por defecto son **el comportamiento de hoy sin gestión de socios**: nadie autenticado,
  ningún socio y prioridad concedida. La respuesta se guarda sólo durante la petición (la sesión vive en un
  transient) y `olvidar()` la descarta tras un login o un logout.

### Pruebas

- 8 casos en `MemberIdentityTest`: los valores por defecto, la respuesta normalizada (lo que no encaja se
  convierte en «no hay socio»), que se pregunta **una sola vez** por petición y que `olvidar()` vuelve a
  preguntar.

## [2.3.46] — 2026-10-21

### Cambiado

- **Tres capacidades más al mapa**: `manage_convoca_logs`, `manage_convoca_templates` y
  `manage_convoca_gateway` las aplicaba Convoca Enroll al administrador. Con el vocabulario en core tienen
  que estar aquí, o al retirar el mapa de enroll el administrador las pierde sin que nadie se entere.
  El mapa queda en 29 capacidades y ningún addon necesita tocar roles.

## [2.3.45] — 2026-10-21

### Cambiado

- **El vocabulario de personas es del producto: core crea los roles.** `socio`, `voluntario_aprobado` y
  `monitor_actividad` los creaba cada plugin por su cuenta —members, shifts y enroll—, cada uno con sus
  capacidades. Eso hacía que en un sitio **sin** la gestión de personas aparecieran los roles y se pudiera
  asignar gente a los turnos. Ahora los crea `Capabilities::crear_roles()` y nadie más; quién es voluntario
  o monitor sigue siendo cosa de sus fichas y aprobaciones.
- **Aviso de permisos**: al aplicar el mapa de capacidades se añaden a los roles existentes las que les
  falten (hoy `gestionar_mis_turnos` la añadían members y shifts por separado, así que hay instalaciones
  con el rol a medias). No se quita ninguna capacidad.

- **El mapa de capacidades estaba incompleto y ahora no lo está.** Lo que cada plugin ponía en su
  `add_role()` tiene que vivir aquí, porque `add_role()` **no pisa un rol existente**: el que llega segundo
  no añade nada y el rol se queda a medias. Faltaban `gestionar_miembros`,
  `gestionar_documentos_voluntariado`, `view_reports` y las ocho de edición de contenido que members ponía
  al rol de monitor (`edit_posts`, `edit_others_posts`, `edit_published_posts`, `publish_posts`,
  `delete_posts`, `delete_others_posts`, `delete_published_posts`, `read_private_posts`).

### Añadido

- **`Hour_Ledger::puede_acreditar_horas()`**: la puerta del libro de horas. Por defecto **no** se acredita:
  el permiso lo aporta quien gestiona las personas, con el filtro `convoca_can_credit_hours`. Los dos
  productores del libro tienen que preguntar aquí antes de escribir.

### Pruebas

- 8 casos nuevos en `RolesYHorasTest`: los tres roles se crean, es idempotente, no pisa un rol existente
  con capacidades propias, el mapa se aplica a quien toca y la puerta de horas dice que no por defecto.
  Probado en negativo: con el valor por defecto a `true` el test cae; sin crear los roles, el mapa no
  llega a nadie.

## [2.3.44] — 2026-10-21

### Cambiado

- **El diagnóstico agregado del ecosistema pasa a filtro.** La página Salud del Sistema llamaba por
  reflexión a `Members\Admin_Settings`, `Enroll\Admin_Settings`, `Gateway\Diagnostic` y a la función de
  Turnos: el núcleo dependía de los addons. Ahora aplica `convoca_system_checks` y cada módulo aporta su
  bloque; se conservan el orden y los dos textos de aviso, y un módulo que lanza una excepción ya no
  tumba la página.
- **Planes y estados del socio por `convoca_member_plans` y `convoca_member_states`**, y la detección de
  Inscripciones por `Features::has( 'enroll' )`.
- **La fachada de cobros (`Core\Payments`) ya no nombra clases del gateway**: backend, lector y recibo se
  resuelven por filtro, y es el gateway quien se registra.

### Arreglado

- **Las métricas del panel habrían reventado con Members activo.** El código recorría
  `Members\Estados::LABELS`, una constante que no existe (la API es `Estados::labels()`): en PHP 8 eso es
  `Error: Undefined constant` en cuanto se abría la pantalla.

## [2.3.43] — 2026-10-20

### Arreglado

- **Una página montada con bloques se quedaba sin `convoca-common.css`.** La detección solo miraba
  shortcodes (`[convoca_x]`), así que un `<!-- wp:convoca-members/formulario-alta -->` no casaba y el
  formulario salía desnudo; el JS sí llegaba, porque los scripts de los plugins sí declaran la
  dependencia. La regla vive ahora en `Utils::content_needs_common_assets()`, mira shortcode **y**
  bloque (`convoca-*` y `centro-social/*`) y tiene pruebas en los dos sentidos.

### Cambiado

- «Convoca Common» → «Convoca Core» en los textos que ve el usuario y en los catálogos `.pot`/`.po`.
  El usuario instalaba «Convoca Core» y la web le pedía «Convoca Common Utilities», un producto que
  ya no existe con ese nombre.

## [2.3.42] — 2026-10-20

### Añadido

- `Payments::get()`, `Payments::status()` y `Payments::receipt_url()`: la fachada también **lee** el
  pago y su recibo, con `convoca_payment_reader` y `convoca_payment_receipt` para sustituir el
  backend. Sin pasarela devuelven vacío (nunca un fatal).

## [2.3.41] — 2026-10-20

### Añadido

- `Convoca\Core\Payments`: fachada única para crear pagos (`create()`, `is_available()`) con el
  backend sustituible por el filtro `convoca_payment_backend`. Los módulos que cobran dejan de llamar
  a las clases de Convoca Gateway; el gateway sigue siendo el backend real.
- Gancho `convoca_payment_created` al crear un pago.

## [2.3.40] — 2026-10-20

### Añadido

- `License_Manager::pro_features()` incluye la función `courses` (Convoca Courses PRO: lecciones de
  vídeo con URL firmada). El módulo de cursos la usa para saber si ofrece vídeo.

## [2.3.39] — 2026-10-16

### Arreglado
- **La tarjeta del Escritorio decía «el servidor va bien» cuando no lo sabía.** La versión anterior (2.3.38)
  contaba las pruebas de salud con `WP_Site_Health::get_test_result()` —un método **que no existe**— y, al ir
  envuelto en un `method_exists()`, nunca se ejecutaba nada: los contadores quedaban a cero y la tarjeta
  afirmaba «El servidor va bien: no hay nada urgente» mientras el widget de WordPress, justo debajo, decía
  «Necesita mejoras · 7 elementos».
  Una tranquilidad falsa es la peor clase de error, y la escribía yo. Ahora:
  - cada prueba se ejecuta por su método (`get_test_<nombre>`, que es como está hecha la clase);
  - solo se corren las pruebas **directas** (las que no salen a la red), para no dejar esperando a quien entra
    a trabajar: las asíncronas las sigue lanzando el widget de WordPress;
  - si **no se ha podido mirar ninguna**, la tarjeta dice que no se sabe, en vez de dar el visto bueno;
  - el resultado se guarda **media hora en caché** (el Escritorio se abre muchas veces al día);
  - y hay una lista de exclusiones por filtro (`convoca_core_salud_excluir`), **vacía por defecto**, para
    sitios con una decisión deliberada que la prueba marca (el modo depuración, por ejemplo).

### Notas
- Una prueba nueva fija las tres reglas (`test_lo_que_no_se_puede_leer_no_se_da_por_bueno`), incluida la de
  no volver a usar el accesorio inexistente.
