<?php
/**
 * La entrada al panel: dónde aterriza la junta y qué ve primero (core#20).
 *
 * MEDIDO (informe del issue, visto como miembro de la junta que entra a trabajar): al iniciar sesión se caía en
 * el Escritorio de WordPress —el aviso de WPML con su precio en inglés y un «Estado de salud del sitio:
 * Necesita mejoras · Tu sitio tiene un problema crítico» en rojo, sin decir qué hacer— mientras el **Panel de
 * Control de Convoca**, que sí responde a lo que quiere saber, estaba un clic más allá.
 *
 * El primer minuto decide si la herramienta parece para ella o para un informático. Aquí se hace que ese
 * minuto sea suyo: al entrar aterriza en su panel, y si alguien pasa por el Escritorio, lo primero que ve es
 * una tarjeta de Convoca que traduce la salud del servidor a lenguaje de asociación y dice por dónde empezar.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entrada y Escritorio.
 */
class Admin_Entrada {

	/**
	 * Ajuste: dónde aterrizar y si se ocultan los avisos de terceros en el Escritorio.
	 */
	const OPCION = 'convoca_core_entrada';

	/**
	 * Enganches.
	 */
	public static function init(): void {
		add_filter( 'login_redirect', array( __CLASS__, 'aterrizar' ), 20, 3 );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'escritorio' ), 20 );
	}

	/**
	 * Los ajustes, con sus valores por defecto.
	 *
	 * @return array{aterrizar:bool,ocultar_avisos:bool}
	 */
	public static function ajustes(): array {
		$guardado = get_option( self::OPCION, array() );
		$guardado = is_array( $guardado ) ? $guardado : array();

		return array(
			// Por defecto, sí a las dos: son las dos cosas que pidió el issue.
			'aterrizar'      => ! isset( $guardado['aterrizar'] ) || ! empty( $guardado['aterrizar'] ),
			'ocultar_avisos' => ! isset( $guardado['ocultar_avisos'] ) || ! empty( $guardado['ocultar_avisos'] ),
		);
	}

	/**
	 * ¿Puede este usuario aterrizar en el panel de Convoca?
	 *
	 * Solo quien ve el panel (permiso de gestión) y nunca si el propio usuario ha pedido otra cosa.
	 *
	 * @param mixed $user Usuario (WP_User si ha entrado; WP_Error si el acceso falló).
	 * @return bool
	 */
	public static function puede_aterrizar( $user ): bool {
		return ( $user instanceof \WP_User ) && user_can( $user, 'manage_options' );
	}

	/**
	 * Al entrar, al Panel de Control de Convoca.
	 *
	 * @param string           $redirect_to           Destino ya calculado por WordPress.
	 * @param string           $requested_redirect_to Destino que pidió quien entró ('' si no pidió ninguno).
	 * @param \WP_User|\WP_Error $user              Usuario que acaba de entrar.
	 * @return string
	 */
	public static function aterrizar( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! self::ajustes()['aterrizar'] || ! self::puede_aterrizar( $user ) ) {
			return $redirect_to;
		}

		// Si ha pedido una pantalla concreta (o ha venido de una), se respeta: aquí solo se cambia el «no sé
		// adónde ir», que es cuando WordPress manda al Escritorio.
		if ( '' !== (string) $requested_redirect_to ) {
			return $redirect_to;
		}
		if ( false !== strpos( (string) $redirect_to, 'page=' ) ) {
			return $redirect_to;
		}

		// Y tampoco si alguien ha apagado el panel por filtro (sitios que lo esconden).
		$destino = admin_url( 'admin.php?page=conv-dashboard' );
		if ( ! apply_filters( 'convoca_core_aterrizar_en_panel', true, $user ) ) {
			return $redirect_to;
		}

		return $destino;
	}

	/**
	 * El Escritorio: la tarjeta de Convoca arriba del todo y, si se ha pedido, sin avisos de terceros.
	 */
	public static function escritorio(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'convoca_entrada',
			__( 'Convoca: por dónde empiezo', 'convoca-core' ),
			array( __CLASS__, 'tarjeta' )
		);

		// A la primera posición: el resumen de la asociación se lee antes que nada.
		self::tarjeta_primero();

		if ( self::ajustes()['ocultar_avisos'] ) {
			self::callar_a_terceros();
		}
	}

	/**
	 * Mueve la tarjeta al primer lugar del Escritorio.
	 */
	private static function tarjeta_primero(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordenar los widgets del Escritorio se hace así en WordPress: no hay API pública para subir uno al primer lugar.
		global $wp_meta_boxes;

		foreach ( array( 'normal', 'side' ) as $columna ) {
			if ( empty( $wp_meta_boxes['dashboard'][ $columna ]['core']['convoca_entrada'] ) ) {
				continue;
			}
			$mia = $wp_meta_boxes['dashboard'][ $columna ]['core']['convoca_entrada'];
			unset( $wp_meta_boxes['dashboard'][ $columna ]['core']['convoca_entrada'] );
			$wp_meta_boxes['dashboard'][ $columna ]['core'] = array( 'convoca_entrada' => $mia ) + $wp_meta_boxes['dashboard'][ $columna ]['core']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Subir el propio widget al primer lugar del Escritorio: es el idioma de WordPress para esto.

			return;
		}
	}

	/**
	 * Traduce el estado de salud del sitio a lenguaje de asociación.
	 *
	 * «Necesita mejoras · problema crítico» no dice nada a quien lleva la contabilidad; esto sí: si el servidor
	 * va bien, se dice; y si hay algo, se dice a quién avisar.
	 *
	 * @return array{estado:string,texto:string,detalle:string}
	 */
	/**
	 * Nombre canónico de una prueba.
	 *
	 * Se compone solo con algo escalar: con un array, `(string)` daba «Array» y un warning (core#30).
	 *
	 * @param array<string,mixed> $prueba Definición registrada.
	 * @param string              $clave  Clave del registro.
	 * @return string
	 */
	private static function nombre_prueba( array $prueba, string $clave ): string {
		$callback = $prueba['test'] ?? '';

		if ( is_scalar( $callback ) && '' !== (string) $callback ) {
			return (string) $callback;
		}

		return $clave;
	}

	/**
	 * Ejecuta una prueba registrada, sea cual sea la forma en que se registró.
	 *
	 * La API de Site Health admite tres y las tres se atienden:
	 *   · el nombre de un método de `WP_Site_Health` (casi todo el núcleo),
	 *   · un callable suelto (Performance Lab),
	 *   · un callable en array, `array( $objeto, 'metodo' )` (WP Consent API).
	 *
	 * Antes se hacía `(string)` del callback: con un array saltaba un warning, el nombre quedaba en «Array»
	 * y la prueba se descartaba en silencio (core#30).
	 *
	 * @param object              $salud Objeto con los métodos `get_test_*`.
	 * @param string              $clave Clave con la que se registró.
	 * @param array<string,mixed> $prueba Definición de la prueba.
	 * @return array<string,mixed>|null Resultado, o null si no se puede ejecutar.
	 */
	private static function ejecutar_prueba( $salud, string $clave, array $prueba ): ?array {
		$callback = $prueba['test'] ?? '';

		if ( is_string( $callback ) && method_exists( $salud, 'get_test_' . $callback ) ) {
			$resultado = $salud->{'get_test_' . $callback}();

			return is_array( $resultado ) ? $resultado : null;
		}

		if ( is_callable( $callback ) ) {
			$resultado = call_user_func( $callback );

			return is_array( $resultado ) ? $resultado : null;
		}

		if ( '' !== $clave && method_exists( $salud, 'get_test_' . $clave ) ) {
			$resultado = $salud->{'get_test_' . $clave}();

			return is_array( $resultado ) ? $resultado : null;
		}

		return null;
	}

	/**
	 * Ejecuta las pruebas directas y devuelve el recuento y la cobertura.
	 *
	 * Se separa de `salud()` para poder probarlo sin un WordPress entero: recibe las pruebas y el objeto que
	 * las ejecuta. La API de Site Health admite el nombre de un método, un callable suelto y un callable en
	 * array; las tres formas se atienden (core#30) y lo que no se puede ejecutar se declara (core#31).
	 *
	 * @param object                $salud     Objeto con los métodos `get_test_*` (normalmente WP_Site_Health).
	 * @param array<array-key,mixed> $directas Pruebas directas registradas.
	 * @param string[]            $excluidas  Nombres que no se cuentan.
	 * @return array{cuenta: array<string,int>, primer_crit: string, miradas: int, omitidas: int, total: int}
	 */
	public static function evaluar_pruebas( $salud, array $directas, array $excluidas = array() ): array {
		$cuenta      = array(
			'good'        => 0,
			'recommended' => 0,
			'critical'    => 0,
		);
		$primer_crit = '';
		$miradas     = 0;
		$omitidas    = 0;
		$total       = 0;

		// Solo las pruebas «directas»: no salen a la red. Las asíncronas (comunicación con WordPress.org,
		// bucle de retorno…) las lanza el propio widget de salud desde el navegador; hacerlo aquí dejaría
		// esperando a quien entra a trabajar.
		foreach ( $directas as $clave => $prueba ) {
			$nombre = self::nombre_prueba( $prueba, (string) $clave );

			if ( '' === $nombre || in_array( $nombre, $excluidas, true ) ) {
				continue;
			}

			++$total;

			$resultado = self::ejecutar_prueba( $salud, (string) $clave, $prueba );

			// Lo que no se puede ejecutar no se cuenta —y desde luego no se da por bueno—, pero tampoco se
			// inventa: se declara en la cobertura (core#31).
			if ( null === $resultado ) {
				++$omitidas;
				continue;
			}

			++$miradas;
			$estado = (string) ( $resultado['status'] ?? '' );
			if ( isset( $cuenta[ $estado ] ) ) {
				++$cuenta[ $estado ];
			}
			if ( 'critical' === $estado && '' === $primer_crit ) {
				$primer_crit = wp_strip_all_tags( (string) ( $resultado['label'] ?? $prueba['label'] ?? '' ) );
			}
		}

		return array(
			'cuenta'      => $cuenta,
			'primer_crit' => $primer_crit,
			'miradas'     => $miradas,
			'omitidas'    => $omitidas,
			'total'       => $total,
		);
	}

	/**
	 * Estado del servidor en lenguaje de asociación.
	 *
	 * @return array<string,mixed>
	 */
	public static function salud(): array {
		$sin_datos = array(
			'estado'  => 'sin_datos',
			'texto'   => __( 'No hemos podido consultar el estado del servidor ahora mismo.', 'convoca-core' ),
			'detalle' => '',
		);

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			return $sin_datos;
		}

		// Con caché media hora: correr las pruebas de salud en cada carga del Escritorio lo haría lento, y el
		// Escritorio se abre muchas veces al día.
		$guardado = get_transient( 'convoca_core_salud' );
		if ( is_array( $guardado ) && isset( $guardado['estado'], $guardado['texto'], $guardado['detalle'] ) ) {
			/**
			 * Resultado guardado en la caché.
			 *
			 * @var array{estado: string, texto: string, detalle: string} $guardado
			 */
			return $guardado;
		}

		$salud = \WP_Site_Health::get_instance();
		if ( ! $salud ) {
			return $sin_datos;
		}
		$pruebas = $salud->get_tests();
		if ( empty( $pruebas ) ) {
			return $sin_datos;
		}

		/**
		 * Filtro: pruebas de salud que NO se cuentan.
		 *
		 * Para sitios con una decisión deliberada que la prueba marca (el modo depuración, que en estos sitios se
		 * deja encendido a propósito). Por defecto no se excluye ninguna: ocultar un aviso real es peor que
		 * enseñar uno de más.
		 *
		 * @param string[] $excluidas Nombres de prueba (p. ej. 'is_in_debug_mode').
		 */
		$excluidas = (array) apply_filters( 'convoca_core_salud_excluir', array() );

		$evaluacion = self::evaluar_pruebas( $salud, (array) ( $pruebas['direct'] ?? array() ), $excluidas );

		$respuesta = self::respuesta_de_salud( $evaluacion );

		set_transient( 'convoca_core_salud', $respuesta, 30 * MINUTE_IN_SECONDS );

		return $respuesta;
	}

	/**
	 * Traduce la evaluación a la respuesta que se enseña.
	 *
	 * La cobertura solo se nombra cuando falta alguna prueba: si están todas, añadirla sería ruido.
	 * El estado no cambia por este dato (core#31).
	 *
	 * @param array{cuenta: array<string,int>, primer_crit: string, miradas: int, omitidas: int, total: int} $evaluacion Evaluación.
	 * @return array<string,mixed>
	 */
	private static function respuesta_de_salud( array $evaluacion ): array {
		$cuenta      = $evaluacion['cuenta'];
		$primer_crit = $evaluacion['primer_crit'];
		$miradas     = $evaluacion['miradas'];
		$omitidas    = $evaluacion['omitidas'];
		$total       = $evaluacion['total'];

		// Si no se ha podido mirar NINGUNA prueba, no se dice que todo va bien: se dice que no se sabe.
		if ( 0 === $miradas ) {
			return array(
				'estado'  => 'sin_datos',
				'texto'   => __( 'No hemos podido consultar el estado del servidor ahora mismo.', 'convoca-core' ),
				'detalle' => '',
			);
		}

		// Cobertura: decir cuántas se han mirado y cuántas no. Un «va bien» sobre 22 de 28 pruebas es
		// verdad a medias, y el día que el problema viva en una de las 6 nadie lo sabría (core#31).
		$cobertura = '';

		if ( $omitidas > 0 ) {
			$cobertura = sprintf(
				/* translators: 1: pruebas revisadas, 2: pruebas totales */
				__( '%1$d de %2$d pruebas revisadas: las que no se pueden ejecutar desde aquí las lanza el propio WordPress.', 'convoca-core' ),
				$miradas,
				$total
			);
		}

		$datos = array(
			'miradas'  => $miradas,
			'total'    => $total,
			'omitidas' => $omitidas,
		);

		if ( $cuenta['critical'] > 0 ) {
			$respuesta = array_merge(
				array(
					'estado'  => 'critical',
					'texto'   => __( 'El servidor tiene algo que conviene enseñar a quien te instaló esto.', 'convoca-core' ),
					'detalle' => trim( $primer_crit . ' ' . $cobertura ),
				),
				$datos
			);
		} elseif ( $cuenta['recommended'] > 0 ) {
			$respuesta = array_merge(
				array(
					'estado'  => 'recommended',
					'texto'   => __( 'El servidor va bien, con algún aviso que no corre prisa.', 'convoca-core' ),
					'detalle' => $cobertura,
				),
				$datos
			);
		} else {
			$respuesta = array_merge(
				array(
					'estado'  => 'good',
					'texto'   => __( 'El servidor va bien: no hay nada urgente.', 'convoca-core' ),
					'detalle' => $cobertura,
				),
				$datos
			);
		}

		return $respuesta;
	}

	public static function tarjeta(): void {
		$salud = self::salud();
		$panel = admin_url( 'admin.php?page=conv-dashboard' );

		echo '<div class="convoca-entrada">';
		echo '<p style="margin-top:0;font-size:1.05em;">'
			. esc_html__( 'Lo que te interesa de la asociación está en tu panel:', 'convoca-core' ) . ' '
			. '<a class="button button-primary" href="' . esc_url( $panel ) . '">'
			. esc_html__( 'Abrir el Panel de Control', 'convoca-core' ) . '</a></p>';

		// El estado del servidor, en lenguaje de asociación.
		$colores = array(
			'good'        => '#1a7f37',
			'recommended' => '#b26a00',
			'critical'    => '#b32d2e',
			'sin_datos'   => '#646970',
		);
		echo '<p style="margin:10px 0 4px;"><span style="color:' . esc_attr( $colores[ $salud['estado'] ] ) . ';font-weight:600;">■</span> '
			. esc_html( $salud['texto'] ) . '</p>';
		if ( '' !== $salud['detalle'] ) {
			echo '<p style="margin:0 0 4px;color:#50575e;"><em>' . esc_html( $salud['detalle'] ) . '</em></p>';
		}

		echo '<details style="margin-top:12px;"><summary style="cursor:pointer;font-weight:600;">'
			. esc_html__( '¿Por dónde empiezo?', 'convoca-core' ) . '</summary><ol style="margin:8px 0 0 18px;">';
		foreach ( array(
			__( 'Mira el Panel de Control: socios activos, altas del mes, inscripciones e ingresos.', 'convoca-core' ),
			__( 'Publica las actividades de las próximas semanas y comprueba que tienen plazas.', 'convoca-core' ),
			__( 'Reparte los turnos del centro entre los voluntarios (Turnos → Delegados lo ven).', 'convoca-core' ),
			__( 'Si algo del servidor pide atención, avisa a quien te lo instaló; no hace falta que lo entiendas.', 'convoca-core' ),
		) as $paso ) {
			echo '<li>' . esc_html( $paso ) . '</li>';
		}
		echo '</ol></details></div>';
	}

	/**
	 * Calla los avisos que no son del producto, SOLO en el Escritorio.
	 *
	 * Medido: lo primero que leía la junta era el aviso de precio de WPML, en inglés y sobre algo que no usa.
	 * Se quitan los avisos cuyo código no viva en un plugin de Convoca (lista blanca), y solo en esta pantalla:
	 * en las pantallas de cada plugin los avisos siguen a la vista de quien tiene que verlos.
	 *
	 * Se puede desactivar desde el ajuste, y afinar con el filtro.
	 */
	private static function callar_a_terceros(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- No hay API pública para quitar las devoluciones de un gancho, y aquí es justo lo que hace falta: dejar sin voz a los avisos de terceros.
		global $wp_filter;

		$pantalla = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $pantalla || 'dashboard' !== $pantalla->id ) {
			return;
		}

		/**
		 * Filtro: lista blanca de carpetas de plugin cuyos avisos se respetan en el Escritorio.
		 *
		 * @param string[] $carpetas Carpetas permitidas.
		 */
		$blanca      = (array) apply_filters( 'convoca_core_avisos_permitidos', array( 'convoca-', 'hello-convoca' ) );
		$plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );

		foreach ( array( 'admin_notices', 'all_admin_notices' ) as $gancho ) {
			if ( empty( $wp_filter[ $gancho ] ) ) {
				continue;
			}

			foreach ( $wp_filter[ $gancho ]->callbacks as $prioridad => $callbacks ) {
				foreach ( $callbacks as $id => $cb ) {
					$origen = self::origen_de( $cb['function'] ?? null );
					if ( '' === $origen || 0 !== strpos( $origen, $plugins_dir ) ) {
						// No viene de un plugin (núcleo, tema) o no se puede saber: se respeta.
						continue;
					}

					$relativa  = ltrim( substr( $origen, strlen( $plugins_dir ) ), '/' );
					$permitido = false;
					foreach ( $blanca as $carpeta ) {
						if ( 0 === strpos( $relativa, (string) $carpeta ) ) {
							$permitido = true;
							break;
						}
					}
					if ( ! $permitido ) {
						unset( $wp_filter[ $gancho ]->callbacks[ $prioridad ][ $id ] );
					}
				}
			}
		}
	}

	/**
	 * De qué fichero sale una devolución de llamada.
	 *
	 * @param mixed $devolucion Devolución.
	 * @return string Ruta normalizada, o '' si no se puede saber.
	 */
	private static function origen_de( $devolucion ): string {
		try {
			if ( is_string( $devolucion ) && false !== strpos( $devolucion, '::' ) ) {
				list( $clase, $metodo ) = explode( '::', $devolucion, 2 );
				$ref                    = new \ReflectionMethod( $clase, $metodo );
			} elseif ( is_array( $devolucion ) && 2 === count( $devolucion ) ) {
				$ref = new \ReflectionMethod( $devolucion[0], (string) $devolucion[1] );
			} elseif ( $devolucion instanceof \Closure ) {
				$ref = new \ReflectionFunction( $devolucion );
			} else {
				return '';
			}

			return wp_normalize_path( (string) $ref->getFileName() );
		} catch ( \Throwable $e ) {
			return '';
		}
	}
}
