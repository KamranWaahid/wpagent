<?php
/**
 * wordpress.org-only plugin install guards.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class PluginInstallTest extends TestCase {

	public function test_slug_rejects_url_and_path(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Plugin_Install::normalize_slug( 'https://github.com/evil/plugin.zip' );
	}

	public function test_slug_rejects_plugin_file(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Plugin_Install::normalize_slug( 'akismet/akismet.php' );
	}

	public function test_slug_normalizes(): void {
		$this->assertSame( 'akismet', WPAgent_Plugin_Install::normalize_slug( 'Akismet' ) );
		$this->assertSame( 'woocommerce', WPAgent_Plugin_Install::normalize_slug( 'woocommerce' ) );
	}

	public function test_zip_must_be_downloads_wordpress_org(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Plugin_Install::assert_wordpress_org_zip( 'https://example.com/plugin/akismet.zip' );
	}

	public function test_zip_rejects_http(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Plugin_Install::assert_wordpress_org_zip( 'http://downloads.wordpress.org/plugin/akismet.1.0.zip' );
	}

	public function test_zip_accepts_official(): void {
		$url = 'https://downloads.wordpress.org/plugin/akismet.5.3.zip';
		$this->assertSame( $url, WPAgent_Plugin_Install::assert_wordpress_org_zip( $url ) );
	}

	public function test_install_plugin_requires_confirm(): void {
		$def = WPAgent_Allowlist::get( 'install_plugin' );
		$this->assertTrue( ! empty( $def['requires_confirm'] ) );
		$this->assertSame( 'install_plugins', $def['capability'] );
	}

	public function test_cli_plugin_install_is_destructive(): void {
		$meta = WPAgent_Cli::catalog()['plugin install'];
		$this->assertSame( 'destructive', $meta['tier'] );
		$this->assertSame( 'install_plugins', $meta['cap'] );
	}
}
