<?php
/**
 * Copia de seguridad: lo que la importación puede escribir y lo que no.
 *
 * Restaurar un ZIP toca **todas** las entidades del sitio de golpe, así que aquí lo que se prueba es la integridad
 * de lo que queda escrito:
 *
 * - **referencias que no se rompen**: una inscripción importada apunta al proyecto nuevo por su id antiguo; si no
 *   hay correspondencia se conserva la referencia original, no se inventa un `0`;
 * - **usuarios que existen**: un turno cuyo responsable ya no está en el sitio queda sin responsable, no con un id
 *   fantasma;
 * - **sólo los ajustes permitidos**: un ZIP no puede tocar `siteurl` ni `users_can_register`; y lo que sobrescribe
 *   deja copia previa.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Admin_Backup;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/Backups.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Backup.php';

if ( ! defined( 'CONVOCA_IMAGES_URL' ) ) {
	define( 'CONVOCA_IMAGES_URL', 'https://example.test/img/' );
}

/**
 * Exportación e importación.
 */
final class AdminBackupTest extends TestCase {

	/**
	 * Copia de seguridad bajo prueba.
	 *
	 * @var Admin_Backup
	 */
	private Admin_Backup $backup;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		unset( $GLOBALS['_test_user_id'], $GLOBALS['_test_caps'] );
		$GLOBALS['_wp_stores'] = array(
			'options'       => array(),
			'post_meta'     => array(),
			'transients'    => array( 'convoca_logger_table_exists' => 1 ),
			'posts'         => array(),
			'usuarios'      => array(),
			'db_inserts'    => array(),
			'queries'       => array(),
			'db_reads'      => array(),
			'submenu_pages' => array(),
		);
		$GLOBALS['_test_caps'] = array();
		$this->backup          = new Admin_Backup();
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, ...$args ) {
		$reflejo = new \ReflectionMethod( Admin_Backup::class, $metodo );

		return $reflejo->invoke( $this->backup, ...$args );
	}

	/**
	 * El constructor engancha los cuatro ganchos: menú, exportar e importar (vista previa y ejecución).
	 */
	public function test_engancha_los_ganchos(): void {
		$this->assertContains( array( $this->backup, 'register_page' ), (array) ( $GLOBALS['_wp_hooks']['admin_menu'] ?? array() ) );
		foreach ( array( 'convoca_export_backup', 'convoca_import_backup', 'convoca_import_backup_run' ) as $accion ) {
			$this->assertContains( array( $this->backup, $accion === 'convoca_export_backup' ? 'handle_export' : ( 'convoca_import_backup' === $accion ? 'handle_import_preview' : 'handle_import_run' ) ), (array) ( $GLOBALS['_wp_hooks'][ 'admin_post_' . $accion ] ?? array() ), "«{$accion}»" );
		}
	}

	/**
	 * La página se registra con **su** capacidad: no es `manage_options`, es `common_manage_backup`.
	 */
	public function test_la_pagina_de_copia(): void {
		$this->backup->register_page();

		$pagina = $GLOBALS['_wp_stores']['submenu_pages'][0] ?? array();
		$this->assertSame( 'convoca-core', $pagina['parent'] );
		$this->assertSame( 'common_manage_backup', $pagina['capability'] );
		$this->assertSame( 'conv-backup', $pagina['menu_slug'] );
		$this->assertSame( array( $this->backup, 'render' ), $pagina['callback'] );
	}

	/**
	 * Las entidades se llaman por su nombre, no por su slug.
	 */
	public function test_las_etiquetas(): void {
		$this->assertSame( 'Miembros / Socios', $this->privado( 'entity_label', 'miembros' ) );
		$this->assertSame( 'Inscripciones a Actividades', $this->privado( 'entity_label', 'inscripciones' ) );
		$this->assertSame( 'Proyectos y Actividades', $this->privado( 'entity_label', 'proyectos' ) );
		$this->assertSame( 'Turnos', $this->privado( 'entity_label', 'turnos' ) );
		$this->assertSame( 'Pagos', $this->privado( 'entity_label', 'pagos' ), 'lo desconocido se capitaliza' );
	}

	/**
	 * Un socio importado escribe sus seis campos, con el correo saneado y estado por defecto `activo`.
	 */
	public function test_importar_un_socio(): void {
		$this->privado(
			'apply_meta',
			'miembros',
			77,
			array( '77', 'Nombre', '  Ada@Example.TEST ', ' 12345678Z ', 'premium', '', '2027-03-01' )
		);

		$meta = (array) ( $GLOBALS['_wp_stores']['post_meta'][77] ?? array() );
		// WordPress no pasa el correo a minúsculas: quita los espacios y poco más. La prueba no debe inventar más.
		$this->assertSame( 'Ada@Example.TEST', $meta['_convoca_email'], 'sin espacios de sobra' );
		$this->assertSame( '12345678Z', $meta['_convoca_dni'], 'sin espacios de sobra' );
		$this->assertSame( 'premium', $meta['_convoca_plan'] );
		$this->assertSame( 'activo', $meta['_convoca_estado_miembro'], 'un socio sin estado es activo' );
		$this->assertSame( '2027-03-01', $meta['_convoca_vencimiento'] );
	}

	/**
	 * **La referencia a la actividad se remapea** al id nuevo: importar no puede dejar apuntando al id del sitio
	 * de origen.
	 */
	public function test_la_inscripcion_apunta_al_proyecto_nuevo(): void {
		$GLOBALS['_wp_stores']['posts'][900] = (object) array(
			'ID'          => 900,
			'post_type'   => 'proyecto',
			'post_status' => 'publish',
			'post_title'  => 'Actividad de origen',
			'post_date'   => '2026-01-01 10:00:00',
		);
		$GLOBALS['_wp_stores']['post_meta'][900] = array( '_convoca_old_import_id' => '55' );

		$this->privado( 'apply_meta', 'inscripciones', 80, array( '80', 'Ana', 'ana@example.test', 'confirmada', '55' ) );

		$meta = (array) ( $GLOBALS['_wp_stores']['post_meta'][80] ?? array() );
		$this->assertSame( 900, (int) $meta['_convoca_actividad_id'], 'al proyecto nuevo' );
	}

	/**
	 * Si el proyecto no vino en el ZIP, se conserva la referencia original en vez de inventar un cero.
	 */
	public function test_si_no_hay_proyecto_se_conserva_la_referencia(): void {
		$this->privado( 'apply_meta', 'inscripciones', 80, array( '80', 'Ana', 'ana@example.test', 'confirmada', '55' ) );

		$meta = (array) ( $GLOBALS['_wp_stores']['post_meta'][80] ?? array() );
		$this->assertSame( 55, (int) $meta['_convoca_actividad_id'] );
	}

	/**
	 * **Un responsable que ya no existe deja el turno sin responsable**, no con un id fantasma.
	 */
	public function test_un_responsable_inexistente_no_se_guarda(): void {
		$this->privado( 'apply_meta', 'turnos', 90, array( '90', 'Mañana', '404', 'pendiente', '14:00' ) );

		$meta = (array) ( $GLOBALS['_wp_stores']['post_meta'][90] ?? array() );
		$this->assertSame( 0, (int) $meta['_id_responsable'], 'sin usuario, sin responsable' );
	}

	/**
	 * Y si existe, se conserva.
	 */
	public function test_un_responsable_existente_se_guarda(): void {
		$GLOBALS['_wp_stores']['usuarios'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Ada',
			'user_email'   => 'ada@example.test',
			'roles'        => array( 'subscriber' ),
		);

		$this->privado( 'apply_meta', 'turnos', 90, array( '90', 'Mañana', '7', 'pendiente', '14:00' ) );

		$meta = (array) ( $GLOBALS['_wp_stores']['post_meta'][90] ?? array() );
		$this->assertSame( 7, (int) $meta['_id_responsable'] );
	}

	/**
	 * Los ajustes: se importan los de la lista y **se deja fuera lo que no está** (un ZIP no cambia `siteurl`).
	 */
	public function test_los_ajustes_solo_de_la_lista_blanca(): void {
		$zip = new \ZipArchive();
		$ruta = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-backup-test-' . getmypid() . '.zip';
		$this->assertTrue( $zip->open( $ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) );
		$zip->addFromString(
			'settings.json',
			(string) wp_json_encode(
				array(
					'convoca_members_settings' => array( 'cuota' => 30 ),
					'siteurl'                  => 'https://malo.example.test',
					'users_can_register'       => 1,
				)
			)
		);
		$zip->close();

		$zip = new \ZipArchive();
		$zip->open( $ruta );

		$this->privado( 'import_settings', $zip );
		$zip->close();
		@unlink( $ruta );

		$this->assertSame( array( 'cuota' => 30 ), $GLOBALS['_wp_stores']['options']['convoca_members_settings'], 'el ajuste permitido entra' );
		$this->assertArrayNotHasKey( 'siteurl', $GLOBALS['_wp_stores']['options'], 'la URL del sitio no se toca' );
		$this->assertArrayNotHasKey( 'users_can_register', $GLOBALS['_wp_stores']['options'], 'ni el registro abierto' );
	}

	/**
	 * Antes de sobrescribir se guarda copia de lo que había (issue core#9).
	 */
	public function test_antes_de_sobrescribir_se_guarda_copia(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_settings'] = array( 'cuota' => 12 );

		$zip = new \ZipArchive();
		$ruta = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-backup-test2-' . getmypid() . '.zip';
		$zip->open( $ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( 'settings.json', (string) wp_json_encode( array( 'convoca_members_settings' => array( 'cuota' => 30 ) ) ) );
		$zip->close();

		$zip = new \ZipArchive();
		$zip->open( $ruta );
		$this->privado( 'import_settings', $zip );
		$zip->close();
		@unlink( $ruta );

		$copias = (array) ( $GLOBALS['_wp_stores']['options']['convoca_members_settings_backups'] ?? array() );
		$this->assertNotEmpty( $copias, 'hay copia' );
		$this->assertSame( array( 'cuota' => 12 ), $copias[0]['datos'] ?? null, 'con el valor anterior' );
	}

	/**
	 * Pinta la pantalla.
	 *
	 * @return string
	 */
	private function pantalla(): string {
		ob_start();
		try {
			$this->backup->render();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Sin la capacidad de la copia no se pinta nada: la pantalla muere antes.
	 */
	public function test_sin_permiso_la_pantalla_muere(): void {
		$GLOBALS['_test_caps']    = array( 'common_manage_backup' => false );
		$GLOBALS['_wp_die_lanza'] = true;

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );
		$this->pantalla();
	}

	/**
	 * Sin previsualización se pinta la pantalla pero **no** el bloque de contenido detectado: no hay nada que importar.
	 */
	public function test_sin_previsualizacion_no_hay_bloque_de_contenido(): void {
		$GLOBALS['_test_caps'] = array( 'common_manage_backup' => true );

		$html = $this->pantalla();

		$this->assertStringContainsString( 'Copia de Seguridad y Mantenimiento', $html );
		$this->assertStringNotContainsString( 'Contenido Detectado', $html );
		$this->assertStringNotContainsString( 'Ejecutar Importación', $html );
	}

	/**
	 * Con previsualización: el token viaja con el formulario y cada entidad sale con su nombre y su recuento.
	 */
	public function test_con_previsualizacion(): void {
		$GLOBALS['_test_caps']    = array( 'common_manage_backup' => true );
		$GLOBALS['_test_user_id'] = 7;
		$GLOBALS['_wp_stores']['transients']['convoca_import_preview_' . get_current_user_id()] = array(
			'token'    => 'abc123',
			'entities' => array(
				'miembros'  => array(
					'label' => 'Miembros / Socios',
					'count' => 12,
				),
				'proyectos' => array(
					'label' => 'Proyectos y Actividades',
					'count' => 3,
				),
			),
		);

		$html = $this->pantalla();

		$this->assertStringContainsString( 'Contenido Detectado', $html );
		$this->assertStringContainsString( 'value="abc123"', $html, 'el token viaja con el formulario' );
		$this->assertStringContainsString( 'Miembros / Socios', $html );
		$this->assertStringContainsString( '12 registros', $html );
		$this->assertStringContainsString( 'value="proyectos"', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html, 'con su nonce' );
	}

	/**
	 * Una entidad que no conoce no escribe nada: una fila de más no puede tocar metas ajenos.
	 */
	public function test_una_entidad_desconocida_no_escribe(): void {
		$this->privado( 'apply_meta', 'pagos', 401, array( '401', 'x', 'y' ) );

		$this->assertArrayNotHasKey( 401, $GLOBALS['_wp_stores']['post_meta'] );
	}

	/**
	 * Un `settings.json` que no es JSON, o que es un escalar, se ignora sin romper ni escribir nada.
	 */
	public function test_un_settings_json_que_no_vale_se_ignora(): void {
		$ruta = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-backup-test3-' . getmypid() . '.zip';

		foreach ( array( 'no es json', '"solo texto"', '[]' . str_repeat( ' ', 0 ) ) as $i => $contenido ) {
			$zip = new \ZipArchive();
			$zip->open( $ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
			$zip->addFromString( 'settings.json', $contenido );
			$zip->close();

			$zip = new \ZipArchive();
			$zip->open( $ruta );
			$this->privado( 'import_settings', $zip );
			$zip->close();
		}
		@unlink( $ruta );

		$this->assertArrayNotHasKey( 'convoca_members_settings', $GLOBALS['_wp_stores']['options'] );
	}
}
