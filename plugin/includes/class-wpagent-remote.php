<?php
/**
 * Outbound calls to a user-configured WPAgent MCP host (pairing).
 *
 * The destination is never hardcoded — it is the URL the site owner typed
 * in wp-admin. Used only to deliver an Application Password to that session.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Remote {

	/**
	 * POST site credentials to the MCP server using a pairing code.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function link_by_pairing( string $mcp_base, string $pairing_code, string $username, string $password ) {
		$base = untrailingslashit( esc_url_raw( $mcp_base ) );
		if ( ! $base || ! wp_http_validate_url( $base ) ) {
			return new WP_Error( 'wpagent_remote_url', __( 'Enter a valid MCP server URL.', 'wpagent' ) );
		}

		$endpoint = $base . '/auth/link-by-pairing';
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'      => wp_json_encode(
					array(
						'pairing_code' => $pairing_code,
						'url'          => home_url( '/' ),
						'username'     => $username,
						'password'     => $password,
						'id'           => sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'site' ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && ! empty( $body['message'] ) ? (string) $body['message'] : __( 'MCP server rejected the pairing request.', 'wpagent' );
			return new WP_Error( 'wpagent_remote_http', $message, array( 'status' => $code ) );
		}

		return is_array( $body ) ? $body : array( 'ok' => true );
	}
}
