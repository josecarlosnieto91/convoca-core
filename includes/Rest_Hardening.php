<?php
/**
 * Endurecimiento de la API REST del núcleo.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cierra la enumeración de usuarios por la API REST a quien no ha iniciado sesión.
 *
 * Por qué existe: bloquear la ruta `/wp-json/wp/v2/users` en el servidor web es insuficiente,
 * porque la misma consulta llega también como `/?rest_route=/wp/v2/users` y esa forma **no
 * pasa** por una regla de nginx: para nginx la ruta es `/`. Medido el 13/10/2026 en las tres
 * webs: la variante con `?rest_route=` devolvía 200 con la lista de usuarios (en biodevas.org,
 * 92 KB con nombres y slugs de acceso). El nombre y el slug son datos personales y material
 * para fuerza bruta dirigida.
 *
 * Se cierra en WordPress y no en el servidor web para que valga con cualquier forma de llamada,
 * presente y futura. A quien ha iniciado sesión no se le retira nada: el editor y la administración
 * siguen viendo lo que veían.
 */
class Rest_Hardening {

	/**
	 * Rutas que se retiran a las peticiones sin sesión.
	 *
	 * Se retira la colección y el acceso por identificador. No se toca `/wp/v2/users/me`, que
	 * responde 401 sin sesión y no enumera nada.
	 *
	 * @var string[]
	 */
	const RUTAS_DE_ENUMERACION = array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' );

	/**
	 * Engancha el filtro. Se llama una sola vez desde el arranque del plugin.
	 */
	public static function init(): void {
		add_filter( 'rest_endpoints', array( __CLASS__, 'filtrar' ) );
	}

	/**
	 * Retira las rutas de enumeración cuando no hay sesión.
	 *
	 * @param array<string, mixed> $endpoints Rutas de la API REST, indexadas por ruta.
	 * @return array<string, mixed> Las mismas rutas, sin las de enumeración si no hay sesión.
	 */
	public static function filtrar( array $endpoints ): array {
		return self::sin_rutas_de_enumeracion( $endpoints, is_user_logged_in() );
	}

	/**
	 * Parte pura del filtro, para poder probarla sin WordPress cargado.
	 *
	 * @param array $endpoints  Rutas de la API REST.
	 * @param bool  $con_sesion Si quien llama ha iniciado sesión.
	 * @return array Las rutas que correspondan a ese estado.
	 */
	public static function sin_rutas_de_enumeracion( array $endpoints, bool $con_sesion ): array {
		if ( $con_sesion ) {
			return $endpoints;
		}

		foreach ( self::RUTAS_DE_ENUMERACION as $ruta ) {
			unset( $endpoints[ $ruta ] );
		}

		return $endpoints;
	}
}
