<?php
/**
 * Same-origin HTML fetch helpers (optional WooCommerce cart cookie).
 *
 * @package WPAgent
 */

class WPAgent_Inspect {

	/**
	 * @param callable $is_same_origin fn(string $url): bool
	 * @return array{response:array|WP_Error,cookies:array<int, mixed>,session:?array<string, mixed>,final_url:string,redirected:bool}
	 */
	public static function fetch( string $target, int $add_to_cart, callable $is_same_origin ): array {
		$cookies   = array();
		$session   = null;
		$final_url = $target;

		if ( $add_to_cart > 0 ) {
			$session = self::prime_cart( $add_to_cart, $cookies, $is_same_origin );
		}

		$response = self::get_following( $target, $cookies, 3, $is_same_origin, $final_url );
		return array(
			'response'   => $response,
			'cookies'    => $cookies,
			'session'    => $session,
			'final_url'  => $final_url,
			'redirected' => $final_url !== $target,
		);
	}

	/**
	 * @param array<int, mixed> $cookies Cookie jar (by ref).
	 * @param callable          $is_same_origin Origin check.
	 * @return array<string, mixed>
	 */
	private static function prime_cart( int $product_id, array &$cookies, callable $is_same_origin ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			throw new InvalidArgumentException( 'add_to_cart requires WooCommerce.' );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			throw new InvalidArgumentException( sprintf( 'Product %d was not found.', $product_id ) );
		}

		$add_url  = add_query_arg( 'add-to-cart', $product_id, home_url( '/' ) );
		$response = self::get_following( $add_url, $cookies, 5, $is_same_origin );
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		return array(
			'add_to_cart' => $product_id,
			'add_url'     => $add_url,
			'add_status'  => (int) wp_remote_retrieve_response_code( $response ),
			'purchasable' => method_exists( $product, 'is_purchasable' ) ? (bool) $product->is_purchasable() : null,
		);
	}

	/**
	 * Follow same-origin redirects while keeping the cookie jar.
	 *
	 * @param array<int, mixed> $cookies Cookie jar (by ref).
	 * @param callable          $is_same_origin Origin check.
	 * @param string|null       $final_url Last fetched URL (by ref).
	 * @return array|WP_Error
	 */
	public static function get_following( string $url, array &$cookies, int $redirection, callable $is_same_origin, ?string &$final_url = null ) {
		$current   = $url;
		$final_url = $url;
		$response  = null;
		for ( $i = 0; $i <= $redirection; $i++ ) {
			$args = array(
				'timeout'     => 15,
				'redirection' => 0,
				'sslverify'   => true,
			);
			if ( $cookies ) {
				$args['cookies'] = $cookies;
			}
			$response = wp_remote_get( $current, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$got = wp_remote_retrieve_cookies( $response );
			if ( is_array( $got ) && $got ) {
				$cookies = array_merge( $cookies, $got );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 300 || $code >= 400 ) {
				return $response;
			}

			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( ! is_string( $location ) || '' === $location ) {
				return $response;
			}
			$next = self::absolutize( $location, $current );
			if ( ! $is_same_origin( $next ) ) {
				return $response;
			}
			$current   = $next;
			$final_url = $current;
		}
		return $response;
	}

	/**
	 * Prefer a body slice (or a contains window) over the document head.
	 */
	public static function html_excerpt( string $html, int $max = 8000, string $contains = '' ): string {
		if ( '' !== $contains ) {
			$pos = stripos( $html, $contains );
			if ( false !== $pos ) {
				$needle  = strlen( $contains );
				$before  = min( 400, max( 0, (int) floor( ( $max - $needle ) / 2 ) ) );
				$start   = max( 0, $pos - $before );
				return substr( $html, $start, $max );
			}
		}

		if ( preg_match( '#<body[^>]*>(.*)$#is', $html, $match ) ) {
			return substr( $match[1], 0, $max );
		}

		return substr( $html, 0, $max );
	}

	public static function absolutize( string $location, string $current ): string {
		if ( preg_match( '#^https?://#i', $location ) ) {
			return $location;
		}
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $current ) : parse_url( $current );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $location;
		}
		$scheme = $parts['scheme'] ?? 'https';
		$host   = $parts['host'];
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		if ( str_starts_with( $location, '/' ) ) {
			return $scheme . '://' . $host . $port . $location;
		}
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$dir  = str_ends_with( $path, '/' ) ? $path : ( dirname( $path ) . '/' );
		return $scheme . '://' . $host . $port . $dir . $location;
	}
}
