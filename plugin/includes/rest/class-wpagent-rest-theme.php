<?php
/**
 * Draft theme + sandboxed file REST.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Theme extends WPAgent_REST_Controller {

	public function register_routes(): void {
		$ns = WPAGENT_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/draft-theme',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_draft' ),
					'permission_callback' => WPAgent_Permissions::callback( 'get_draft_theme' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_draft' ),
					'permission_callback' => WPAgent_Permissions::callback( 'create_draft_theme' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/draft-theme/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete_draft' ),
				'permission_callback' => WPAgent_Permissions::callback( 'delete_draft_theme' ),
			)
		);

		register_rest_route(
			$ns,
			'/draft-theme/publish',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'publish_draft' ),
				'permission_callback' => WPAgent_Permissions::callback( 'publish_draft_theme' ),
			)
		);

		register_rest_route(
			$ns,
			'/draft-theme/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_theme_preview_url' ),
			)
		);

		register_rest_route(
			$ns,
			'/theme/files',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_files' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_theme_files' ),
			)
		);

		register_rest_route(
			$ns,
			'/theme/file',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'read_file' ),
					'permission_callback' => WPAgent_Permissions::callback( 'read_theme_file' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'write_file' ),
					'permission_callback' => WPAgent_Permissions::callback( 'write_theme_file' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/theme/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_files' ),
				'permission_callback' => WPAgent_Permissions::callback( 'search_theme_files' ),
			)
		);
	}

	public function get_draft( WP_REST_Request $request ) {
		return $this->execute( 'get_draft_theme', $request, static fn() => WPAgent_Draft_Theme::status() );
	}

	public function create_draft( WP_REST_Request $request ) {
		return $this->execute(
			'create_draft_theme',
			$request,
			static fn() => WPAgent_Draft_Theme::create(),
			array( 'object_type' => 'theme' )
		);
	}

	public function delete_draft( WP_REST_Request $request ) {
		return $this->execute(
			'delete_draft_theme',
			$request,
			static fn() => WPAgent_Draft_Theme::delete(),
			array( 'object_type' => 'theme' )
		);
	}

	public function publish_draft( WP_REST_Request $request ) {
		return $this->execute(
			'publish_draft_theme',
			$request,
			static fn() => WPAgent_Draft_Theme::publish(),
			array( 'object_type' => 'theme' )
		);
	}

	public function preview( WP_REST_Request $request ) {
		return $this->execute( 'get_theme_preview_url', $request, static fn() => WPAgent_Theme_Preview::preview() );
	}

	public function list_files( WP_REST_Request $request ) {
		return $this->execute(
			'list_theme_files',
			$request,
			static fn() => WPAgent_Theme_Files::list_files(
				$request->get_param( 'glob' ) ? (string) $request->get_param( 'glob' ) : null,
				$request->get_param( 'prefix' ) ? (string) $request->get_param( 'prefix' ) : null
			)
		);
	}

	public function read_file( WP_REST_Request $request ) {
		return $this->execute(
			'read_theme_file',
			$request,
			static fn() => WPAgent_Theme_Files::read( (string) $request->get_param( 'path' ) )
		);
	}

	public function write_file( WP_REST_Request $request ) {
		return $this->execute(
			'write_theme_file',
			$request,
			static fn() => WPAgent_Theme_Files::write(
				(string) $request->get_param( 'path' ),
				(string) $request->get_param( 'content' )
			),
			array( 'object_type' => 'theme_file' )
		);
	}

	public function search_files( WP_REST_Request $request ) {
		return $this->execute(
			'search_theme_files',
			$request,
			static function () use ( $request ) {
				$exclude = $request->get_param( 'exclude_glob' );
				return WPAgent_Theme_Files::search(
					(string) $request->get_param( 'query' ),
					$request->get_param( 'path' ) ? (string) $request->get_param( 'path' ) : null,
					null === $exclude ? null : (string) $exclude
				);
			}
		);
	}
}
