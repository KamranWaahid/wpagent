<?php
/**
 * Nav menu action allowlist (no WordPress menu runtime).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class MenusTest extends TestCase {

	public function test_rejects_unknown_action(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Menus::normalize_action( 'drop' );
	}

	public function test_known_actions(): void {
		foreach ( array( 'create', 'add_item', 'update_item', 'remove_item', 'assign_location' ) as $action ) {
			$this->assertSame( $action, WPAgent_Menus::normalize_action( $action ) );
		}
	}

	public function test_manage_without_wordpress_fails_on_create_name(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Menus::manage( array( 'action' => 'create' ) );
	}

	public function test_delete_requires_confirm(): void {
		$def = WPAgent_Allowlist::get( 'delete_nav_menu' );
		$this->assertTrue( ! empty( $def['requires_confirm'] ) );
		$this->assertSame( 'edit_theme_options', WPAgent_Allowlist::capability_for( 'manage_nav_menu' ) );
	}
}
