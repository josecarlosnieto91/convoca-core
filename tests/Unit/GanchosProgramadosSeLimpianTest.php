<?php
/**
 * Contrato de desinstalación: todo gancho que este plugin programa tiene que limpiarse al
 * desinstalarlo.
 *
 * Por qué existe: medido en un WordPress limpio (13/10/2026), tras borrar el plugin seguían en la
 * lista de tareas programadas ganchos suyos que se disparaban sin que nadie los atendiera. El
 * uninstall.php limpiaba unos cuantos escritos a mano y se dejaba fuera los demás.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

class GanchosProgramadosSeLimpianTest extends TestCase {

	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/** Los ganchos que el plugin programa, leídos de su propio código. */
	private function ganchosProgramados(): array {
		$ganchos = array();
		$iter    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->raiz() ) );
		foreach ( $iter as $f ) {
			$ruta = $f->getPathname();
			if ( 'php' !== strtolower( $f->getExtension() ) ) { continue; }
			if ( false !== strpos( $ruta, '/vendor/' ) || false !== strpos( $ruta, '/node_modules/' ) || false !== strpos( $ruta, '/tests/' ) ) { continue; }
			$txt = (string) file_get_contents( $ruta );
			if ( preg_match_all( '/wp_schedule_(?:event|single_event)\s*\((.*?)\)\s*;/s', $txt, $m ) ) {
				foreach ( $m[1] as $args ) {
					// El gancho es el ÚLTIMO literal entrecomillado de la llamada: los de delante son
					// la marca de tiempo y el nombre de la recurrencia (que también puede empezar por
					// 'convoca_', como 'convoca_weekly', y no es un gancho).
					if ( preg_match_all( "/'([a-z0-9_]+)'/i", $args, $h ) && ! empty( $h[1] ) ) {
						$gancho = end( $h[1] );
						if ( false !== stripos( $gancho, 'convoca' ) ) { $ganchos[ $gancho ] = true; }
					}
				}
			}
		}
		return array_keys( $ganchos );
	}

	public function test_todo_gancho_programado_se_limpia_al_desinstalar(): void {
		$programados = $this->ganchosProgramados();
		$this->assertNotEmpty( $programados, 'No se ha encontrado ningún gancho programado: ¿la prueba está mirando donde debe?' );

		$uninstall   = (string) file_get_contents( $this->raiz() . '/uninstall.php' );
		$sin_limpiar = array();
		foreach ( $programados as $g ) {
			if ( false === strpos( $uninstall, "'" . $g . "'" ) ) {
				$sin_limpiar[] = $g;
			}
		}

		$this->assertSame(
			array(),
			$sin_limpiar,
			'Estos ganchos se programan pero la desinstalación no los limpia: ' . implode( ', ', $sin_limpiar )
		);
	}
}
