<?php
/**
 * Draft-first status policy tests.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class StatusPolicyTest extends TestCase {

	public function test_defaults_to_draft(): void {
		$this->assertSame( 'draft', WPAgent_Status_Policy::resolve( null ) );
		$this->assertSame( 'draft', WPAgent_Status_Policy::resolve( 'publish' ) );
	}

	public function test_explicit_publish_allowed(): void {
		$this->assertSame( 'publish', WPAgent_Status_Policy::resolve( 'publish', true ) );
	}

	public function test_pending_does_not_need_explicit_publish(): void {
		$this->assertSame( 'pending', WPAgent_Status_Policy::resolve( 'pending' ) );
	}

	public function test_unknown_status_falls_back(): void {
		$this->assertSame( 'draft', WPAgent_Status_Policy::resolve( 'live' ) );
	}

	public function test_future_requires_explicit_flag(): void {
		$this->assertSame( 'draft', WPAgent_Status_Policy::resolve( 'future' ) );
		$this->assertSame( 'future', WPAgent_Status_Policy::resolve( 'future', true ) );
	}

	public function test_trash_is_never_forced_to_draft(): void {
		$this->assertSame( 'trash', WPAgent_Status_Policy::resolve( 'trash' ) );
		$this->assertSame( 'trash', WPAgent_Status_Policy::resolve( 'trash', false ) );
		$this->assertContains( 'trash', WPAgent_Status_Policy::ALLOWED );
	}
}
