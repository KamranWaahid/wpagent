<?php
/**
 * Abilities API REST (WordPress 6.9+).
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Abilities extends WPAgent_REST_Controller {

	public function register_routes(): void {
		$ns = WPAGENT_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/abilities',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'discover' ),
				'permission_callback' => WPAgent_Permissions::callback( 'discover_abilities' ),
			)
		);

		register_rest_route(
			$ns,
			'/abilities/(?P<ns>[a-z0-9_.-]+)/(?P<slug>[a-z0-9_.-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'info' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_ability_info' ),
			)
		);

		register_rest_route(
			$ns,
			'/abilities/(?P<ns>[a-z0-9_.-]+)/(?P<slug>[a-z0-9_.-]+)/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run' ),
				'permission_callback' => WPAgent_Permissions::callback( 'run_ability' ),
			)
		);
	}

	public function discover( WP_REST_Request $request ) {
		return $this->execute( 'discover_abilities', $request, static fn() => WPAgent_Abilities::list_all() );
	}

	public function info( WP_REST_Request $request ) {
		return $this->execute(
			'get_ability_info',
			$request,
			static fn() => WPAgent_Abilities::info( self::ability_name( $request ) )
		);
	}

	public function run( WP_REST_Request $request ) {
		return $this->execute(
			'run_ability',
			$request,
			static function () use ( $request ) {
				return WPAgent_Abilities::execute(
					self::ability_name( $request ),
					$request->get_param( 'input' ),
					rest_sanitize_boolean( $request->get_param( 'confirm' ) )
				);
			},
			array( 'object_type' => 'ability' )
		);
	}

	private static function ability_name( WP_REST_Request $request ): string {
		return (string) $request['ns'] . '/' . (string) $request['slug'];
	}
}
