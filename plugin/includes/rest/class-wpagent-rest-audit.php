<?php
/**
 * Audit log REST.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Audit extends WPAgent_REST_Controller {

	public function register_routes(): void {
		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/audit-log',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_log' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_audit_log' ),
			)
		);
	}

	public function get_log( WP_REST_Request $request ) {
		return $this->execute(
			'get_audit_log',
			$request,
			function () use ( $request ) {
				$result = WPAgent_Audit::query(
					array(
						'page'     => (int) $request->get_param( 'page' ),
						'per_page' => (int) $request->get_param( 'per_page' ) ?: 20,
						'command'  => sanitize_key( (string) $request->get_param( 'command' ) ),
						'result'   => sanitize_key( (string) $request->get_param( 'result' ) ),
						'user_id'  => (int) $request->get_param( 'user_id' ),
					)
				);

				foreach ( $result['items'] as &$row ) {
					if ( ! empty( $row['before_state'] ) ) {
						$decoded = json_decode( $row['before_state'], true );
						$row['before_state'] = null === $decoded ? $row['before_state'] : $decoded;
					}
					if ( ! empty( $row['after_state'] ) ) {
						$decoded = json_decode( $row['after_state'], true );
						$row['after_state'] = null === $decoded ? $row['after_state'] : $decoded;
					}
				}

				return $result;
			}
		);
	}
}
