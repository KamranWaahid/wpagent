<?php
/**
 * Fatal-error string detector used after updates.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class HealthProbeTest extends TestCase {

	public function test_detects_wp_critical_error_page(): void {
		$this->assertTrue( WPAgent_Health_Probe::looks_fatal( 'There has been a critical error on this website.' ) );
		$this->assertTrue( WPAgent_Health_Probe::looks_fatal( 'Fatal error: Uncaught Error' ) );
		$this->assertFalse( WPAgent_Health_Probe::looks_fatal( '<html><body>Hello</body></html>' ) );
	}
}
