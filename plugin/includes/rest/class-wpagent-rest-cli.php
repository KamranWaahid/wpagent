<?php
/**
 * WP-CLI-style dispatch over REST.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Cli extends WPAgent_REST_Controller {

	public function register_routes(): void {
		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/cli/commands',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'commands' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_wp_cli_commands' ),
			)
		);

		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/cli/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run' ),
				'permission_callback' => WPAgent_Permissions::callback( 'run_wp_cli' ),
			)
		);
	}

	public function commands( WP_REST_Request $request ) {
		return $this->execute(
			'list_wp_cli_commands',
			$request,
			static function () {
				$items = array();
				foreach ( WPAgent_Cli::catalog() as $name => $meta ) {
					$items[] = array(
						'command'     => $name,
						'tier'        => $meta['tier'],
						'capability'  => $meta['cap'],
						'description' => $meta['description'],
					);
				}
				return array( 'items' => $items );
			}
		);
	}

	public function run( WP_REST_Request $request ) {
		return $this->execute(
			'run_wp_cli',
			$request,
			static function () use ( $request ) {
				return WPAgent_Cli::run(
					(string) $request->get_param( 'command' ),
					rest_sanitize_boolean( $request->get_param( 'confirm' ) ),
					(string) $request->get_param( 'approval_id' )
				);
			},
			array( 'object_type' => 'cli' )
		);
	}
}
