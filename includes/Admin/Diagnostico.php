<?php
/**
 * Diagnóstico de estado compartido por los plugins de Convoca.
 *
 * Nació de dos pantallas gemelas (`Admin_Status` en Members y en Shifts) que habían
 * copiado el mismo HTML y las mismas tres comprobaciones genéricas, y solo se
 * diferenciaban en los datos: qué páginas, qué tablas y qué eventos cron vigila cada
 * plugin. Aquí vive la mecánica; los datos siguen en cada plugin, que es donde
 * pertenecen.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprobaciones genéricas y pintado de una pantalla de estado.
 */
class Diagnostico {

	public const SEVERITY_OK      = 'ok';
	public const SEVERITY_WARNING = 'warning';
	public const SEVERITY_ERROR   = 'error';

	/**
	 * Comprueba la versión de PHP.
	 *
	 * @param string $minimo Versión mínima exigida, en formato comparable.
	 *
	 * @return array<string, mixed>
	 */
	public static function php( string $minimo = '8.0' ): array {
		$actual = PHP_VERSION;
		if ( version_compare( $actual, $minimo, '>=' ) ) {
			return self::resultado( 'php', 'PHP', sprintf( 'Versión %s', $actual ), self::SEVERITY_OK );
		}

		return self::resultado(
			'php',
			'PHP',
			sprintf( 'Versión %s (mínimo %s)', $actual, $minimo ),
			self::SEVERITY_ERROR,
			sprintf( 'Actualiza PHP a %s o superior.', $minimo )
		);
	}

	/**
	 * Comprueba que existen las tablas indicadas.
	 *
	 * @param array<int, string> $tablas Nombres completos, con prefijo.
	 * @param string             $arreglo Qué debe hacer el administrador si faltan.
	 *
	 * @return array<string, mixed>
	 */
	public static function tablas( array $tablas, string $arreglo = '' ): array {
		global $wpdb;

		$faltan = array();
		foreach ( $tablas as $tabla ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabla ) ) ) {
				$faltan[] = $tabla;
			}
		}

		if ( empty( $faltan ) ) {
			return self::resultado( 'tables', 'Tablas BD', 'Todas las tablas existen', self::SEVERITY_OK );
		}

		return self::resultado(
			'tables',
			'Tablas BD',
			sprintf( 'Faltan: %s', implode( ', ', $faltan ) ),
			self::SEVERITY_ERROR,
			$arreglo
		);
	}

	/**
	 * Comprueba que los eventos cron indicados están programados.
	 *
	 * @param array<int, string> $eventos Nombres de los ganchos.
	 * @param string             $arreglo Qué debe hacer el administrador si faltan.
	 *
	 * @return array<string, mixed>
	 */
	public static function cron( array $eventos, string $arreglo = '' ): array {
		$faltan = array();
		foreach ( $eventos as $evento ) {
			if ( ! wp_next_scheduled( $evento ) ) {
				$faltan[] = $evento;
			}
		}

		if ( empty( $faltan ) ) {
			return self::resultado( 'cron', 'Cron', 'Eventos programados activos', self::SEVERITY_OK );
		}

		return self::resultado(
			'cron',
			'Cron',
			sprintf( 'Faltan: %s', implode( ', ', $faltan ) ),
			self::SEVERITY_WARNING,
			$arreglo
		);
	}

	/**
	 * Construye el resultado de una comprobación.
	 *
	 * @param string $id       Identificador estable.
	 * @param string $label    Etiqueta visible.
	 * @param string $message  Resultado legible.
	 * @param string $severity Una de las constantes SEVERITY_*.
	 * @param string $fix      Qué hacer para arreglarlo, si aplica.
	 *
	 * @return array<string, mixed>
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) Cinco campos son los de una
	 * comprobación (id, etiqueta, mensaje, gravedad y arreglo); empaquetarlos en un array
	 * solo cambia el problema de sitio y hace las llamadas menos legibles.
	 */
	public static function resultado(
		string $id,
		string $label,
		string $message,
		string $severity,
		string $fix = ''
	): array {
		return compact( 'id', 'label', 'message', 'severity', 'fix' );
	}

	/**
	 * Pinta la pantalla de estado.
	 *
	 * @param array<int, array<string, mixed>> $resultados Salida de las comprobaciones.
	 * @param string                           $titulo     Encabezado de la pantalla, ya traducido.
	 */
	public static function pintar( array $resultados, string $titulo ): void {
		$hay_errores = self::hay_severidad( $resultados, self::SEVERITY_ERROR );
		$hay_avisos  = self::hay_severidad( $resultados, self::SEVERITY_WARNING );
		$estado      = $hay_errores ? 'error' : ( $hay_avisos ? 'warning' : 'success' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $titulo ); ?></h1>
			<div class="convoca-diagnostic">
				<?php self::pintar_cabecera( $estado, count( $resultados ) ); ?>
				<div class="convoca-diagnostic-results">
					<?php foreach ( $resultados as $resultado ) : ?>
						<?php self::pintar_fila( $resultado ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * ¿Hay alguna comprobación con esta severidad?
	 *
	 * @param array<int, array<string, mixed>> $resultados Salida de las comprobaciones.
	 * @param string                           $severidad  Una de las constantes SEVERITY_*.
	 */
	private static function hay_severidad( array $resultados, string $severidad ): bool {
		foreach ( $resultados as $resultado ) {
			if ( $severidad === $resultado['severity'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Cabecera con el resumen global.
	 *
	 * @param string $estado error, warning o success.
	 * @param int    $total  Número de comprobaciones.
	 */
	private static function pintar_cabecera( string $estado, int $total ): void {
		$severidad = 'success' === $estado
			? self::SEVERITY_OK
			: ( 'warning' === $estado ? self::SEVERITY_WARNING : self::SEVERITY_ERROR );
		?>
		<div class="convoca-diagnostic-header convoca-diagnostic-header--<?php echo esc_attr( $estado ); ?>">
			<div class="convoca-diagnostic-icon"><?php echo esc_html( self::icono( $severidad ) ); ?></div>
			<div class="convoca-diagnostic-summary">
				<h3><?php echo esc_html( self::resumen( $estado ) ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %d: number of checks run */
						esc_html__( '%d comprobaciones realizadas.', 'convoca-core' ),
						$total
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Una fila de la tabla de resultados.
	 *
	 * @param array<string, mixed> $resultado Una comprobación.
	 */
	private static function pintar_fila( array $resultado ): void {
		?>
		<div class="convoca-diagnostic-row">
			<div class="convoca-diagnostic-severity convoca-badge--<?php echo esc_attr( self::clase_badge( $resultado['severity'] ) ); ?>">
				<?php echo esc_html( self::icono( $resultado['severity'] ) ); ?>
			</div>
			<div class="convoca-diagnostic-content">
				<strong><?php echo esc_html( $resultado['label'] ); ?></strong>
				<div class="convoca-diagnostic-message"><?php echo esc_html( $resultado['message'] ); ?></div>
				<?php if ( ! empty( $resultado['fix'] ) ) : ?>
					<div class="convoca-diagnostic-fix"><?php echo esc_html( $resultado['fix'] ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Resumen en una línea del estado global.
	 *
	 * @param string $estado error, warning o success.
	 */
	private static function resumen( string $estado ): string {
		if ( 'error' === $estado ) {
			return __( 'Se encontraron errores', 'convoca-core' );
		}
		if ( 'warning' === $estado ) {
			return __( 'Atención: algunas comprobaciones requieren revisión', 'convoca-core' );
		}
		return __( 'Todo correcto', 'convoca-core' );
	}

	/**
	 * Icono de una severidad.
	 *
	 * @param string $severity Una de las constantes SEVERITY_*.
	 */
	private static function icono( string $severity ): string {
		if ( self::SEVERITY_OK === $severity ) {
			return '✓';
		}
		return self::SEVERITY_WARNING === $severity ? '⚠' : '✗';
	}

	/**
	 * Clase CSS del badge para una severidad.
	 *
	 * @param string $severity Una de las constantes SEVERITY_*.
	 */
	private static function clase_badge( string $severity ): string {
		if ( self::SEVERITY_OK === $severity ) {
			return 'success';
		}
		return self::SEVERITY_WARNING === $severity ? 'warning' : 'error';
	}
}
