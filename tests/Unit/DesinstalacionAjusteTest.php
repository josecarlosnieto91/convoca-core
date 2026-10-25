<?php
/**
 * El ajuste de desinstalación del ecosistema.
 *
 * `Uninstall_Settings.php` estaba al 0 % (56 statements) y decide, para los siete plugins a la vez,
 * si al desinstalar se conservan los datos o se borran. Es la opción que leen todos los `uninstall.php`:
 * si la guarda se rompe, una desinstalación se lleva por delante la base del centro.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class DesinstalacionAjusteTest extends TestCase
{
    /**
     * Prepara el caso.
     */
    protected function setUp(): void
    {
        $GLOBALS['_wp_stores'] = array('options' => array(), 'submenu_pages' => array());
        $GLOBALS['_test_caps'] = array('manage_options' => true);
        $_POST = array();
        unset($GLOBALS['_wp_die_llamado'], $GLOBALS['_wp_die_lanza']);

        require_once dirname(__DIR__, 2) . '/includes/Uninstall_Settings.php';
    }

    /**
     * Deja el arnés como estaba.
     */
    protected function tearDown(): void
    {
        $_POST = array();
        unset($GLOBALS['_wp_stores'], $GLOBALS['_test_caps'], $GLOBALS['_wp_die_llamado'], $GLOBALS['_wp_die_lanza']);
    }

    /**
     * La página se registra en el menú de Convoca, con su capacidad.
     */
    public function test_la_pagina_se_registra_en_el_menu(): void
    {
        \Convoca\Core\convoca_register_uninstall_settings();

        $paginas = $GLOBALS['_wp_stores']['submenu_pages'];
        $this->assertCount(1, $paginas);
        $this->assertSame('convoca-core', $paginas[0]['parent']);
        $this->assertSame('convoca-uninstall', $paginas[0]['menu_slug']);
        $this->assertSame('manage_options', $paginas[0]['capability']);
        $this->assertSame('Convoca\Core\convoca_uninstall_settings_page', $paginas[0]['callback']);
    }

    /**
     * Sin nonce no se guarda nada (ni se toca la opción).
     */
    public function test_sin_nonce_no_se_guarda(): void
    {
        \Convoca\Core\convoca_maybe_save_uninstall_settings();

        $this->assertArrayNotHasKey('convoca_uninstall_keep_data', $GLOBALS['_wp_stores']['options']);

        $_POST['convoca_uninstall_keep_data'] = '1';
        \Convoca\Core\convoca_maybe_save_uninstall_settings();

        $this->assertArrayNotHasKey('convoca_uninstall_keep_data', $GLOBALS['_wp_stores']['options'], 'sin el campo de nonce no se guarda');
    }

    /**
     * Con un nonce que no vale tampoco.
     */
    public function test_un_nonce_invalido_no_guarda(): void
    {
        $GLOBALS['_wp_stores']['nonces_validos'] = array('el-bueno');
        $_POST['convoca_uninstall_nonce'] = 'el-malo';
        $_POST['convoca_uninstall_keep_data'] = '1';

        \Convoca\Core\convoca_maybe_save_uninstall_settings();

        $this->assertArrayNotHasKey('convoca_uninstall_keep_data', $GLOBALS['_wp_stores']['options']);
    }

    /**
     * Sin permiso, el guardado muere en vez de escribir.
     */
    public function test_sin_permiso_el_guardado_muere(): void
    {
        $GLOBALS['_test_caps']['manage_options'] = false;
        $GLOBALS['_wp_die_lanza'] = true;
        $_POST['convoca_uninstall_nonce'] = 'lo-que-sea';
        $_POST['convoca_uninstall_keep_data'] = '1';

        try {
            \Convoca\Core\convoca_maybe_save_uninstall_settings();
            $this->fail('sin permiso no debería seguir');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('permisos', $e->getMessage());
        }

        $this->assertArrayNotHasKey('convoca_uninstall_keep_data', $GLOBALS['_wp_stores']['options'], 'no se escribe nada');
    }

    /**
     * Con nonce bueno y permiso: la casilla marcada guarda 1 y desmarcada guarda 0.
     */
    public function test_con_nonce_y_permiso_se_guarda_lo_que_dice_la_casilla(): void
    {
        $_POST['convoca_uninstall_nonce'] = 'vale';
        $_POST['convoca_uninstall_keep_data'] = '1';

        \Convoca\Core\convoca_maybe_save_uninstall_settings();
        $this->assertSame(1, $GLOBALS['_wp_stores']['options']['convoca_uninstall_keep_data'], 'marcada: se conservan los datos');

        unset($_POST['convoca_uninstall_keep_data']);
        \Convoca\Core\convoca_maybe_save_uninstall_settings();
        $this->assertSame(0, $GLOBALS['_wp_stores']['options']['convoca_uninstall_keep_data'], 'desmarcada: se borra todo');
    }

    /**
     * La pantalla pinta la casilla en el estado guardado.
     */
    public function test_la_pantalla_pinta_la_casilla_en_su_estado(): void
    {
        $GLOBALS['_wp_stores']['options']['convoca_uninstall_keep_data'] = 1;

        ob_start();
        \Convoca\Core\convoca_uninstall_settings_page();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Desinstalación de Convoca', $html);
        $this->assertMatchesRegularExpression('/convoca_uninstall_keep_data[^>]*checked/', $html, 'con la opción a 1 la casilla sale marcada');
        $this->assertStringContainsString('convoca_uninstall_nonce', $html, 'el formulario lleva su nonce');

        $GLOBALS['_wp_stores']['options']['convoca_uninstall_keep_data'] = 0;

        ob_start();
        \Convoca\Core\convoca_uninstall_settings_page();
        $html = (string) ob_get_clean();

        $this->assertDoesNotMatchRegularExpression('/convoca_uninstall_keep_data[^>]*checked/', $html, 'con la opción a 0 sale sin marcar');
    }

    /**
     * Sin permiso, la pantalla no se pinta.
     */
    public function test_sin_permiso_la_pantalla_no_se_pinta(): void
    {
        $GLOBALS['_test_caps']['manage_options'] = false;
        $GLOBALS['_wp_die_lanza'] = true;

        $this->expectException(\RuntimeException::class);

        \Convoca\Core\convoca_uninstall_settings_page();
    }
}
