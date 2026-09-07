<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPAgent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$delete_data = get_option( 'wpagent_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

$options = array(
	'wpagent_db_version',
	'wpagent_crypto_salt',
	'wpagent_disabled_commands',
	'wpagent_allow_insecure_app_passwords',
	'wpagent_delete_data_on_uninstall',
	'wpagent_encrypted_secrets',
	'wpagent_connection_stats',
	'wpagent_remote_mcp_url',
	'wpagent_option_allowlist',
	'wpagent_draft_theme',
	'wpagent_preview_token',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

$table = $wpdb->prefix . 'wpagent_audit_log';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
