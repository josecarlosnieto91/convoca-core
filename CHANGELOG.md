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

