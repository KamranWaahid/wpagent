<?php
/**
 * Plugin deactivation.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Deactivator {

	/**
	 * Run on deactivation. Data is retained unless uninstall cleanup is enabled.
	 */
	public static function deactivate(): void {
		// Intentionally no table drops. Audit history stays until uninstall.
	}
}
