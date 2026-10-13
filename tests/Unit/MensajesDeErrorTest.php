<?php
/**
 * Contrato de los mensajes de error de la capa JS del ecosistema.
 *
 * Por qué existe: el usuario final veía «Error desconocido.» en los flujos (alta de socio,
 * inscripción, panel de reservas) y frases en inglés cuando la petición fallaba de verdad
 * (`Authentication required. Please log in.`, `Service temporarily unavailable.`,
 * `Redirect detected. Please log in.`) o texto técnico
 * (`Error HTTP Fetch al portal wp-admin /admin-ajax.php`). Ninguna de esas frases dice
 * QUÉ HACER, que es lo que exige el listón.
 *
 * La causa raíz no estaba en los llamadores: `conv.ajaxPost` no traducía las respuestas de
 * protocolo de WordPress (`0` = acción inexistente, `-1` = nonce caducado) y cada llamador
 * acababa inventándose un respaldo. Ahora hay un único punto (`conv.normalizarError` /
 * `convAdmin.normalizarError`) y unos textos con fuente única en PHP
 * (`Convoca\Core\convoca_common_js_strings()`).
 *
 * Prueba en negativo: devolver cualquiera de los textos retirados —o quitar el mapa de
 * `0`/`-1`— hace fallar esta comprobación.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class MensajesDeErrorTest extends TestCase
{
	/** Textos que NO pueden volver: no dicen qué hacer (o están en inglés). */
	private const PROHIBIDOS = [
		'Error desconocido',
		'Authentication required. Please log in.',
		'Service temporarily unavailable.',
		'Redirect detected. Please log in.',
		'Error HTTP Fetch al portal wp-admin /admin-ajax.php',
	];

	/** Claves que el JS espera recibir y el PHP tiene que declarar. */
	private const CLAVES = [
		'sinConexion',
		'sesionCaducada',
		'accionNoDisponible',
		'servidorNoDisponible',
		'respuestaInesperada',
		'reintentar',
	];

	private function raiz(): string
	{
		return dirname(__DIR__, 2);
	}

	/**
	 * Código sin comentarios: el comentario que explica el defecto nombra justo lo prohibido.
	 *
	 * @param string $ruta Fichero a leer.
	 * @return string Código sin comentarios de bloque ni de línea.
	 */
	private function codigo(string $ruta): string
	{
		$this->assertFileExists($ruta, "Falta el fichero {$ruta}.");
		$txt = (string) file_get_contents($ruta);

		// /* ... */ y // ... (una pasada basta para estos ficheros; el orden importa:
		// primero el de bloque, para no dejar restos de un bloque que contenga //).
		$txt = (string) preg_replace('#/\*.*?\*/#s', '', $txt);
		$txt = (string) preg_replace('#(^|\s)//[^\n]*#', '$1', $txt);

		return $txt;
	}

	/**
	 * @return array<string, string> nombre => código sin comentarios de cada bundle.
	 */
	private function bundles(): array
	{
		return [
			'público'   => $this->codigo($this->raiz() . '/assets/js/convoca-common.js'),
			'admin'     => $this->codigo($this->raiz() . '/assets/js/convoca-common-admin.js'),
		];
	}

	public function test_ningun_bundle_conserva_los_textos_prohibidos(): void
	{
		foreach ($this->bundles() as $nombre => $js) {
			foreach (self::PROHIBIDOS as $prohibido) {
				$this->assertStringNotContainsString(
					$prohibido,
					$js,
					"El bundle {$nombre} sigue con «{$prohibido}»: un fallo tiene que decir qué hacer."
				);
			}
		}
	}

	public function test_cada_bundle_traduce_los_codigos_de_wordpress(): void
	{
		foreach ($this->bundles() as $nombre => $js) {
			$this->assertMatchesRegularExpression(
				'/===?\s*0.*?accionNoDisponible/s',
				$js,
				"El bundle {$nombre} no traduce la respuesta `0` (acción inexistente) de admin-ajax."
			);
			$this->assertMatchesRegularExpression(
				'/===?\s*(-1|\'-1\').*?sesionCaducada/s',
				$js,
				"El bundle {$nombre} no traduce la respuesta `-1` (nonce caducado) de admin-ajax."
			);
		}
	}

	public function test_el_php_declara_los_mismos_textos_y_los_inyecta(): void
	{
		$php = $this->codigo($this->raiz() . '/includes/admin-global-menu.php');

		foreach (self::CLAVES as $clave) {
			$this->assertStringContainsString(
				"'" . $clave . "'",
				$php,
				"Falta el texto «{$clave}» en convoca_common_js_strings()."
			);
		}

		$this->assertMatchesRegularExpression(
			"/wp_localize_script\(\s*'convoca-common-js'\s*,\s*'convocaI18n'/",
			$php,
			'El bundle público no recibe los textos.'
		);
		$this->assertMatchesRegularExpression(
			"/wp_localize_script\(\s*'convoca-common-admin-js'\s*,\s*'convocaAdminI18n'/",
			$php,
			'El bundle de administración no recibe los textos.'
		);
	}

	public function test_los_textos_declarados_son_los_que_el_js_espera(): void
	{
		// Si alguien renombra una clave en PHP y no en el JS (o al revés), el mensaje sale
		// como `undefined` en pantalla: el cruce se comprueba, no se supone.
		foreach ($this->bundles() as $nombre => $js) {
			foreach (self::CLAVES as $clave) {
				$this->assertMatchesRegularExpression(
					'/' . $clave . '\s*:/',
					$js,
					"El bundle {$nombre} no usa la clave «{$clave}» que declara el PHP."
				);
			}
		}
	}
}
