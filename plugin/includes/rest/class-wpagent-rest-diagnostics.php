<?php
/**
 * Rendered HTML inspection and read-only SQL.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Diagnostics extends WPAgent_REST_Controller {

	public function register_routes(): void {
		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/inspect-html',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'inspect_html' ),
				'permission_callback' => WPAgent_Permissions::callback( 'inspect_rendered_html' ),
			)
		);

		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/query',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'query_db' ),
				'permission_callback' => WPAgent_Permissions::callback( 'query_db' ),
			)
		);
	}

	public function inspect_html( WP_REST_Request $request ) {
		return $this->execute(
			'inspect_rendered_html',
			$request,
			function () use ( $request ) {
				$target = esc_url_raw( (string) $request->get_param( 'url' ) );
				if ( ! $target ) {
					$path = (string) $request->get_param( 'path' );
					$target = home_url( $path ?: '/' );
				}

				if ( ! $this->is_same_origin( $target ) ) {
					throw new InvalidArgumentException( 'URL must be on this WordPress site (same origin).' );
				}

				$add_to_cart = absint( $request->get_param( 'add_to_cart' ) );
				$fetched     = WPAgent_Inspect::fetch(
					$target,
					$add_to_cart,
					fn( string $url ) => $this->is_same_origin( $url )
				);
				$response = $fetched['response'];
				if ( is_wp_error( $response ) ) {
					throw new RuntimeException( $response->get_error_message() );
				}

				$code = wp_remote_retrieve_response_code( $response );
				$html = (string) wp_remote_retrieve_body( $response );
				$html = $this->strip_noise( $html );

				$text = wp_strip_all_tags( $html );
				$text = preg_replace( '/[ \t]+/', ' ', $text ) ?? $text;
				$text = preg_replace( '/\n{3,}/', "\n\n", $text ) ?? $text;

				$contains  = (string) $request->get_param( 'contains' );
				$final_url = (string) ( $fetched['final_url'] ?? $target );
				$out       = array(
					'url'           => $final_url,
					'requested_url' => $target,
					'redirected'    => ! empty( $fetched['redirected'] ),
					'status'        => (int) $code,
					'title'         => $this->extract_title( $html ),
					'text'          => substr( trim( $text ), 0, 15000 ),
					'html_excerpt'  => WPAgent_Inspect::html_excerpt( $html, 8000, $contains ),
					'headings'      => $this->extract_headings( $html ),
				);
				if ( is_array( $fetched['session'] ) ) {
					$out['session'] = $fetched['session'];
				}
				return $out;
			}
		);
	}

	public function query_db( WP_REST_Request $request ) {
		return $this->execute(
			'query_db',
			$request,
			function () use ( $request ) {
				$validated = WPAgent_SQL_Guard::assert_read_only(
					(string) $request->get_param( 'sql' ),
					(int) ( $request->get_param( 'limit' ) ?: WPAgent_SQL_Guard::DEFAULT_ROW_LIMIT )
				);
				$sql = WPAgent_SQL_Guard::apply_limit( $validated['sql'], $validated['limit'] );

				global $wpdb;
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results( $sql, ARRAY_A );
				if ( $wpdb->last_error ) {
					throw new InvalidArgumentException( $wpdb->last_error );
				}

				return WPAgent_SQL_Guard::format_result( $rows ?: array(), $validated['limit'] );
			}
		);
	}

	private function is_same_origin( string $url ): bool {
		$home = wp_parse_url( home_url( '/' ) );
		$got  = wp_parse_url( $url );
		if ( ! $home || ! $got ) {
			return false;
		}
		$host_ok = isset( $home['host'], $got['host'] ) && strtolower( $home['host'] ) === strtolower( $got['host'] );
		$scheme_ok = empty( $got['scheme'] ) || strtolower( (string) $got['scheme'] ) === strtolower( (string) ( $home['scheme'] ?? 'https' ) );
		return $host_ok && $scheme_ok;
	}

	private function strip_noise( string $html ): string {
		$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html ) ?? $html;
		$html = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $html ) ?? $html;
		$html = preg_replace( '#<noscript\b[^>]*>.*?</noscript>#is', '', $html ) ?? $html;
		return $html;
	}

	private function extract_title( string $html ): string {
		if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
			return trim( wp_strip_all_tags( $m[1] ) );
		}
		return '';
	}

	/**
	 * @return array<int, array{level:int,text:string}>
	 */
	private function extract_headings( string $html ): array {
		$out = array();
		if ( ! preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', $html, $matches, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( array_slice( $matches, 0, 40 ) as $match ) {
			$out[] = array(
				'level' => (int) $match[1],
				'text'  => trim( wp_strip_all_tags( $match[2] ) ),
			);
		}
		return $out;
	}
}
