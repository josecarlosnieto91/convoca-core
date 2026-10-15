<?php
/**
 * Comprobación de contrato: en convoca-core, el CSV se escribe en UN solo sitio (issue convoca-core#16).
 *
 * Por qué existe: nueve exportadores del ecosistema abrían el CSV a mano —cuatro `header()`, el BOM,
 * `fopen( 'php://output' )`, `fputcsv` con separador y, en unos sí y en otros no, el escapado de cada
 * celda—. Medido el 15/10/2026: de los nueve, tres no escapaban ninguna celda y dos escribían con un
 * separador que Excel en español no reconoce. Y el intento anterior de compartirlo (`csv_headers()`)
 * quedó obsoleto y sin usar porque abría y cerraba el flujo entre el BOM y las filas.
 *
 * El contrato: fuera de `includes/Utils.php` nadie abre `php://output` para CSV ni llama a `fputcsv`,
 * y nadie revive `csv_headers()`. Con la lista de excepciones escrita y su motivo, que es como el
 * ecosistema declara lo que acepta.
 *
 * Prueba en negativo (ejecutada el 15/10/2026): añadiendo `fputcsv( fopen( 'php://output', 'w' ), array() );`
 * a `includes/Logger.php`, las dos comprobaciones caen y el mensaje NOMBRA el fichero.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class UnaSolaCeremoniaCsvTest extends TestCase {

	/**
	 * Ficheros que SÍ pueden escribir CSV a mano, con el motivo por el que se aceptan.
	 *
	 * `Admin_Backup.php` arma un ZIP de copia de seguridad con un CSV por tabla dentro de un flujo
	 * temporal (`php://temp`), no una descarga al navegador: no comparte ni las cabeceras de
	 * contenido ni el ciclo de vida de la respuesta, así que `stream_csv()` no le sirve. Su
	 * separador es `,` a propósito, porque esos CSV se restauran con un lector propio.
	 *
	 * @var array<string, string>
	 */
	private const EXCEPCIONES = array(
		'includes/Admin_Backup.php' => 'CSV dentro del ZIP de copia de seguridad (flujo temporal, no descarga).',
	);

	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Ficheros PHP del repositorio, sin dependencias ni pruebas.
	 *
	 * @return array<int, string> Rutas relativas a la raíz.
	 */
	private function ficherosPhp(): array {
		$raiz    = $this->raiz();
		$omitir  = array( 'vendor', 'node_modules', 'tests', 'build', 'build-dir', 'dev-tools', '.git', 'languages' );
		$encontrados = array();

		$iterador = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $raiz, \FilesystemIterator::SKIP_DOTS ),
				static function ( \SplFileInfo $actual ) use ( $omitir ): bool {
					return ! in_array( $actual->getFilename(), $omitir, true );
				}
			)
		);

		foreach ( $iterador as $f ) {
			if ( $f instanceof \SplFileInfo && $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
				$encontrados[] = ltrim( str_replace( $raiz, '', $f->getPathname() ), '/' );
			}
		}
		sort( $encontrados );

		return $encontrados;
	}

	/**
	 * Código sin comentarios: un `// fputcsv(...)` en un comentario no es una llamada, y este
	 * contrato mide llamadas. Se quitan con el tokenizador de PHP, no con una expresión regular.
	 *
	 * @param string $codigo Fuente PHP.
	 * @return string Fuente sin comentarios ni docblocks.
	 */
	private function sinComentarios( string $codigo ): string {
		$limpio = '';
		foreach ( token_get_all( $codigo ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$limpio .= is_array( $token ) ? $token[1] : $token;
		}

		return $limpio;
	}

	/**
	 * Apariciones de un patrón, por fichero y sin las excepciones aceptadas.
	 *
	 * @param string $patron Expresión regular.
	 * @return array<int, string> Ficheros que lo contienen.
	 */
	private function quienes( string $patron ): array {
		$quienes = array();
		foreach ( $this->ficherosPhp() as $relativo ) {
			if ( isset( self::EXCEPCIONES[ $relativo ] ) ) {
				continue;
			}
			$codigo = $this->sinComentarios( (string) file_get_contents( $this->raiz() . '/' . $relativo ) );
			if ( preg_match( $patron, $codigo ) ) {
				$quienes[] = $relativo;
			}
		}

		return $quienes;
	}

	/**
	 * La ceremonia CSV tiene un solo dueño: el ayudante del núcleo.
	 */
	public function test_solo_utils_abre_el_flujo_de_salida_para_csv(): void {
		$quienes = $this->quienes( '/php:\/\/output/' );

		$this->assertSame(
			array( 'includes/Utils.php' ),
			$quienes,
			'Solo el ayudante del núcleo puede abrir php://output para CSV. Lo abren: ' . implode( ', ', $quienes )
		);
	}

	/**
	 * Y decide en un solo sitio qué celda es peligrosa: nadie escribe filas por su cuenta.
	 */
	public function test_nadie_llama_a_fputcsv_fuera_de_utils(): void {
		$quienes = $this->quienes( '/[^_a-zA-Z]fputcsv\s*\(/' );

		$this->assertSame(
			array( 'includes/Utils.php' ),
			$quienes,
			'Escribir filas a mano se salta la protección contra fórmulas. Lo hacen: ' . implode( ', ', $quienes )
		);
	}

	/**
	 * El intento anterior sigue declarado —es API publicada— pero nadie lo usa: si vuelve a
	 * aparecer una llamada, es que alguien ha vuelto a cortar el flujo por la mitad.
	 */
	public function test_no_se_revive_csv_headers(): void {
		$llamadas = array();
		foreach ( $this->ficherosPhp() as $relativo ) {
			$codigo = $this->sinComentarios( (string) file_get_contents( $this->raiz() . '/' . $relativo ) );
			// La declaración (con `function`) no cuenta; la llamada sí.
			if ( preg_match( '/[^a-zA-Z_]csv_headers\s*\(/', preg_replace( '/function\s+csv_headers/', '', $codigo ) ) ) {
				$llamadas[] = $relativo;
			}
		}

		$this->assertSame(
			array(),
			$llamadas,
			'csv_headers() abre y cierra el flujo, así que el BOM se pierde: usa stream_csv(). Lo llaman: ' . implode( ', ', $llamadas )
		);
	}

	/**
	 * El ayudante sigue siendo el mismo de siempre: separador `;` por defecto y escapado por celda.
	 */
	public function test_el_ayudante_existe_con_separador_por_defecto(): void {
		$metodo = new \ReflectionMethod( \Convoca\Core\Utils::class, 'stream_csv' );
		$params = $metodo->getParameters();

		$this->assertTrue( $metodo->isStatic() );
		$this->assertCount( 4, $params );
		$this->assertSame( ';', $params[3]->getDefaultValue() );
		$this->assertSame( 3, $params[2]->getPosition() + 1, 'El tercer parámetro son las filas' );
	}
}
