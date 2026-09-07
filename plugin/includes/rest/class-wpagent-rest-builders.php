<?php
/**
 * Page-builder REST.
 *
 * @package WPAgent
 */

class WPAgent_REST_Builders extends WPAgent_REST_Controller {

	public function register_routes(): void {
		$ns = WPAGENT_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/builders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'inventory' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_page_builders' ),
			)
		);

		register_rest_route(
			$ns,
			'/builders/(?P<builder>[a-z]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'catalog' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_builder_catalog' ),
			)
		);

		register_rest_route(
			$ns,
			'/builders/(?P<builder>[a-z]+)/pages/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_page' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_builder_page' ),
			)
		);

		register_rest_route(
			$ns,
			'/builders/(?P<builder>[a-z]+)/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save' ),
				'permission_callback' => WPAgent_Permissions::callback( 'save_builder_page' ),
			)
		);
	}

	public function inventory( WP_REST_Request $request ) {
		return $this->execute( 'list_page_builders', $request, static fn() => WPAgent_Builders::inventory() );
	}

	public function catalog( WP_REST_Request $request ) {
		return $this->execute(
			'list_builder_catalog',
			$request,
			static fn() => WPAgent_Builders::catalog(
				(string) $request['builder'],
				$request->get_param( 'item' ) ? (string) $request->get_param( 'item' ) : null
			)
		);
	}

	public function get_page( WP_REST_Request $request ) {
		return $this->execute(
			'get_builder_page',
			$request,
			static fn() => WPAgent_Builders::get_page( (string) $request['builder'], (int) $request['id'] )
		);
	}

	public function save( WP_REST_Request $request ) {
		return $this->execute(
			'save_builder_page',
			$request,
			static function () use ( $request ) {
				$body = $request->get_json_params();
				if ( ! is_array( $body ) ) {
					$body = $request->get_params();
				}
				$body['explicit_publish'] = rest_sanitize_boolean( $request->get_param( 'explicit_publish' ) );
				return WPAgent_Builders::save( (string) $request['builder'], $body );
			},
			array( 'object_type' => 'builder_page' )
		);
	}
}
