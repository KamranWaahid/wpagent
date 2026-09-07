<?php
/**
 * PHPUnit bootstrap (no WordPress required for unit tests).
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wpagent-tests/' );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ): string {
		return trim( strip_tags( (string) $str ) );
	}
}

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require_once $autoload;
} else {
	$includes = dirname( __DIR__ ) . '/includes/';
	foreach ( glob( $includes . 'class-wpagent-*.php' ) as $file ) {
		require_once $file;
	}
}
