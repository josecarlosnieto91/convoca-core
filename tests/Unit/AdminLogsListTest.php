<?php
/**
 * La pantalla de registros: las columnas y las acciones en bloque.
 *
 * El registro es donde se mira un incidente, así que aquí hay dos cosas que importan más de lo que parece:
 *
 * - **el mensaje se pinta escapado**. Un registro puede contener lo que escribió un atacante (una ruta, un
 *   parámetro): si se pinta crudo, el registro se convierte en la vía de entrada al panel;
 * - **la purga borra por fecha**, no todo: `purge_30` tiene que llevarse lo de más de 30 días y dejar el resto.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin_Logs_List;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Logs_List.php';

/**
 * Listado de registros.
 */
final class AdminLogsListTest extends TestCase {

	/**
	 * Listado.
	 *
	 * @var Admin_Logs_List
	 */
	private Admin_Logs_List $lista;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['_wp_stores'], $_GET['paged'], $_REQUEST['action'], $_REQUEST['action2'], $_POST['log_ids'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'db_inserts' => array(),
			'queries'    => array(),
		);

		$this->lista = new Admin_Logs_List();
	}

	/**
	 * Llama a un método protegido (las columnas lo son).
	 *
	 * @param string $metodo Método.
	 * @param mixed  $item   Fila.
	 * @return string
	 */
	private function columna( string $metodo, $item ): string {
		$reflejo = new \ReflectionMethod( Admin_Logs_List::class, $metodo );

		return (string) $reflejo->invoke( $this->lista, $item );
	}

	/**
	 * Una fila del registro.
	 *
	 * @param array $datos Datos.
	 * @return object
	 */
	private function fila( array $datos = array() ): object {
		return (object) array_merge(
			array(
				'id'         => 55,
				'created_at' => '2026-10-23 20:15:00',
				'level'      => 'info',
				'context'    => 'Common/Security',
				'message'    => 'Acceso no autorizado',
				'user_id'    => 0,
				'object_id'  => 0,
			),
			$datos
		);
	}

	/**
	 * Las columnas de la tabla y cuáles se pueden ordenar.
	 */
	public function test_las_columnas(): void {
		$columnas = $this->lista->get_columns();

		foreach ( array( 'cb', 'created_at', 'level', 'context', 'message', 'user_id', 'object_id' ) as $clave ) {
			$this->assertArrayHasKey( $clave, $columnas, "la columna «{$clave}» está" );
		}
		$ordenables = $this->lista->get_sortable_columns();
		$this->assertSame( array( 'created_at', 'level', 'context' ), array_keys( $ordenables ), 'y tres se ordenan' );
		$this->assertTrue( $ordenables['created_at'][1], 'la fecha empieza de más reciente a más antigua' );
	}

	/**
	 * La fecha se pinta legible.
	 */
	public function test_la_fecha(): void {
		$this->assertSame( '<strong>23/10/2026 20:15:00</strong>', $this->columna( 'column_created_at', $this->fila() ) );
	}

	/**
	 * **El mensaje se escapa**: un registro con HTML no puede inyectar nada en el panel.
	 */
	public function test_el_mensaje_se_escapa(): void {
		$salida = $this->columna( 'column_message', $this->fila( array( 'message' => 'Ruta <script>alert(1)</script> pedida' ) ) );

		$this->assertStringNotContainsString( '<script>', $salida, 'ni una etiqueta viva' );
		$this->assertStringContainsString( '&lt;script&gt;', $salida, 'sale escapado' );
	}

	/**
	 * Un mensaje largo se recorta a 200 y ofrece el resto, con el texto completo escapado en el atributo.
	 */
	public function test_el_mensaje_largo(): void {
		$largo  = str_repeat( 'a', 250 );
		$salida = $this->columna( 'column_message', $this->fila( array( 'message' => $largo ) ) );

		$this->assertStringContainsString( 'ver más', $salida, 'ofrece el resto' );
		$this->assertStringContainsString( 'data-log-message="' . $largo . '"', $salida, 'y lleva el texto completo' );
		$this->assertSame(
			200,
			mb_strlen( trim( html_entity_decode( wp_strip_all_tags( explode( '<span', $salida )[0] ) ) ) ),
			'sin el corte, la tabla se desmadra'
		);
	}

	/**
	 * Un mensaje corto no lleva el «ver más».
	 */
	public function test_el_mensaje_corto_no_lleva_vernas(): void {
		$this->assertStringNotContainsString( 'ver más', $this->columna( 'column_message', $this->fila() ) );
	}

	/**
	 * El nivel va con su insignia y **su color**: un error y un aviso no pueden verse igual en el registro, que es
	 * donde se busca un incidente a ojo.
	 */
	public function test_el_nivel_lleva_su_insignia(): void {
		$this->assertStringContainsString( 'convoca-badge--error', $this->columna( 'column_level', $this->fila( array( 'level' => 'error' ) ) ) );
		$this->assertStringContainsString( 'convoca-badge--warning', $this->columna( 'column_level', $this->fila( array( 'level' => 'warning' ) ) ) );
		$this->assertStringContainsString( 'convoca-badge--info', $this->columna( 'column_level', $this->fila( array( 'level' => 'info' ) ) ) );
		$this->assertStringContainsString( 'Error', $this->columna( 'column_level', $this->fila( array( 'level' => 'error' ) ) ), 'y el nivel legible' );
	}

	/**
	 * El contexto va en su etiqueta y escapado (lo escribe el propio código, pero se comprueba).
	 */
	public function test_el_contexto(): void {
		$salida = $this->columna( 'column_context', $this->fila( array( 'context' => 'Gateway/Redsys' ) ) );

		$this->assertStringContainsString( '<code', $salida );
		$this->assertStringContainsString( 'Gateway/Redsys', $salida );
	}

	/**
	 * El usuario: sin usuario se marca el hueco; con usuario, su nombre; si ya no existe, el número.
	 */
	public function test_el_usuario(): void {
		$this->assertStringContainsString( '—', $this->columna( 'column_user_id', $this->fila() ), 'sin usuario' );

		$GLOBALS['_wp_stores']['usuarios'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Ada Lovelace',
			'user_email'   => 'ada@example.test',
			'roles'        => array( 'administrator' ),
		);
		$this->assertSame( 'Ada Lovelace', $this->columna( 'column_user_id', $this->fila( array( 'user_id' => 7 ) ) ) );

		$this->assertSame( '#9', $this->columna( 'column_user_id', $this->fila( array( 'user_id' => 9 ) ) ), 'un usuario que ya no está' );
	}

	/**
	 * El id de objeto: con id se pinta, sin id se marca el hueco.
	 */
	public function test_el_id_de_objeto(): void {
		$this->assertStringContainsString( '#123', $this->columna( 'column_object_id', $this->fila( array( 'object_id' => 123 ) ) ) );
		$this->assertStringContainsString( '—', $this->columna( 'column_object_id', $this->fila() ) );
		// Un id con basura se pinta como número: si se colara el texto tal cual, el `#12abc` acabaría en el panel.
		$this->assertStringContainsString( '#12<', $this->columna( 'column_object_id', $this->fila( array( 'object_id' => '12abc' ) ) ) );
	}

	/**
	 * La casilla de selección lleva el id de la fila (si no, no se puede borrar la que quieres).
	 */
	public function test_la_casilla(): void {
		$this->assertStringContainsString( 'value="55"', $this->columna( 'column_cb', $this->fila() ) );
	}

	/**
	 * Las acciones en bloque son dos, y están escritas para que se entiendan.
	 */
	public function test_las_acciones_en_bloque(): void {
		$acciones = $this->lista->get_bulk_actions();

		$this->assertArrayHasKey( 'delete', $acciones );
		$this->assertArrayHasKey( 'purge_30', $acciones, 'la purga por antigüedad' );
	}

	/**
	 * Borrar seleccionados: se borran **los marcados**, con sus ids convertidos a número.
	 */
	public function test_borrar_los_marcados(): void {
		$_REQUEST['action'] = 'delete';
		$_POST['log_ids']   = array( '5', '6', 'no-es-un-numero' );

		$this->lista->process_bulk_action();

		$consultas = implode( ' | ', (array) $GLOBALS['_wp_stores']['queries'] );
		$this->assertStringContainsString( 'DELETE FROM wp_convoca_logs', $consultas, 'se borra de la tabla de registros' );
		$this->assertStringContainsString( 'IN (5,6,0)', $consultas, 'con los ids marcados (y el que no era número, a 0)' );
	}

	/**
	 * La purga por antigüedad borra **por fecha** y deja constancia de cuántos se fueron.
	 */
	public function test_la_purga_por_antiguedad(): void {
		$_REQUEST['action'] = 'purge_30';

		$this->lista->process_bulk_action();

		$consultas = implode( ' | ', (array) $GLOBALS['_wp_stores']['queries'] );
		$this->assertStringContainsString( 'DELETE FROM wp_convoca_logs WHERE created_at <', $consultas, 'borra por fecha' );

		$hace_30 = wp_date( 'Y-m-d', strtotime( '-30 days' ) );
		$this->assertStringContainsString( $hace_30, $consultas, 'el corte son 30 días' );
	}

	/**
	 * Con la lista de ids vacía no se borra: un formulario sin marcar nada no puede llevarse el registro entero.
	 */
	public function test_sin_ids_marcados_no_borra(): void {
		$_REQUEST['action'] = 'delete';
		$_POST['log_ids']   = array();

		$this->lista->process_bulk_action();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['queries'], 'sin ids no hay consulta de borrado' );
	}

	/**
	 * Y con una acción que no es de borrado, tampoco: los ids en el formulario no bastan.
	 */
	public function test_con_otra_accion_no_borra(): void {
		$_REQUEST['action'] = 'filtrar';
		$_POST['log_ids']   = array( '5' );

		$this->lista->process_bulk_action();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['queries'], 'mirar no es borrar' );
	}

	/**
	 * Sin acción no se borra nada: abrir la pantalla no puede vaciar el registro.
	 */
	public function test_sin_accion_no_borra(): void {
		$this->lista->process_bulk_action();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['queries'], 'ni una consulta' );
	}

	/**
	 * Sin pantalla de administración (WP-CLI, REST) la tabla avisa en vez de romperse.
	 */
	public function test_sin_pantalla_avisa(): void {
		ob_start();
		$this->lista->display();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( 'requiere el panel de administración', $salida );
	}
}
