## [2.3.38] — 2026-10-16

### Añadido
- **Al entrar, la junta aterriza en el Panel de Control de Convoca (core#20).** Medido en el informe: al iniciar
  sesión se caía en el Escritorio de WordPress —aviso de precio de WPML en inglés, y «Estado de salud del sitio:
  Necesita mejoras · problema crítico» en rojo, sin decir qué hacer— mientras el panel bueno estaba a un clic.
  Ahora el primer minuto es suyo: quien puede ver el panel **entra en él** (se respeta a quien ha pedido una
  pantalla concreta, a quien viene con destino propio y a quien no tiene permiso).
- **Tarjeta «Convoca: por dónde empiezo» arriba del todo del Escritorio**, para quien sí pase por ahí: botón al
  panel, el estado del servidor **traducido a lenguaje de asociación** («El servidor va bien: no hay nada
  urgente», o «hay algo que conviene enseñar a quien te instaló esto: …») y los cuatro primeros pasos.
- **Los avisos de terceros se callan SOLO en el Escritorio**: los de Convoca se respetan siempre y en las
  pantallas de cada plugin no se toca nada. Con lista blanca ajustable por filtro
  (`convoca_core_avisos_permitidos`) y se puede apagar desde el ajuste `convoca_core_entrada`.

### Notas
- Seis pruebas nuevas (`EntradaDelPanelTest`): el destino del acceso (con sus tres excepciones), la traducción
  de la salud —que sin datos dice «no hemos podido consultar», no un diagnóstico inventado—, el contenido de la
  tarjeta y los ajustes por defecto.
- La salud del sitio no se inventa: se lee de `WP_Site_Health` y, si no se puede, se dice.

