<?php
/**
 * PSR-0-ish autoloader for WPAgent_* classes.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a WPAgent class file.
	 *
	 * @param string $class Class name.
	 */
	public static function load( string $class ): void {
		if ( ! str_starts_with( $class, 'WPAgent' ) ) {
			return;
		}

		$slug = strtolower( str_replace( '_', '-', $class ) );
		$file = 'class-' . $slug . '.php';

		$dirs = array(
			WPAGENT_DIR . 'includes/',
			WPAGENT_DIR . 'includes/rest/',
			WPAGENT_DIR . 'includes/admin/',
		);

		foreach ( $dirs as $dir ) {
			$path = $dir . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
