<?php
/**
 * wp-admin settings, connection flow, audit viewer, allowlist.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Admin {

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_menu_page(
			__( 'WPAgent', 'wpagent' ),
			__( 'WPAgent', 'wpagent' ),
			'manage_options',
			'wpagent',
			array( $this, 'render_settings' ),
			'dashicons-rest-api',
			80
		);

		add_submenu_page(
			'wpagent',
			__( 'Connection', 'wpagent' ),
			__( 'Connection', 'wpagent' ),
			'manage_options',
			'wpagent',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'wpagent',
			__( 'Audit log', 'wpagent' ),
			__( 'Audit log', 'wpagent' ),
			'manage_options',
			'wpagent-audit',
			array( $this, 'render_audit' )
		);
	}

	public function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'wpagent' ) ) {
			return;
		}
		wp_enqueue_style(
			'wpagent-admin',
			WPAGENT_URL . 'assets/css/admin.css',
			array(),
			WPAGENT_VERSION
		);
		wp_enqueue_script(
			'wpagent-admin',
			WPAGENT_URL . 'assets/js/admin.js',
			array(),
			WPAGENT_VERSION,
			true
		);
		wp_localize_script(
			'wpagent-admin',
			'wpagentAdmin',
			array(
				'copied'      => __( 'Copied', 'wpagent' ),
				'generating'  => __( 'Generating…', 'wpagent' ),
				'copyFailed'  => __( 'Copy failed', 'wpagent' ),
			)
		);
	}

	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_POST['wpagent_action'] ) ? sanitize_key( wp_unslash( $_POST['wpagent_action'] ) ) : '';
		if ( '' === $action ) {
			return;
		}

		check_admin_referer( 'wpagent_admin' );

		switch ( $action ) {
			case 'save_settings':
				update_option( 'wpagent_allow_insecure_app_passwords', ! empty( $_POST['allow_insecure'] ) );
				update_option( 'wpagent_delete_data_on_uninstall', ! empty( $_POST['delete_data'] ) );
				if ( isset( $_POST['remote_mcp_url'] ) ) {
					update_option( 'wpagent_remote_mcp_url', esc_url_raw( wp_unslash( $_POST['remote_mcp_url'] ) ), false );
				}
				$extras = array();
				if ( ! empty( $_POST['option_keys'] ) && is_array( $_POST['option_keys'] ) ) {
					$keys   = array_map( 'sanitize_key', wp_unslash( $_POST['option_keys'] ) );
					$writes = isset( $_POST['option_writable'] ) ? (array) wp_unslash( $_POST['option_writable'] ) : array();
					$descs  = isset( $_POST['option_descriptions'] ) ? (array) wp_unslash( $_POST['option_descriptions'] ) : array();
					foreach ( $keys as $i => $key ) {
						if ( '' === $key ) {
							continue;
						}
						$extras[] = array(
							'key'         => $key,
							'writable'    => ! empty( $writes[ $i ] ),
							'description' => sanitize_text_field( (string) ( $descs[ $i ] ?? '' ) ),
						);
					}
				}
				if ( ! empty( $_POST['new_option_key'] ) ) {
					$extras[] = array(
						'key'         => sanitize_key( wp_unslash( $_POST['new_option_key'] ) ),
						'writable'    => ! empty( $_POST['new_option_writable'] ),
						'description' => sanitize_text_field( wp_unslash( $_POST['new_option_description'] ?? '' ) ),
					);
				}
				update_option( 'wpagent_option_allowlist', WPAgent_Options_Allowlist::normalize_extras( $extras ), false );
				$disabled = isset( $_POST['disabled_commands'] ) && is_array( $_POST['disabled_commands'] )
					? WPAgent_Allowlist::normalize_disabled( wp_unslash( $_POST['disabled_commands'] ) )
					: array();
				update_option( 'wpagent_disabled_commands', $disabled, false );
				add_settings_error( 'wpagent', 'saved', __( 'Settings saved.', 'wpagent' ), 'success' );
				break;

			case 'create_app_password':
				$this->create_app_password();
				break;

			case 'revoke_app_passwords':
				$this->revoke_app_passwords();
				break;

			case 'link_remote_session':
				$this->link_remote_session();
				break;
		}
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wpagent' ) );
		}

		$available   = function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available();
		$stats       = get_option( 'wpagent_connection_stats', array() );
		$disabled    = WPAgent_Allowlist::normalize_disabled( get_option( 'wpagent_disabled_commands', array() ) );
		$insecure    = (bool) get_option( 'wpagent_allow_insecure_app_passwords', false );
		$delete_data = (bool) get_option( 'wpagent_delete_data_on_uninstall', false );
		$remote_url  = (string) get_option( 'wpagent_remote_mcp_url', '' );
		$option_extras = WPAgent_Options_Allowlist::normalize_extras( get_option( 'wpagent_option_allowlist', array() ) );
		$new_secret  = get_transient( 'wpagent_new_app_password' );
		$config      = get_transient( 'wpagent_new_mcp_config' );
		$passwords   = array();
		$user_id     = get_current_user_id();

		if ( class_exists( 'WP_Application_Passwords' ) ) {
			$passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );
		}

		include WPAGENT_DIR . 'includes/admin/views/settings.php';
	}

	public function render_audit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wpagent' ) );
		}

		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = WPAgent_Audit::query(
			array(
				'page'     => $page,
				'per_page' => 25,
			)
		);

		include WPAGENT_DIR . 'includes/admin/views/audit-log.php';
	}

	private function create_app_password(): void {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			add_settings_error( 'wpagent', 'no_app_pw', __( 'Application Passwords are not available on this WordPress version.', 'wpagent' ), 'error' );
			return;
		}

		if ( ! wp_is_application_passwords_available() ) {
			add_settings_error(
				'wpagent',
				'https',
				__( 'Application Passwords require HTTPS, or enable “Allow over HTTP” for local development.', 'wpagent' ),
				'error'
			);
			return;
		}

		$user_id = get_current_user_id();
		$name    = 'WPAgent MCP ' . gmdate( 'Y-m-d H:i' );
		$created = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => $name ) );

		if ( is_wp_error( $created ) ) {
			add_settings_error( 'wpagent', 'create_fail', $created->get_error_message(), 'error' );
			return;
		}

		$password = $created[0];
		$user     = wp_get_current_user();
		$site_id  = sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'site' );

		$mcp = array(
			'mcpServers' => array(
				'wpagent' => array(
					'command' => 'node',
					'args'    => array( '/ABSOLUTE/PATH/TO/WPAgent/server/dist/index.js' ),
					'env'     => array(
						'WPAGENT_SITES' => wp_json_encode(
							array(
								$site_id => array(
									'url'      => home_url( '/' ),
									'username' => $user->user_login,
									'password' => $password,
								),
							)
						),
					),
				),
			),
		);

		set_transient( 'wpagent_new_app_password', $password, 60 );
		set_transient( 'wpagent_new_mcp_config', wp_json_encode( $mcp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), 60 );

		add_settings_error(
			'wpagent',
			'created',
			__( 'Application password created. Copy it now — it will not be shown again.', 'wpagent' ),
			'success'
		);
	}

	/**
	 * Create an Application Password and POST it to the hosted MCP session via pairing code.
	 */
	private function link_remote_session(): void {
		$mcp_url = isset( $_POST['remote_mcp_url'] ) ? esc_url_raw( wp_unslash( $_POST['remote_mcp_url'] ) ) : '';
		$code    = isset( $_POST['pairing_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['pairing_code'] ) ) ) : '';

		if ( ! $mcp_url || ! $code ) {
			add_settings_error( 'wpagent', 'pairing_missing', __( 'MCP server URL and pairing code are required.', 'wpagent' ), 'error' );
			return;
		}

		update_option( 'wpagent_remote_mcp_url', $mcp_url, false );

		if ( ! class_exists( 'WP_Application_Passwords' ) || ! wp_is_application_passwords_available() ) {
			add_settings_error( 'wpagent', 'https', __( 'Application Passwords are not available. Enable HTTPS or local HTTP access first.', 'wpagent' ), 'error' );
			return;
		}

		$user    = wp_get_current_user();
		$created = WP_Application_Passwords::create_new_application_password(
			(int) $user->ID,
			array( 'name' => 'WPAgent MCP remote ' . gmdate( 'Y-m-d H:i' ) )
		);
		if ( is_wp_error( $created ) ) {
			add_settings_error( 'wpagent', 'create_fail', $created->get_error_message(), 'error' );
			return;
		}

		$password = $created[0];
		$uuid     = $created[1]['uuid'] ?? '';
		$result   = WPAgent_Remote::link_by_pairing( $mcp_url, $code, $user->user_login, $password );

		if ( is_wp_error( $result ) ) {
			if ( $uuid ) {
				WP_Application_Passwords::delete_application_password( (int) $user->ID, $uuid );
			}
			add_settings_error( 'wpagent', 'link_fail', $result->get_error_message(), 'error' );
			return;
		}

		add_settings_error(
			'wpagent',
			'linked',
			__( 'This site is linked to your WPAgent session. The Application Password was sent to the MCP server and is not stored in this plugin.', 'wpagent' ),
			'success'
		);
	}

	private function revoke_app_passwords(): void {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return;
		}
		$user_id = get_current_user_id();
		$all     = WP_Application_Passwords::get_user_application_passwords( $user_id );
		foreach ( $all as $item ) {
			if ( ! empty( $item['name'] ) && str_starts_with( (string) $item['name'], 'WPAgent MCP' ) ) {
				WP_Application_Passwords::delete_application_password( $user_id, $item['uuid'] );
			}
		}
		add_settings_error( 'wpagent', 'revoked', __( 'WPAgent application passwords revoked for your user.', 'wpagent' ), 'success' );
	}

	/**
	 * Core authorize-application.php URL for remote clients (Phase 3).
	 */
	public static function authorization_url( string $success_url = '', string $reject_url = '' ): string {
		$base = admin_url( 'authorize-application.php' );
		$args = array(
			'app_name' => 'WPAgent',
			'app_id'   => '00000000-0000-4000-8000-000000000001',
		);
		if ( $success_url ) {
			$args['success_url'] = $success_url;
		}
		if ( $reject_url ) {
			$args['reject_url'] = $reject_url;
		}
		return add_query_arg( $args, $base );
	}
}
