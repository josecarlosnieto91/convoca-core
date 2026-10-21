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

