<?php
/**
 * Allowlist tests.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class AllowlistTest extends TestCase {

	public function test_unknown_command_is_denied(): void {
		$this->assertFalse( WPAgent_Allowlist::is_permitted( 'drop_database' ) );
		$this->assertNull( WPAgent_Allowlist::get( 'eval_php' ) );
		$this->assertNull( WPAgent_Allowlist::capability_for( 'not_a_command' ) );
	}

	public function test_known_commands_have_capabilities(): void {
		foreach ( WPAgent_Allowlist::definitions() as $name => $def ) {
			$this->assertArrayHasKey( 'capability', $def, $name );
			$this->assertNotSame( '', $def['capability'], $name );
			$this->assertArrayHasKey( 'write', $def, $name );
			$this->assertTrue( WPAgent_Allowlist::is_permitted( $name ) );
		}
	}

	public function test_phase1_commands_exist(): void {
		foreach ( array( 'list_posts', 'get_post', 'get_site_health', 'get_current_user_capabilities' ) as $command ) {
			$this->assertNotNull( WPAgent_Allowlist::get( $command ) );
		}
	}

	public function test_disabled_commands_are_rejected(): void {
		$this->assertFalse( WPAgent_Allowlist::is_permitted( 'update_plugin', array( 'update_plugin' ) ) );
		$this->assertTrue( WPAgent_Allowlist::is_permitted( 'list_posts', array( 'update_plugin' ) ) );
	}

	public function test_cannot_disable_unknown_commands(): void {
		$normalized = WPAgent_Allowlist::normalize_disabled( array( 'list_posts', 'rm_rf', 123, null ) );
		$this->assertSame( array( 'list_posts' ), $normalized );
	}

	public function test_destructive_flags(): void {
		$delete = WPAgent_Allowlist::get( 'delete_post' );
		$this->assertTrue( $delete['trash_only'] );
		$page_delete = WPAgent_Allowlist::get( 'delete_page' );
		$this->assertTrue( $page_delete['trash_only'] );
		$permanent = WPAgent_Allowlist::get( 'permanent_delete_post' );
		$this->assertTrue( $permanent['requires_confirm'] );
		$this->assertTrue( WPAgent_Allowlist::get( 'permanent_delete_page' )['requires_confirm'] );
		$search = WPAgent_Allowlist::get( 'run_search_replace' );
		$this->assertTrue( $search['dry_run_default'] );
		$core = WPAgent_Allowlist::get( 'update_core' );
		$this->assertTrue( $core['requires_confirm'] );
		$this->assertSame( 'update_core', $core['capability'] );
	}

	public function test_create_post_is_draft_first(): void {
		$create = WPAgent_Allowlist::get( 'create_post' );
		$this->assertTrue( $create['draft_first'] );
		$this->assertSame( 'edit_posts', WPAgent_Allowlist::capability_for( 'create_post' ) );
	}
}
