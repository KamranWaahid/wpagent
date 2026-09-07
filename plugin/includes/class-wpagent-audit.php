<?php
/**
 * Append-only audit log.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Audit {

	/**
	 * Table name including prefix.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpagent_audit_log';
	}

	/**
	 * Append a log row. Never updates existing rows.
	 *
	 * @param array<string, mixed> $entry Log fields.
	 * @return int Insert id, or 0 on failure.
	 */
	public static function log( array $entry ): int {
		global $wpdb;

		$user = wp_get_current_user();

		$row = array(
			'created_at'    => current_time( 'mysql', true ),
			'request_id'    => sanitize_text_field( $entry['request_id'] ?? wp_generate_uuid4() ),
			'user_id'       => (int) ( $entry['user_id'] ?? ( $user->ID ?? 0 ) ),
			'user_login'    => sanitize_user( $entry['user_login'] ?? ( $user->user_login ?? '' ) ),
			'command'       => sanitize_key( $entry['command'] ?? '' ),
			'object_type'   => sanitize_key( $entry['object_type'] ?? '' ),
			'object_id'     => (int) ( $entry['object_id'] ?? 0 ),
			'capability'    => sanitize_text_field( $entry['capability'] ?? '' ),
			'ip'            => sanitize_text_field( self::client_ip() ),
			'user_agent'    => substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
			'before_state'  => self::encode_state( $entry['before_state'] ?? null ),
			'after_state'   => self::encode_state( $entry['after_state'] ?? null ),
			'result'        => sanitize_key( $entry['result'] ?? 'success' ),
			'error_message' => isset( $entry['error_message'] ) ? sanitize_textarea_field( (string) $entry['error_message'] ) : null,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( self::table(), $row );

		$id = (int) $wpdb->insert_id;
		if ( $id && in_array( $row['result'], array( 'success', 'denied' ), true ) ) {
			self::bump_stat( 'denied' === $row['result'] ? 'denied_count' : 'write_count' );
		}

		return $id;
	}

	/**
	 * Query log rows (newest first).
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = self::table();

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['command'] ) ) {
			$where[]  = 'command = %s';
			$values[] = sanitize_key( (string) $args['command'] );
		}

		if ( ! empty( $args['result'] ) ) {
			$where[]  = 'result = %s';
			$values[] = sanitize_key( (string) $args['result'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$values[] = (int) $args['user_id'];
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";

		if ( $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );
			$values[] = $per_page;
			$values[] = $offset;
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $values ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( $count_sql );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $per_page, $offset ), ARRAY_A );
		}

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'page'  => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Encode before/after snapshots.
	 *
	 * @param mixed $state State payload.
	 */
	private static function encode_state( $state ): ?string {
		if ( null === $state || '' === $state ) {
			return null;
		}

		$json = wp_json_encode( $state );
		return is_string( $json ) ? $json : null;
	}

	/**
	 * Best-effort client IP (audit only, not used for access control).
	 */
	private static function client_ip(): string {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			return substr( $ip, 0, 45 );
		}
		return '';
	}

	/**
	 * Increment a stats counter.
	 */
	private static function bump_stat( string $key ): void {
		$stats = get_option( 'wpagent_connection_stats', array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		$stats[ $key ] = (int) ( $stats[ $key ] ?? 0 ) + 1;
		update_option( 'wpagent_connection_stats', $stats, false );
	}
}
