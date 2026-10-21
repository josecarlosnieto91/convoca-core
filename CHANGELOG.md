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

