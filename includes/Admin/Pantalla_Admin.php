<?php
/**
 * Clase base de las pantallas de administración de Convoca (issue convoca-core#16).
 *
 * Es la forma de usar el andamiaje compartido cuando la pantalla **no** hereda ya de otra clase. Su
 * única razón de existir es no repetir en cada pantalla el constructor que registra los ganchos:
 *
 *     class Admin_Actividades extends \Convoca\Core\Admin\Pantalla_Admin {
 *         protected function ganchos(): array {
 *             return array( 'admin_menu' => 'add_menu', ... );
 *         }
 *     }
 *
 * Las pantallas que heredan de `\WP_List_Table` no pueden heredar de aquí —PHP no tiene herencia
 * múltiple— y usan el trait `Andamiaje_Admin`, que es donde está la implementación. Una sola
 * implementación, dos formas de engancharla, y el porqué escrito en cada sitio.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla de administración: ganchos, menú, assets y guardas, todo del andamiaje compartido.
 */
abstract class Pantalla_Admin {
	use Andamiaje_Admin;
}
