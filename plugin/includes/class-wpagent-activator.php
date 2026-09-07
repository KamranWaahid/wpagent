<?php
/**
 * Plugin activation.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		self::create_tables();
		self::ensure_crypto_salt();

		if ( false === get_option( 'wpagent_disabled_commands', false ) ) {
			add_option( 'wpagent_disabled_commands', array(), '', false );
		}

		if ( false === get_option( 'wpagent_connection_stats', false ) ) {
			add_option(
				'wpagent_connection_stats',
				array(
					'activated_at' => gmdate( 'c' ),
					'write_count'  => 0,
					'denied_count' => 0,
				),
				'',
				false
			);
		}

		update_option( 'wpagent_db_version', WPAGENT_DB_VERSION );
	}

	/**
	 * Create custom tables via dbDelta.
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->prefix . 'wpagent_audit_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			request_id varchar(36) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_login varchar(60) NOT NULL DEFAULT '',
			command varchar(100) NOT NULL DEFAULT '',
			object_type varchar(50) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			capability varchar(100) NOT NULL DEFAULT '',
			ip varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			before_state longtext NULL,
			after_state longtext NULL,
			result varchar(20) NOT NULL DEFAULT 'success',
			error_message text NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY command (command),
			KEY user_id (user_id),
			KEY request_id (request_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Persist a per-site encryption salt (never reused as a key by itself).
	 */
	public static function ensure_crypto_salt(): void {
		$salt = get_option( 'wpagent_crypto_salt', '' );
		if ( is_string( $salt ) && strlen( $salt ) >= 32 ) {
			return;
		}

		$salt = bin2hex( random_bytes( 32 ) );
		add_option( 'wpagent_crypto_salt', $salt, '', false );
	}
}
