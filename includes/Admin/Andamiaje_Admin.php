<?php
/**
 * El andamiaje de una pantalla de administración de Convoca, en un solo sitio (issue convoca-core#16).
 *
 * Por qué existe: medido el 15/10/2026, seis pantallas de administración del ecosistema repetían el
 * mismo andamiaje —un constructor con la lista de `add_action`, las páginas del menú, el encolado del
 * CSS común, el redirect del editor de WordPress al editor propio y el nodo de la barra de
 * administración— sin compartir nada: seis constructores con la misma forma y tres copias carácter a
 * carácter del bloque de `wp_enqueue_style`.
 *
 * Existe como trait, y no solo como clase base, por una limitación medida: dos de las seis pantallas
 * (`Admin_Horas` y `Admin_Proyectos`) ya heredan de `\WP_List_Table`, y PHP no tiene herencia múltiple.
 * Esas dos usan el trait directamente y las otras cuatro heredan de `Pantalla_Admin`, que usa este
 * mismo trait: hay **una sola implementación**, no dos. La alternativa —dejar que las dos tablas se
 * copiaran el constructor— es exactamente lo que este refactor cierra.
 *
 * Nada de esto cambia lo que hace cada pantalla: los ganchos, las capacidades y el orden de las
 * comprobaciones son los de antes. Lo único que cambia es dónde está escrito.
 *
 * Prueba en negativo (ejecutada el 15/10/2026): volviendo a escribir en `Admin_Proyectos` el
 * constructor con sus siete `add_action`, el contrato `AndamiajeAdminTest` cae y nombra el fichero.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implementación del andamiaje. La consumen `Pantalla_Admin` (herencia) y las pantallas que ya
 * extienden otra clase (uso directo).
 */
trait Andamiaje_Admin {

	/**
	 * Declara los ganchos de la pantalla.
	 *
	 * Formato: `'hook' => 'metodo'` o `'hook' => array( 'metodo', $prioridad, $args_aceptados )`.
	 * Es la lista que antes estaba copiada dentro del constructor de cada pantalla.
	 *
	 * @return array<string, string|array<int, int|string>> Ganchos de WordPress que registra la pantalla.
	 */
	abstract protected function ganchos(): array;

	/**
	 * Editor propio al que se lleva el editor estándar de WordPress.
	 *
	 * Solo lo declaran las pantallas que redirigen (`Admin_Actividades`, `Admin_Proyectos`). Va como
	 * método y no como argumento del callback porque el gancho `load-post.php` no pasa argumentos.
	 *
	 * @return array{tipo: string, slug: string}|null
	 */
	protected function editor_propio(): ?array {
		return null;
	}

	/**
	 * Nodo «Añadir nuevo» de la barra de administración que apunta al editor propio.
	 *
	 * Solo lo declaran las pantallas con editor propio.
	 *
	 * @return array{nodo: string, slug: string}|null
	 */
	protected function nodo_barra(): ?array {
		return null;
	}

	/**
	 * Punto único donde se registran los ganchos de una pantalla.
	 *
	 * El constructor es lo que estaba duplicado en las seis: aquí vive una sola vez, y quien hereda
	 * (o usa el trait) solo declara su lista en `ganchos()`.
	 */
	public function __construct() {
		foreach ( $this->ganchos() as $hook => $destino ) {
			$metodo    = is_array( $destino ) ? (string) $destino[0] : (string) $destino;
			$prioridad = is_array( $destino ) ? (int) ( $destino[1] ?? 10 ) : 10;
			$args      = is_array( $destino ) ? (int) ( $destino[2] ?? 1 ) : 1;

			add_action( $hook, array( $this, $metodo ), $prioridad, $args );
		}
	}

	/**
	 * Registra una página del menú de administración con el callback de esta pantalla.
	 *
	 * Envoltorio de `add_submenu_page()` con los mismos argumentos y el mismo orden: no cambia el
	 * menú, solo deja el alta de páginas en el andamiaje compartido. Devuelve el sufijo del hook, que
	 * es lo que permite encolar assets solo en esta pantalla.
	 *
	 * @param string|null $padre     Slug del menú padre; `null` para una página oculta.
	 * @param string      $titulo    Título de la página.
	 * @param string      $menu      Etiqueta del menú.
	 * @param string      $capacidad Capacidad necesaria para verla.
	 * @param string      $slug      Slug de la página.
	 * @param callable    $callback  Quien la dibuja.
	 * @return string Sufijo del hook devuelto por WordPress ('' si no se pudo registrar).
	 */
	protected function registrar_subpagina( ?string $padre, string $titulo, string $menu, string $capacidad, string $slug, callable $callback ): string {
		$hook = add_submenu_page( $padre, $titulo, $menu, $capacidad, $slug, $callback );

		return is_string( $hook ) ? $hook : '';
	}

	/**
	 * Encola el CSS común de Convoca. Es el bloque que estaba copiado en tres pantallas, carácter a
	 * carácter, con la misma versión (el cache-buster sigue a la versión del núcleo).
	 */
	protected function encolar_estilo_comun(): void {
		wp_enqueue_style( 'convoca-core', CONVOCA_COMMON_URL . 'assets/css/convoca-common.css', array(), CONVOCA_COMMON_VERSION );
	}

	/**
	 * Guarda de un manejador de guardado: el nonce primero y la capacidad después, en el mismo orden
	 * que tenían los manejadores (cambiar el orden cambiaría qué error ve quien no tiene permiso).
	 *
	 * `$mensaje` lo traduce quien llama, para no mover cadenas de texto entre dominios.
	 *
	 * @param string $nonce     Acción del nonce que se verifica.
	 * @param string $capacidad Capacidad necesaria.
	 * @param string $mensaje   Mensaje ya traducido para quien no tiene permiso.
	 */
	protected function exigir_guardado( string $nonce, string $capacidad, string $mensaje ): void {
		check_admin_referer( $nonce );

		if ( ! current_user_can( $capacidad ) ) {
			wp_die( $mensaje );
		}
	}

	/**
	 * Lleva el editor estándar de WordPress al editor propio de la pantalla.
	 *
	 * Es el bloque idéntico que había copiado en dos pantallas: el tipo de contenido y el slug de la
	 * página eran lo único que cambiaba. La tercera pantalla con redirect —`Admin_Horas`— mantiene el
	 * suyo, porque su lógica no es esta (mira solo `$screen->id`): unificarla sería cambiar el
	 * comportamiento, no compartirlo.
	 */
	public function redirigir_editor_propio(): void {
		$config = $this->editor_propio();
		if ( ! $config ) {
			return;
		}

		$tipo_post = $config['tipo'];
		$slug      = $config['slug'];

		$screen    = get_current_screen();
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( ! $post_type && isset( $_GET['post'] ) ) {
			$post_type = (string) get_post_type( (int) $_GET['post'] );
		}

		$peticion = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( ( $screen && $screen->id === $tipo_post ) || $post_type === $tipo_post ) {
			if ( ( isset( $screen->action ) && 'add' === $screen->action ) || strpos( $peticion, 'post-new.php' ) !== false ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . $slug ) );
				exit;
			}

			$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
			if ( $post_id && strpos( $peticion, 'post.php' ) !== false ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . $slug . '&id=' . $post_id ) );
				exit;
			}
		}
	}

	/**
	 * Manda a su pantalla propia quien llegue a la cruda de WordPress de un tipo con pantalla.
	 *
	 * Un tipo con `show_ui => false` deja la URL cruda viva y WordPress la resuelve con un **500 de
	 * permisos sin arreglo** (`edit.php?post_type=…`), y un tipo con pantalla propia ofrece además el
	 * listado duplicado. El mapa declara, por tipo, a dónde va el listado y a dónde va el alta, y cada
	 * plugin trae el suyo: la ceremonia (el gancho, el orden, la salida) vive en un solo sitio — core#16.
	 *
	 * Se retira del paso cuando la URL trae `page`: esas son pantallas propias colgadas del tipo
	 * (`edit.php?post_type=actividad&page=conv_enroll-logs`) y llevan a otro sitio a propósito.
	 *
	 * Se llama **en la clase** que usa el trait (`\Convoca\Core\Admin\Pantalla_Admin::redirigir_pantallas_crudas()`),
	 * nunca en el trait: PHP 8.5 avisa por deprecación al invocar un método estático sobre el trait
	 * directamente («it should only be called on a class using the trait»), y el aviso salía en cada
	 * pantalla de administración.
	 *
	 * @param array<string, array{lista: string, alta?: string}> $destinos Tipo de contenido => pantallas propias.
	 */
	public static function redirigir_pantallas_crudas( array $destinos ): void {
		add_action(
			'admin_init',
			static function () use ( $destinos ) {
				$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
				if ( ! in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) ) {
					return;
				}

				if ( isset( $_GET['page'] ) ) {
					return;
				}

				$tipo = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
				if ( '' === $tipo || ! isset( $destinos[ $tipo ] ) ) {
					return;
				}

				$destino = $destinos[ $tipo ];
				$slug    = ( 'post-new.php' === $pagenow && ! empty( $destino['alta'] ) ) ? $destino['alta'] : $destino['lista'];

				wp_safe_redirect( admin_url( 'admin.php?page=' . $slug ) );
				exit;
			},
			0
		);
	}

	/**
	 * Apunta el nodo «Añadir nuevo» de la barra de administración al editor propio.
	 *
	 * Otra copia de dos pantallas en las que solo cambiaban el nodo y el slug.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Barra de administración (la pasa el gancho `admin_bar_menu`).
	 */
	public function apuntar_nodo_barra( \WP_Admin_Bar $wp_admin_bar ): void {
		$config = $this->nodo_barra();
		if ( ! $config ) {
			return;
		}

		$node = $wp_admin_bar->get_node( $config['nodo'] );
		if ( $node ) {
			$node->href = admin_url( 'admin.php?page=' . $config['slug'] );
			$wp_admin_bar->add_node( (array) $node );
		}
	}
}
