<?php
/**
 * Common Block renderers for Biodevas.
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Blocks_Common {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'add_custom_categories' ), 1, 2 );
	}

	public static function add_custom_categories( $categories, $post ) {
		$new_categories = array(
			array(
				'slug'  => 'convoca-enroll',
				'title' => 'Biodevas: Inscripciones',
				'icon'  => 'clipboard',
			),
			array(
				'slug'  => 'convoca-members',
				'title' => 'Biodevas: Socios',
				'icon'  => 'admin-users',
			),
			array(
				'slug'  => 'biodevas-turnos',
				'title' => 'Biodevas: Turnos',
				'icon'  => 'calendar-alt',
			),
			array(
				'slug'  => 'convoca-gateway',
				'title' => 'Biodevas: Pagos',
				'icon'  => 'money-alt',
			),
			array(
				'slug'  => 'convoca-core',
				'title' => 'Biodevas: General',
				'icon'  => 'admin-settings',
			),
		);

		// Filter out duplicates by slug
		$existing_slugs = array_column( $categories, 'slug' );
		$filtered       = array_filter(
			$new_categories,
			function ( $cat ) use ( $existing_slugs ) {
				return ! in_array( $cat['slug'], $existing_slugs );
			}
		);

		return array_merge( array_values( $filtered ), $categories );
	}

	public static function register_blocks() {
		register_block_type(
			'biodevas-common/post-meta-field',
			array(
				'apiVersion'      => 3,
				'title'           => __( 'Metadato de Post', 'convoca-core' ),
				'category'        => 'convoca-core',
				'icon'            => 'database',
				'attributes'      => array(
					'metaField' => array(
						'type'    => 'string',
						'default' => '',
					),
					'prefix'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'suffix'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'type'      => array(
						'type'    => 'string',
						'default' => 'text', // text, date, price, number
					),
				),
				'render_callback' => array( __CLASS__, 'render_post_meta_field' ),
			)
		);
	}

	/**
	 * Render a post meta field as a formatted block attribute.
	 * Properly escapes output to prevent XSS.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content Inner content.
	 * @param WP_Block $block Block instance.
	 * @return string Rendered HTML.
	 */
	public static function render_post_meta_field( $attributes, $content, $block ) {
		if ( empty( $attributes['metaField'] ) ) {
			return '';
		}

		$post_id = $block->context['postId'] ?? get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$value = get_post_meta( $post_id, $attributes['metaField'], true );

		if ( $value === '' || $value === null ) {
			return '';
		}

		$type            = $attributes['type'] ?? 'text';
		$formatted_value = $value;

		switch ( $type ) {
			case 'date':
				if ( ! empty( $value ) ) {
					$date = strtotime( $value );
					if ( $date ) {
						$formatted_value = date_i18n( get_option( 'date_format' ), $date );
					}
				}
				break;
			case 'price':
				$formatted_value = number_format( (float) $value, 2, ',', '.' ) . ' €';
				break;
			case 'number':
				$formatted_value = number_format( (float) $value, 0, ',', '.' );
				break;
			case 'text':
			case 'textarea':
			default:
				$formatted_value = make_clickable( wp_kses_post( $value ) );
				break;
		}

		$prefix = esc_html( $attributes['prefix'] ?? '' );
		$suffix = esc_html( $attributes['suffix'] ?? '' );

		return $prefix . $formatted_value . $suffix;
	}
}
