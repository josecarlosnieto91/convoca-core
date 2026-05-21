<?php
/**
 * Centralized log viewer for the convoca_logs table.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Admin_Logs_List extends \WP_List_Table {

	public $approx_total = false;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
				'screen'   => 'bdv-logs',
			)
		);
	}

	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox">',
			'created_at' => __( 'Fecha', 'convoca-core' ),
			'level'      => __( 'Nivel', 'convoca-core' ),
			'context'    => __( 'Plugin', 'convoca-core' ),
			'message'    => __( 'Mensaje', 'convoca-core' ),
			'user_id'    => __( 'Usuario', 'convoca-core' ),
			'object_id'  => __( 'ID Objeto', 'convoca-core' ),
		);
	}

	public function get_sortable_columns(): array {
		return array(
			'created_at' => array( 'created_at', true ),
			'level'      => array( 'level', false ),
			'context'    => array( 'context', false ),
		);
	}

	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%d">', $item->id );
	}

	protected function column_created_at( $item ): string {
		return '<strong>' . esc_html( wp_date( 'd/m/Y H:i:s', strtotime( $item->created_at ) ) ) . '</strong>';
	}

	protected function column_level( $item ): string {
		return Utils::render_log_level_badge( $item->level );
	}

	protected function column_context( $item ): string {
		return '<code style="background:#f1f5f9;color:#475569;padding:2px 6px;border-radius:4px;">' . esc_html( $item->context ) . '</code>';
	}

	protected function column_message( $item ): string {
		$msg = esc_html( mb_substr( $item->message, 0, 200 ) );
		if ( mb_strlen( $item->message ) > 200 ) {
			$msg .= ' <span class="view-log-detail" data-log-message="' . esc_attr( $item->message ) . '">[' . __( 'ver más', 'convoca-core' ) . ']</span>';
		}
		return $msg;
	}

	protected function column_user_id( $item ): string {
		if ( ! $item->user_id ) {
			return '<span style="color:#94a3b8;">—</span>';
		}
		$u = get_userdata( $item->user_id );
		return $u ? esc_html( $u->display_name ) : '#' . $item->user_id;
	}

	protected function column_object_id( $item ): string {
		return $item->object_id ? '<span style="background:#e0f2fe;color:#0369a1;padding:2px 5px;border-radius:4px;font-size:11px;">#' . (int) $item->object_id . '</span>' : '<span style="color:#94a3b8;">—</span>';
	}

	public function get_bulk_actions(): array {
		return array(
			'delete'   => __( 'Eliminar seleccionados', 'convoca-core' ),
			'purge_30' => __( 'Purgar > 30 días', 'convoca-core' ),
		);
	}

	public function prepare_items(): void {
		global $wpdb;
		$table    = $wpdb->prefix . 'convoca_logs';
		$per_page = 40;
		$page     = $this->get_pagenum();

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->process_bulk_action();

		$where = array( '1=1' );
		$args  = array();

		// Filters.
		$filter_context   = sanitize_text_field( $_GET['filter_context'] ?? '' );
		$filter_level     = sanitize_text_field( $_GET['filter_level'] ?? '' );
		$filter_date_from = sanitize_text_field( $_GET['filter_date_from'] ?? '' );
		$filter_date_to   = sanitize_text_field( $_GET['filter_date_to'] ?? '' );
		$search           = sanitize_text_field( $_GET['s'] ?? '' );

		if ( $filter_context ) {
			$where[] = 'context = %s';
			$args[]  = $filter_context; }
		if ( $filter_level ) {
			$where[] = 'level = %s';
			$args[]  = $filter_level; }
		if ( $filter_date_from ) {
			$where[] = 'created_at >= %s';
			$args[]  = $filter_date_from . ' 00:00:00'; }
		if ( $filter_date_to ) {
			$where[] = 'created_at <= %s';
			$args[]  = $filter_date_to . ' 23:59:59'; }
		if ( $search ) {
			$where[] = '(message LIKE %s OR context LIKE %s)';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $page - 1 ) * $per_page;

		// Optimization: approximate count from information_schema when no filters.
		$total = 0;
		if ( empty( $args ) ) {
			$approx             = $wpdb->get_var( "SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'" );
			$total              = max( (int) $approx, 0 );
			$this->approx_total = true;
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table WHERE $where_clause", $args ) );
		}

		$orderby = ! empty( $_GET['orderby'] ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'created_at';
		$order   = ! empty( $_GET['order'] ) ? sanitize_sql_orderby( $_GET['order'] ) : 'DESC';

		$sql         = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$full_args   = array_merge( $args, array( $per_page, $offset ) );
		$results     = $wpdb->get_results( $wpdb->prepare( $sql, $full_args ) );
		$this->items = is_array( $results ) ? $results : array();

		// Ajustar paginación cuando approx_total da un valor inflado.
		// (information_schema.TABLE_ROWS es aproximado en InnoDB)
		$actual_count = count( $this->items );
		if ( $this->approx_total && $actual_count < $per_page && $page === 1 ) {
			$total = $actual_count;
		} elseif ( $this->approx_total && $actual_count === 0 ) {
			$total = 0;
		}

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	public function process_bulk_action(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'convoca_logs';

		if ( $this->current_action() === 'delete' && isset( $_POST['log_ids'] ) ) {
			check_admin_referer( 'bulk-logs' );
			$ids = array_map( 'absint', (array) $_POST['log_ids'] );
			if ( ! empty( $ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id IN ($placeholders)", $ids ) );
			}
		}

		if ( $this->current_action() === 'purge_30' ) {
			check_admin_referer( 'bulk-logs' );
			$date    = wp_date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s", $date ) );
			if ( $deleted ) {
				\Convoca\Core\Logger::info( "Purga manual: $deleted registros antiguos eliminados.", 'System' );
			}
		}
	}

	/**
	 * Override display with safe guard para contextos sin admin screen (WP-CLI, REST).
	 */
	public function display(): void {
		if ( $this->screen === null ) {
			echo '<div class="notice notice-warning"><p>' . __( 'La vista de logs requiere el panel de administración.', 'convoca-core' ) . '</p></div>';
			return;
		}
		parent::display();
	}

	public function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}

		$filter_context   = $_GET['filter_context'] ?? '';
		$filter_level     = $_GET['filter_level'] ?? '';
		$filter_date_from = $_GET['filter_date_from'] ?? '';
		$filter_date_to   = $_GET['filter_date_to'] ?? '';

		global $wpdb;
		$table    = $wpdb->prefix . 'convoca_logs';
		$contexts = $wpdb->get_col( "SELECT DISTINCT context FROM $table ORDER BY context" );
		$levels   = array( 'info', 'warning', 'error', 'success' );
		wp_nonce_field( 'bulk-logs' );
		?>
		<div class="alignleft actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
			<select name="filter_context">
				<option value=""><?php _e( 'Todos los plugins', 'convoca-core' ); ?></option>
				<?php foreach ( $contexts as $c ) : ?>
					<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $filter_context, $c ); ?>><?php echo esc_html( $c ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="filter_level">
				<option value=""><?php _e( 'Todos los niveles', 'convoca-core' ); ?></option>
				<?php foreach ( $levels as $l ) : ?>
					<option value="<?php echo esc_attr( $l ); ?>" <?php selected( $filter_level, $l ); ?>><?php echo esc_html( ucfirst( $l ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="date" name="filter_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>">
			<input type="date" name="filter_date_to" value="<?php echo esc_attr( $filter_date_to ); ?>">
			<?php submit_button( __( 'Filtrar', 'convoca-core' ), 'convoca-btn convoca-btn-outline', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
