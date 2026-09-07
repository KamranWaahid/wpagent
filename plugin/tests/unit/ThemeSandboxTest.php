<?php
/**
 * Theme path sandbox + PHP guard (no WordPress runtime).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class ThemeSandboxTest extends TestCase {

	public function test_relative_rejects_traversal(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Theme_Sandbox::relative( '../wp-config.php' );
	}

	public function test_relative_normalizes(): void {
		$this->assertSame( 'assets/style.css', WPAgent_Theme_Sandbox::relative( '/assets/./style.css' ) );
	}

	public function test_php_is_allowed_exe_is_not(): void {
		$this->assertTrue( WPAgent_Theme_Sandbox::extension_allowed( 'functions.php' ) );
		$this->assertFalse( WPAgent_Theme_Sandbox::extension_allowed( 'shell.exe' ) );
	}

	public function test_builder_parents_are_blocked(): void {
		$this->assertTrue( WPAgent_Theme_Sandbox::is_blocked_parent( 'Divi' ) );
		$this->assertFalse( WPAgent_Theme_Sandbox::is_blocked_parent( 'divi-child' ) );
	}

	public function test_php_guard_accepts_valid_and_rejects_broken(): void {
		WPAgent_Php_Guard::assert_valid( '<?php echo 1;', 'ok.php' );
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Php_Guard::assert_valid( '<?php function (', 'bad.php' );
	}

	public function test_glob_and_prefix_match_inc_without_samples(): void {
		$this->assertTrue( WPAgent_Theme_Sandbox::match_glob( 'inc/storefront-helpers.php', 'inc/*.php' ) );
		$this->assertTrue( WPAgent_Theme_Sandbox::match_glob( 'inc/a/b.php', 'inc/**' ) );
		$this->assertFalse( WPAgent_Theme_Sandbox::match_glob( 'inc/a/b.php', 'inc/*.php' ) );
		$this->assertTrue( WPAgent_Theme_Sandbox::match_glob( 'samples/content.xml', 'samples/**' ) );
		$this->assertTrue( WPAgent_Theme_Sandbox::matches_prefix( 'inc/foo.php', 'inc' ) );
		$this->assertTrue( WPAgent_Theme_Sandbox::matches_prefix( 'inc/foo.php', 'inc/' ) );
		$this->assertFalse( WPAgent_Theme_Sandbox::matches_prefix( 'vamtam/inc/foo.php', 'inc' ) );
		$this->assertSame( array( 'samples/**' ), WPAgent_Theme_Sandbox::exclude_globs( null ) );
		$this->assertSame( array(), WPAgent_Theme_Sandbox::exclude_globs( '' ) );
		$this->assertTrue( WPAgent_Theme_Sandbox::is_excluded( 'samples/content.xml', array( 'samples/**' ) ) );
		$this->assertFalse( WPAgent_Theme_Sandbox::is_excluded( 'inc/storefront-helpers.php', array( 'samples/**' ) ) );
	}

	public function test_copy_and_rmdir(): void {
		$from = sys_get_temp_dir() . '/wpagent-theme-from-' . uniqid();
		$to   = sys_get_temp_dir() . '/wpagent-theme-to-' . uniqid();
		mkdir( $from );
		file_put_contents( $from . '/style.css', 'x' );
		WPAgent_Draft_Theme::copy_dir( $from, $to );
		$this->assertFileExists( $to . '/style.css' );
		WPAgent_Draft_Theme::rmdir( $to );
		WPAgent_Draft_Theme::rmdir( $from );
		$this->assertDirectoryDoesNotExist( $to );
	}
}
