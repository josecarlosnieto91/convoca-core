<?php
/**
 * Contrato del endurecimiento de la API REST y de la limpieza de tablas al desinstalar.
 *
 * Por qué existe: el bloqueo por ruta de `/wp-json/wp/v2/users` en el servidor web es
 * insuficiente, porque la misma consulta llega como `/?rest_route=/wp/v2/users` y esa forma no
 * pasa por la regla de nginx (para nginx la ruta es `/`). Medido el 13/10/2026 en las tres webs:
 * las tres devolvían 200 con la lista de usuarios (biodevas.org, 92 KB, con nombres y slugs de
 * acceso; lugg, el nombre real de una persona).
 *
 * Se cubre desde WordPress para que valga con cualquier forma de llamada. Estos casos fijan que:
 *  1. sin sesión desaparecen las dos rutas que enumeran (`/wp/v2/users` y `/wp/v2/users/<id>`);
 *  2. `/wp/v2/users/me` sigue ahí (responde 401 sin sesión y no enumera a nadie);
 *  3. con sesión no se retira nada (la administración y el editor ven lo de siempre);
 *  4. no se retira ninguna otra ruta (que no se cierre de más por descuido);
 *  5. el arranque del plugin engancha de verdad el filtro;
 *  6. el segundo bucle de `uninstall.php` limpia el prefijo `conv_` que promete su comentario,
 *     y no vuelve a los nombres `convoca_` (el defecto que tenía: borraba dos veces lo mismo).
 *
 * Prueba en negativo: sin el filtro enganchado, el caso 5 falla; volviendo a poner los nombres
 * `convoca_` en el segundo array, el caso 6 falla; quitando una ruta de la lista, falla el 1.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Rest_Hardening;
use PHPUnit\Framework\TestCase;

class EnumeracionDeUsuariosTest extends TestCase
{
	/** Rutas de la API tal y como las recibe el filtro, con las de usuarios dentro. */
	private function rutasDeEjemplo(): array
	{
		return array(
			'/wp/v2/users'                       => array( 'metodos' => array( 'GET' ) ),
			'/wp/v2/users/(?P<id>[\d]+)'         => array( 'metodos' => array( 'GET' ) ),
			'/wp/v2/users/me'                    => array( 'metodos' => array( 'GET' ) ),
			'/wp/v2/posts'                       => array( 'metodos' => array( 'GET' ) ),
			'/convoca-members/v1/documentos/(?P<id>\d+)' => array( 'metodos' => array( 'GET' ) ),
		);
	}

	public function test_sin_sesion_desaparecen_las_rutas_que_enumeran_usuarios(): void
	{
		$fuera = Rest_Hardening::sin_rutas_de_enumeracion( $this->rutasDeEjemplo(), false );

		$this->assertArrayNotHasKey( '/wp/v2/users', $fuera );
		$this->assertArrayNotHasKey( '/wp/v2/users/(?P<id>[\d]+)', $fuera );
	}

	public function test_users_me_sigue_disponible_sin_sesion(): void
	{
		// `/users/me` responde 401 sin sesión y no enumera a nadie: no se toca.
		$fuera = Rest_Hardening::sin_rutas_de_enumeracion( $this->rutasDeEjemplo(), false );

		$this->assertArrayHasKey( '/wp/v2/users/me', $fuera );
	}

	public function test_no_se_retira_ninguna_otra_ruta(): void
	{
		$original = $this->rutasDeEjemplo();
		$fuera    = Rest_Hardening::sin_rutas_de_enumeracion( $original, false );

		$this->assertSame( count( $original ) - 2, count( $fuera ) );
		$this->assertArrayHasKey( '/wp/v2/posts', $fuera );
		$this->assertArrayHasKey( '/convoca-members/v1/documentos/(?P<id>\d+)', $fuera );
	}

	public function test_con_sesion_no_se_retira_absolutamente_nada(): void
	{
		$original = $this->rutasDeEjemplo();
		$fuera    = Rest_Hardening::sin_rutas_de_enumeracion( $original, true );

		$this->assertSame( $original, $fuera );
	}

	public function test_el_arranque_del_plugin_engancha_el_filtro(): void
	{
		$arranque = file_get_contents( dirname( __DIR__, 2 ) . '/convoca-core.php' );

		$this->assertIsString( $arranque );
		$this->assertStringContainsString(
			'Rest_Hardening::init()',
			$arranque,
			'El endurecimiento de la API REST no está enganchado en el arranque: el filtro no se aplicaría.'
		);
	}

	public function test_el_bucle_de_tablas_legacy_usa_el_prefijo_conv(): void
	{
		$desinstalador = file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );

		$this->assertIsString( $desinstalador );
		$this->assertStringContainsString(
			'{$wpdb->prefix}conv_{$sufijo}',
			$desinstalador,
			'El bucle de tablas antiguas tiene que limpiar el prefijo `conv_`, no repetir `convoca_`.'
		);
	}
}
