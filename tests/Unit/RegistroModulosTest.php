<?php
/**
 * El registro de módulos externos.
 *
 * `class-module-registry.php` estaba al 0 % (59 statements) y es la puerta por la que entra cualquier
 * módulo del marketplace: valida el contrato (versión del núcleo, capacidades PRO, permisos), avisa
 * cuando un módulo no puede correr y dispara su arranque. Si esta validación se afloja, un módulo
 * incompatible se arranca igual y el fallo aparece lejos, en otra pantalla.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\License_Manager;
use Convoca\Core\Module_Registry;
use PHPUnit\Framework\TestCase;

class RegistroModulosTest extends TestCase
{
    /**
     * Prepara el caso y deja el registro vacío.
     */
    protected function setUp(): void
    {
        $GLOBALS['_wp_stores'] = array('options' => array());
        $GLOBALS['_wp_filters'] = array();
        $GLOBALS['_wp_actions'] = array();
        $GLOBALS['_test_caps'] = array();
        $this->reiniciar();
    }

    /**
     * Deja el registro como estaba.
     */
    protected function tearDown(): void
    {
        $this->reiniciar();
        unset($GLOBALS['_wp_stores'], $GLOBALS['_wp_filters'], $GLOBALS['_wp_actions'], $GLOBALS['_test_caps']);
    }

    /**
     * Vacía los estáticos privados del registro (`booted` incluido) por reflexión.
     */
    private function reiniciar(): void
    {
        foreach (array('modules' => array(), 'booted' => false) as $propiedad => $valor) {
            // Sin setAccessible(): desde PHP 8.1 no hace nada y en 8.5 emite aviso de deprecación.
            $reflejo = new \ReflectionProperty(Module_Registry::class, $propiedad);
            $reflejo->setValue(null, $valor);
        }
    }

    /**
     * Un módulo que cumple el contrato se registra, se arranca y se anuncia.
     */
    public function test_un_modulo_valido_se_registra_y_arranca(): void
    {
        $arrancado = array();

        // `core_min` por debajo de cualquier versión real: otras pruebas del repo definen
        // CONVOCA_COMMON_VERSION y un mínimo alto dejaba el módulo incompatible sin querer.
        Module_Registry::register('mi-modulo', array('core_min' => '0.0.1', 'bootstrap' => function ($slug, $definicion) use (&$arrancado) {
            $arrancado = array($slug, $definicion['name']);
        }));

        $this->assertTrue(Module_Registry::is_active('mi-modulo'));
        $this->assertSame(array('mi-modulo', 'mi-modulo'), $arrancado, 'el arranque recibe el slug y la definición');

        $modulos = Module_Registry::get_modules();
        $this->assertSame('1.0.0', $modulos['mi-modulo']['version'], 'se rellenan los valores por defecto');
        $anunciados = array();
        foreach ((array) $GLOBALS['_wp_actions'] as $accion) {
            if ('convoca_module_registered' === $accion['hook']) {
                $anunciados[] = $accion['args'][0];
            }
        }
        $this->assertContains('mi-modulo', $anunciados, 'se anuncia el registro');
    }

    /**
     * El slug se sanea; sin slug no hay módulo.
     */
    public function test_el_slug_se_sanea_y_el_vacio_no_entra(): void
    {
        Module_Registry::register('Mi Módulo!!', array('core_min' => '0.0.1'));

        $this->assertTrue(Module_Registry::is_active('mimdulo'), 'se sanea el identificador');
        $this->assertFalse(Module_Registry::is_active('Mi Módulo!!'));

        Module_Registry::register('', array('core_min' => '0.0.1'));
        $this->assertCount(1, Module_Registry::get_modules(), 'un slug vacío no registra nada');
    }

    /**
     * Un módulo que pide más núcleo del que hay no se arranca y avisa.
     */
    public function test_un_modulo_que_pide_mas_nucleo_no_arranca(): void
    {
        $GLOBALS['_wp_stores']['is_admin'] = 1;
        $arrancado = false;

        Module_Registry::register('futuro', array(
            'core_min'  => '99.0.0',
            'bootstrap' => function () use (&$arrancado) {
                $arrancado = true;
            },
        ));

        $this->assertFalse($arrancado, 'un módulo incompatible no se arranca');
        $this->assertNotEmpty($GLOBALS['_wp_hooks']['admin_notices'] ?? array(), 'y se avisa en el panel');
    }

    /**
     * Una capacidad PRO que no está activa deja el módulo fuera.
     */
    public function test_una_capacidad_pro_ausente_deja_el_modulo_fuera(): void
    {
        $GLOBALS['_wp_stores']['is_admin'] = 1;
        $arrancado = false;

        Module_Registry::register('pro', array(
            'features'  => array('members'),
            'bootstrap' => function () use (&$arrancado) {
                $arrancado = true;
            },
        ));

        $this->assertFalse($arrancado, 'sin licencia PRO el módulo no corre');
        $this->assertFalse(License_Manager::has_pro('members'));
    }

    /**
     * Con la licencia PRO puesta, el módulo de pago sí arranca.
     */
    public function test_con_licencia_pro_el_modulo_arranca(): void
    {
        $GLOBALS['_wp_stores']['options'][License_Manager::OPTION_KEY] = array(
            'key'      => 'LIC-1',
            'status'   => 'active',
            'type'     => 'unlimited',
            'features' => array(),
            'expires'  => '',
            'email'    => 'a@example.test',
        );
        $arrancado = false;

        Module_Registry::register('pro', array(
            'core_min'  => '0.0.1',
            'features'  => array('members'),
            'bootstrap' => function () use (&$arrancado) {
                $arrancado = true;
            },
        ));

        $this->assertTrue($arrancado, 'con licencia ilimitada el módulo arranca');
    }

    /**
     * En el panel, una capacidad que la persona no tiene deja el módulo fuera.
     */
    public function test_en_el_panel_una_capacidad_ausente_deja_el_modulo_fuera(): void
    {
        $GLOBALS['_wp_stores']['is_admin'] = 1;
        $GLOBALS['_test_caps'] = array('convoca_manage_members' => false);
        $arrancado = false;

        Module_Registry::register('socios', array(
            'core_min'     => '0.0.1',
            'capabilities' => array('convoca_manage_members'),
            'bootstrap'    => function () use (&$arrancado) {
                $arrancado = true;
            },
        ));

        $this->assertFalse($arrancado, 'sin la capacidad no se arranca en el panel');
    }

    /**
     * El `init` recoge lo que llega por el filtro y no repite el trabajo.
     */
    public function test_el_init_recoge_los_modulos_del_filtro(): void
    {
        add_filter('convoca_register_module', function ($modulos) {
            $modulos['externo'] = array('name' => 'Externo', 'core_min' => '0.0.1');
            return $modulos;
        });

        Module_Registry::init();

        $this->assertTrue(Module_Registry::is_active('externo'), 'el módulo del filtro queda registrado');
        $this->assertSame('Externo', Module_Registry::get_modules()['externo']['name']);

        // Segunda pasada: no vuelve a recoger (booted).
        Module_Registry::init();
        $this->assertCount(1, Module_Registry::get_modules());
    }
}
