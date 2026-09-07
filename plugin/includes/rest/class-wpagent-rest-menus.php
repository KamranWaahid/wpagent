<?php
/**
 * Navigation menu REST.
 *
 * @package WPAgent
 */

class WPAgent_REST_Menus extends WPAgent_REST_Controller {

	public function register_routes(): void {
		$ns = WPAGENT_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/menus',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_menus' ),
					'permission_callback' => WPAgent_Permissions::callback( 'list_nav_menus' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'manage' ),
					'permission_callback' => WPAgent_Permissions::callback( 'manage_nav_menu' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_menu' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_nav_menu' ),
			)
		);

		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete_menu' ),
				'permission_callback' => WPAgent_Permissions::callback( 'delete_nav_menu' ),
			)
		);
	}

	public function list_menus( WP_REST_Request $request ) {
		return $this->execute( 'list_nav_menus', $request, static fn() => WPAgent_Menus::list_all() );
	}

	public function get_menu( WP_REST_Request $request ) {
		return $this->execute(
			'get_nav_menu',
			$request,
			static fn() => WPAgent_Menus::get_menu( (int) $request['id'] )
		);
	}

	public function manage( WP_REST_Request $request ) {
		return $this->execute(
			'manage_nav_menu',
			$request,
			static function () use ( $request ) {
				$body = $request->get_json_params();
				if ( ! is_array( $body ) ) {
					$body = $request->get_params();
				}
				return WPAgent_Menus::manage( $body );
			},
			array( 'object_type' => 'nav_menu' )
		);
	}

	public function delete_menu( WP_REST_Request $request ) {
		return $this->execute(
			'delete_nav_menu',
			$request,
			static fn() => WPAgent_Menus::delete_menu( (int) $request['id'] ),
			array(
				'object_type' => 'nav_menu',
				'object_id'   => (int) $request['id'],
			)
		);
	}
}
