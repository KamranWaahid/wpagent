<?php
/**
 * Users and current-user capability discovery.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Users extends WPAgent_REST_Controller {

	public function register_routes(): void {
		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_users' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_users' ),
			)
		);

		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'me' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_current_user_capabilities' ),
			)
		);

		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/me/capabilities',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'capabilities' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_current_user_capabilities' ),
			)
		);
	}

	public function list_users( WP_REST_Request $request ) {
		return $this->execute(
			'list_users',
			$request,
			function () use ( $request ) {
				$paging = $this->pagination( $request );
				$users  = get_users(
					array(
						'number' => $paging['per_page'],
						'paged'  => $paging['page'],
						'search' => sanitize_text_field( (string) $request->get_param( 'search' ) ),
						'fields' => 'all',
					)
				);

				return array(
					'page'     => $paging['page'],
					'per_page' => $paging['per_page'],
					'items'    => array_map(
						static function ( WP_User $user ) {
							return array(
								'id'    => (int) $user->ID,
								'login' => $user->user_login,
								'name'  => $user->display_name,
								'email' => $user->user_email,
								'roles' => array_values( $user->roles ),
							);
						},
						$users
					),
				);
			}
		);
	}

	public function me( WP_REST_Request $request ) {
		return $this->capabilities( $request );
	}

	public function capabilities( WP_REST_Request $request ) {
		return $this->execute(
			'get_current_user_capabilities',
			$request,
			function () {
				$user      = wp_get_current_user();
				$disabled  = WPAgent_Allowlist::normalize_disabled( get_option( 'wpagent_disabled_commands', array() ) );
				$commands  = array();

				foreach ( WPAgent_Allowlist::definitions() as $name => $def ) {
					$allowed = WPAgent_Allowlist::is_permitted( $name, $disabled ) && current_user_can( $def['capability'] );
					$commands[ $name ] = array(
						'allowed'     => $allowed,
						'capability'  => $def['capability'],
						'write'       => ! empty( $def['write'] ),
						'destructive' => ! empty( $def['destructive'] ),
						'disabled'    => in_array( $name, $disabled, true ),
						'description' => $def['description'] ?? '',
					);
				}

				$caps = array();
				foreach ( array_unique( array_column( WPAgent_Allowlist::definitions(), 'capability' ) ) as $cap ) {
					$caps[ $cap ] = current_user_can( $cap );
				}

				return array(
					'user'     => array(
						'id'    => (int) $user->ID,
						'login' => $user->user_login,
						'name'  => $user->display_name,
						'email' => $user->user_email,
						'roles' => array_values( $user->roles ),
					),
					'caps'     => $caps,
					'commands' => $commands,
					'note'     => 'Commands with allowed=false will fail server-side. Explain those limits to the user instead of retrying.',
				);
			}
		);
	}
}
