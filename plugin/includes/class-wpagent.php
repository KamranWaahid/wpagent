<?php
/**
 * Core plugin orchestrator.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get the singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire hooks.
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_allow_insecure_app_passwords' ) );
		add_action( 'rest_api_init', array( new WPAgent_REST(), 'register_routes' ) );
		WPAgent_Health_Probe::hooks();
		WPAgent_Theme_Preview::hooks();

		if ( is_admin() ) {
			$admin = new WPAgent_Admin();
			$admin->hooks();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wpagent', false, dirname( plugin_basename( WPAGENT_FILE ) ) . '/languages' );
	}

	/**
	 * Local-dev only: Application Passwords are HTTPS-only by default.
	 */
	public function maybe_allow_insecure_app_passwords(): void {
		if ( ! get_option( 'wpagent_allow_insecure_app_passwords', false ) ) {
			return;
		}

		add_filter( 'wp_is_application_passwords_available', '__return_true' );
	}
}
