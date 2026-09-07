<?php
/**
 * REST route registrar. Every route has a permission_callback.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST {

	/**
	 * Register versioned routes.
	 */
	public function register_routes(): void {
		$content     = new WPAgent_REST_Content();
		$media       = new WPAgent_REST_Media();
		$site        = new WPAgent_REST_Site();
		$users       = new WPAgent_REST_Users();
		$audit       = new WPAgent_REST_Audit();
		$diagnostics = new WPAgent_REST_Diagnostics();
		$theme       = new WPAgent_REST_Theme();
		$cli         = new WPAgent_REST_Cli();
		$abilities   = new WPAgent_REST_Abilities();
		$builders    = new WPAgent_REST_Builders();
		$menus       = new WPAgent_REST_Menus();

		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
			)
		);

		$content->register_routes();
		$media->register_routes();
		$site->register_routes();
		$users->register_routes();
		$audit->register_routes();
		$diagnostics->register_routes();
		$theme->register_routes();
		$cli->register_routes();
		$abilities->register_routes();
		$builders->register_routes();
		$menus->register_routes();
	}

	/**
	 * Lightweight connection probe.
	 *
	 * @return WP_REST_Response
	 */
	public function status(): WP_REST_Response {
		$user = wp_get_current_user();

		return rest_ensure_response(
			array(
				'ok'        => true,
				'plugin'    => 'wpagent',
				'version'   => WPAGENT_VERSION,
				'site_url'  => home_url( '/' ),
				'namespace' => WPAGENT_REST_NAMESPACE,
				'user'      => array(
					'id'    => (int) $user->ID,
					'login' => $user->user_login,
					'roles' => array_values( $user->roles ),
				),
			)
		);
	}
}
