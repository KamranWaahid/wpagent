<?php
/**
 * Post-update fatal-error probe via admin-ajax ping + homepage check.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Health_Probe {

	public const ACTION = 'wpagent_health_ping';
	public const TRANSIENT = 'wpagent_health_token';

	public static function hooks(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( self::class, 'handle_ajax' ) );
	}

	public static function handle_ajax(): void {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$expected = (string) get_transient( self::TRANSIENT );
		if ( ! $expected || ! hash_equals( $expected, $token ) ) {
			wp_send_json_error( array( 'ok' => false ), 403 );
		}
		wp_send_json_success(
			array(
				'ok'  => true,
				'php' => PHP_VERSION,
				'wp'  => get_bloginfo( 'version' ),
			)
		);
	}

	/**
	 * @return array{ok:bool,ajax:array<string,mixed>,homepage:array<string,mixed>}
	 */
	public static function probe(): array {
		$token = wp_generate_password( 24, false );
		set_transient( self::TRANSIENT, $token, 120 );

		$ajax_url = add_query_arg(
			array(
				'action' => self::ACTION,
				'token'  => $token,
			),
			admin_url( 'admin-ajax.php' )
		);

		$ajax = self::fetch( $ajax_url );
		$home = self::fetch( home_url( '/' ) );

		$ajax_ok = $ajax['status'] >= 200 && $ajax['status'] < 400 && ! self::looks_fatal( $ajax['body'] );
		$home_ok = $home['status'] >= 200 && $home['status'] < 500 && ! self::looks_fatal( $home['body'] );

		delete_transient( self::TRANSIENT );

		return array(
			'ok'       => $ajax_ok && $home_ok,
			'ajax'     => array(
				'ok'     => $ajax_ok,
				'status' => $ajax['status'],
				'error'  => $ajax['error'],
			),
			'homepage' => array(
				'ok'     => $home_ok,
				'status' => $home['status'],
				'error'  => $home['error'],
			),
		);
	}

	/**
	 * @return array{status:int,body:string,error:?string}
	 */
	private static function fetch( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'sslverify'   => is_ssl(),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 0,
				'body'   => '',
				'error'  => $response->get_error_message(),
			);
		}
		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => (string) wp_remote_retrieve_body( $response ),
			'error'  => null,
		);
	}

	public static function looks_fatal( string $body ): bool {
		$needles = array(
			'There has been a critical error on this website',
			'There has been a critical error on this site',
			'Parse error',
			'Fatal error',
			'Allowed memory size',
		);
		foreach ( $needles as $needle ) {
			if ( str_contains( $body, $needle ) ) {
				return true;
			}
		}
		return false;
	}
}
