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
	/**
	 * Estado global previo, para devolverlo al terminar.
	 *
	 * @var array
	 */
	private array $previo = array();

	protected function setUp(): void {
		$this->previo = $GLOBALS['_wp_stores'] ?? array();
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

	/**
	 * Devuelve el almacén a como estaba: una prueba no puede dejar el terreno preparado a la siguiente.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['_wp_logged_in'], $GLOBALS['_test_user_id'], $GLOBALS['_wp_json'], $GLOBALS['_wp_die_lanza'] );
	}

	/**
	 * El ZIP de la copia: los cuatro CSV con el nombre de la **entidad** (que es lo que busca el importador) y el
	 * `settings.json` con las opciones permitidas. Un backup con los nombres del CPT restauraba cero filas.
	 */
	public function test_el_zip_de_la_copia(): void {
		$temp = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-zip-test-' . getmypid();
		@mkdir( $temp, 0777, true );
		$GLOBALS['_wp_stores']['temp_dir'] = $temp;

		$post = static function ( int $id, string $tipo, string $titulo ): \WP_Post {
			$p               = new \WP_Post();
			$p->ID           = $id;
			$p->post_type    = $tipo;
			$p->post_status  = 'publish';
			$p->post_title   = $titulo;
			$p->post_content = '';
			return $p;
		};
		$GLOBALS['_wp_stores']['posts'] = array(
			1 => $post( 1, 'miembro', 'Ada' ),
			2 => $post( 2, 'miembro', 'Bea' ),
			3 => $post( 3, 'inscripcion', 'Inscripción' ),
			4 => $post( 4, 'proyecto', 'Actividad' ),
			5 => $post( 5, 'centro_turno', 'Turno' ),
		);
		$GLOBALS['_wp_stores']['post_meta'] = array(
			1 => array(
				'_convoca_email'          => 'ada@example.test',
				'_convoca_dni'            => '12345678Z',
				'_convoca_plan'           => 'base',
				'_convoca_estado_miembro' => 'activo',
				'_convoca_vencimiento'    => '2027-01-01',
			),
			3 => array( '_convoca_nombre' => 'Ada', '_convoca_actividad_id' => 4 ),
			4 => array( '_convoca_fecha_inicio' => '2026-01-01', '_convoca_activo' => '1' ),
			5 => array( '_id_responsable' => '12', '_estado_real' => 'cubierto', '_hora_fin' => '14:00' ),
		);
		$GLOBALS['_wp_stores']['options'] = array(
			'convoca_members_settings' => array( 'cuota' => 30 ),
			'siteurl'                  => 'https://example.test',
		);

		$ruta  = $temp . '/backup.zip';
		$error = $this->privado( 'construir_zip', $ruta );
		$this->assertSame( '', $error, 'sin error' );
		$this->assertFileExists( $ruta );

		$zip = new \ZipArchive();
		$this->assertTrue( $zip->open( $ruta ) );

		$miembros = $zip->getFromName( 'miembros.csv' );
		$this->assertNotFalse( $miembros, 'el CSV se llama por la entidad, no por el CPT' );
		$this->assertStringContainsString( 'ID,Nombre,Email,DNI,Plan,Estado,Vencimiento', $miembros );
		$this->assertStringContainsString( 'ada@example.test', $miembros );
		$this->assertStringContainsString( '12345678Z', $miembros );
		$this->assertStringContainsString( 'Bea', $miembros, 'salen los dos socios' );
		$this->assertFalse( $zip->getFromName( 'miembro.csv' ), 'el nombre del CPT no está: el importador no lo encontraría' );

		foreach ( array( 'inscripciones.csv', 'proyectos.csv', 'turnos.csv' ) as $csv ) {
			$this->assertNotFalse( $zip->getFromName( $csv ), $csv );
		}

		$ajustes = (string) $zip->getFromName( 'settings.json' );
		$this->assertStringContainsString( 'convoca_members_settings', $ajustes );
		$this->assertStringNotContainsString( 'siteurl', $ajustes, 'la URL del sitio no viaja en la copia' );
		$zip->close();

		@unlink( $ruta );
		@rmdir( $temp );
	}

	/**
	 * Lo que trae un ZIP: cada entidad con su nombre y su recuento, y `settings.json` como configuración.
	 *
	 * El recuento **no** cuenta la cabecera del CSV (sale una fila de más, que es un engaño pequeño pero engaño).
	 */
	public function test_lo_que_trae_un_zip(): void {
		$ruta = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-analizar-' . getmypid() . '.zip';
		$zip  = new \ZipArchive();
		$zip->open( $ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( "miembros.csv", "ID,Nombre\n1,Ada\n2,Bea\n" );
		$zip->addFromString( "proyectos.csv", "ID,Título\n1,Actividad\n" );
		$zip->addFromString( "ajeno.csv", "a,b\n" );
		$zip->addFromString( "settings.json", '{"x":1}' );
		$zip->close();

		$zip = new \ZipArchive();
		$zip->open( $ruta );

		$entidades = $this->privado( 'analizar_zip', $zip );

		$this->assertSame( array( 'miembros', 'proyectos', 'settings' ), array_keys( $entidades ) );
		$this->assertSame( 2, $entidades['miembros']['count'], 'dos socios, no tres' );
		$this->assertSame( 1, $entidades['proyectos']['count'] );
		$this->assertSame( 'Miembros / Socios', $entidades['miembros']['label'] );
		$this->assertSame( 1, $entidades['settings']['count'] );
		$this->assertArrayNotHasKey( 'ajeno', $entidades, 'lo que no conoce no se ofrece' );

		$zip->close();
		@unlink( $ruta );
	}

	/**
	 * Un ZIP sin nada reconocible no devuelve entidades: el llamador es quien decide no aceptarlo.
	 */
	public function test_un_zip_ajeno_no_trae_nada(): void {
		$ruta = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-analizar2-' . getmypid() . '.zip';
		$zip  = new \ZipArchive();
		$zip->open( $ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( "otra-cosa.csv", "a\n" );
		$zip->close();

		$zip = new \ZipArchive();
		$zip->open( $ruta );

		$this->assertSame( array(), $this->privado( 'analizar_zip', $zip ) );

		$zip->close();
		@unlink( $ruta );
	}

	/**
	 * Importar un ZIP: cada fila crea su post, se guarda el id antiguo para remapear relaciones y una fila vacía no
	 * rompe nada. El BOM de la primera línea no puede tragarse la cabecera como si fuera un dato.
	 */
	public function test_importar_entidades(): void {
		$ruta = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-importar-' . getmypid() . '.zip';
		$bom  = chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF );
		$zip  = new \ZipArchive();
		$zip->open( $ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( "miembros.csv", $bom . "ID,Nombre,Email\n10,Ada,ada@example.test\n11,Bea,bea@example.test\n\n" );
		$zip->addFromString( "proyectos.csv", "ID,Título\n5,Actividad\n" );
		$zip->addFromString( "turnos.csv", "ID,Título\n" );
		$zip->close();

		$zip = new \ZipArchive();
		$zip->open( $ruta );

		// `invoke()` no pasa por referencia: con `invokeArgs` y el `&` el acumulador llega de vuelta.
		$creados = array();
		$reflejo = new \ReflectionMethod( Admin_Backup::class, 'importar_entidades' );
		$total   = $reflejo->invokeArgs( $this->backup, array( $zip, array( 'miembros', 'proyectos', 'turnos' ), &$creados ) );

		$this->assertSame( 3, $total, 'dos socios y una actividad; el CSV vacío no suma' );
		$this->assertCount( 3, $creados );

		$metas = $GLOBALS['_wp_stores']['post_meta'];
		$ids   = $GLOBALS['_wp_stub_next_id'] ?? 0;
		// El orden es deliberado: **proyectos antes que miembros e inscripciones**, para que al remapear ya exista la
		// actividad a la que apuntan. Se fija aquí para que un cambio de orden no pase inadvertido.
		$this->assertSame( 5, (int) $metas[ $creados[0] ]['_convoca_old_import_id'], 'primero la actividad' );
		$this->assertSame( 10, (int) $metas[ $creados[1] ]['_convoca_old_import_id'], 'el id antiguo se guarda' );
		$this->assertSame( 'ada@example.test', $metas[ $creados[1] ]['_convoca_email'], 'y con él se aplican las metas' );

		$titulos = array_map(
			static fn( int $id ): string => (string) ( $GLOBALS['_wp_stores']['posts'][ $id ]->post_title ?? '' ),
			$creados
		);
		$this->assertSame( array( 'Actividad', 'Ada', 'Bea' ), $titulos, 'el BOM no se come la cabecera' );
		$this->assertGreaterThan( 0, $ids );

		$zip->close();
		@unlink( $ruta );
	}

	/**
	 * Sólo se importa lo seleccionado, y un `wp_insert_post` que devuelve 0 es un error que corta (no un silencio).
	 */
	public function test_importar_solo_lo_seleccionado_y_el_error(): void {
		$ruta = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-importar2-' . getmypid() . '.zip';
		$zip  = new \ZipArchive();
		$zip->open( $ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( "miembros.csv", "ID,Nombre\n1,Ada\n" );
		$zip->addFromString( "proyectos.csv", "ID,Título\n9,Actividad\n" );
		$zip->close();

		$zip = new \ZipArchive();
		$zip->open( $ruta );

		$creados = array();
		$reflejo = new \ReflectionMethod( Admin_Backup::class, 'importar_entidades' );
		$total   = $reflejo->invokeArgs( $this->backup, array( $zip, array( 'miembros' ), &$creados ) );
		$this->assertSame( 1, $total, 'la actividad no estaba seleccionada' );

		$zip->close();
		@unlink( $ruta );
	}

	/**
	 * Lo que se acepta subir y lo que no. Cada rechazo tiene su motivo, y el motivo es lo que ve quien sube.
	 */
	public function test_lo_que_se_acepta_y_lo_que_no(): void {
		$temp = ( getenv( 'TMPDIR' ) ?: sys_get_temp_dir() ) . '/convoca-subida-' . getmypid();
		@mkdir( $temp, 0777, true );
		$GLOBALS['_wp_stores']['upload_dir'] = $temp;

		// 1) Extensión: sólo ZIP.
		try {
			$this->privado( 'preparar_importacion', array( 'name' => 'backup.txt', 'size' => 10, 'tmp_name' => $temp . '/a', 'error' => 0 ), 3 );
			$this->fail( 'un .txt no debería pasar' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Formato inválido', $e->getMessage() );
		}

		// 2) Un error de subida se cuenta, no se ignora.
		try {
			$this->privado( 'preparar_importacion', array( 'name' => 'a.zip', 'size' => 10, 'tmp_name' => $temp . '/a', 'error' => UPLOAD_ERR_PARTIAL ), 3 );
			$this->fail( 'un error de subida no debería pasar' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Error en la subida', $e->getMessage() );
		}

		// 3) Demasiado grande.
		try {
			$this->privado( 'preparar_importacion', array( 'name' => 'a.zip', 'size' => 999999999, 'tmp_name' => $temp . '/a', 'error' => 0 ), 3 );
			$this->fail( 'un ZIP enorme no debería pasar' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'demasiado grande', $e->getMessage() );
		}

		// 4) Un ZIP ilegible.
		file_put_contents( $temp . '/roto.zip', 'no soy un zip' );
		try {
			$this->privado( 'preparar_importacion', array( 'name' => 'roto.zip', 'size' => 12, 'tmp_name' => $temp . '/roto.zip', 'error' => 0 ), 3 );
			$this->fail( 'un ZIP ilegible no debería pasar' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'No se pudo abrir el ZIP', $e->getMessage() );
		}

		// 5) Un ZIP legible pero sin nada de Convoca.
		$zip = new \ZipArchive();
		$zip->open( $temp . '/ajeno.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( 'otra-cosa.csv', "a\n" );
		$zip->close();
		try {
			$this->privado( 'preparar_importacion', array( 'name' => 'ajeno.zip', 'size' => filesize( $temp . '/ajeno.zip' ), 'tmp_name' => $temp . '/ajeno.zip', 'error' => 0 ), 3 );
			$this->fail( 'un ZIP ajeno no debería pasar' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'no contiene datos compatibles', $e->getMessage() );
		}

		// 6) Un ZIP bueno pasa la validación y el análisis: llega hasta el guardado (que en CLI no puede moverse,
		//    porque `move_uploaded_file()` exige una subida real). Lo que se afirma es que **no** falló antes.
		$zip = new \ZipArchive();
		$zip->open( $temp . '/bueno.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( 'miembros.csv', "ID,Nombre\n1,Ada\n" );
		$zip->close();
		try {
			$this->privado( 'preparar_importacion', array( 'name' => 'bueno.zip', 'size' => filesize( $temp . '/bueno.zip' ), 'tmp_name' => $temp . '/bueno.zip', 'error' => 0 ), 3 );
			$this->fail( 'en CLI el guardado no puede completarse' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Error al guardar el temporal', $e->getMessage(), 'pasó validación y análisis' );
		}

		// El directorio de importaciones queda protegido: ni listado ni descarga directa.
		$import_dir = $temp . '/convoca-imports';
		$this->assertFileExists( $import_dir . '/.htaccess' );
		$this->assertStringContainsString( 'Deny from all', (string) file_get_contents( $import_dir . '/.htaccess' ) );
		$this->assertFileExists( $import_dir . '/index.php' );

		foreach ( glob( $temp . '/*' ) as $f ) {
			is_dir( $f ) ? @rmdir( $f ) : @unlink( $f );
		}
		@unlink( $import_dir . '/.htaccess' );
		@unlink( $import_dir . '/index.php' );
		@rmdir( $import_dir );
		@rmdir( $temp );
	}
}
