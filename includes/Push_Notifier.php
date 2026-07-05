<?php

/**
 * Convoca Core
 *
 * @package    Convoca\Core
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Push notifications via ntfy.sh.
 *
 * Sends push notifications to mobile devices using the
 * ntfy.sh service (free, no API key required).
 *
 * @package Convoca\Core
 */

namespace Convoca\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Push_Notifier {

	/**
	 * Option key for ntfy configuration.
	 */
	const CONFIG_OPTION = 'convoca_ntfy_config';

	/**
	 * Send a push notification.
	 *
	 * @param string $topic   The ntfy.sh topic/channel name.
	 * @param string $title   Notification title.
	 * @param string $message Notification message body.
	 * @param string $priority Priority: 'default', 'low', 'high', 'urgent', 'min', 'max '
	 * @param array  $tags    Optional tags/emojis (e.g., ['warning', 'bell']).
	 * @return bool True on success.
	 */
	public static function send( string $topic, string $title, string $message, string $priority = 'default', array $tags = array() ): bool {
		$config = get_option( self::CONFIG_OPTION, array() );
		if ( empty( $config['enabled'] ) ) {
			return false;
		}

		$server = untrailingslashit( $config['server'] ?? 'https://ntfy.sh' );
		$topic  = ! empty( $topic ) ? $topic : ( $config['topic'] ?? 'convoca_alerts' );
		$url    = $server . '/' . $topic;

		// Validate priority.
		$valid_priorities = array( 'default', 'low', 'high', 'urgent', 'min', 'max' );
		if ( ! in_array( $priority, $valid_priorities, true ) ) {
			$priority = 'default';
		}

		$payload = array(
			'topic'    => $topic,
			'title'    => $title,
			'message'  => $message,
			'priority' => $priority,
		);

		if ( ! empty( $tags ) ) {
			$payload['tags'] = $tags;
		}

		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 10,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::warning(
				/* translators: %s: error message from the push notification service */
				sprintf( __( 'Push notification error: %s', 'convoca-core' ), $response->get_error_message() ),
				'Common/Push'
			);
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			Logger::debug(
				/* translators: %s: notification title */
				sprintf( __( 'Push notification sent: %s', 'convoca-core' ), $title ),
				'Common/Push'
			);
			return true;
		}

		Logger::warning(
			/* translators: %1$d: HTTP status code, %2$s: response body */
			sprintf( __( 'Push notification HTTP %1$d: %2$s', 'convoca-core' ), $code, wp_remote_retrieve_body( $response ) ),
			'Common/Push'
		);
		return false;
	}

	/**
	 * Convenience: send to the default configured topic.
	 *
	 * @param string $title    Notification title.
	 * @param string $message  Notification body.
	 * @param string $priority Priority level.
	 * @param array  $tags     Optional tags.
	 * @return bool True on success.
	 */
	public static function notify_admins( string $title, string $message, string $priority = 'default', array $tags = array() ): bool {
		$config = get_option( self::CONFIG_OPTION, array() );
		$topic  = $config['topic'] ?? 'convoca_alerts';

		if ( empty( $topic ) ) {
			return false;
		}

		return self::send( $topic, '[Admin] ' . $title, $message, $priority, $tags );
	}

	/**
	 * Get the default config values.
	 */
	public static function get_defaults(): array {
		return array(
			'enabled' => false,
			'topic'   => 'convoca_alerts',
			'server'  => 'https://ntfy.sh',
		);
	}

	/**
	 * Get current config.
	 */
	public static function get_config(): array {
		$config = get_option( self::CONFIG_OPTION, array() );
		return array_merge( self::get_defaults(), $config );
	}

	/**
	 * Save config.
	 */
	public static function save_config( array $config ): void {
		$config            = wp_parse_args( $config, self::get_defaults() );
		$config['enabled'] = ! empty( $config['enabled'] );
		$config['topic']   = sanitize_text_field( $config['topic'] );
		$config['server']  = esc_url_raw( $config['server'] );
		update_option( self::CONFIG_OPTION, $config );
	}
}
