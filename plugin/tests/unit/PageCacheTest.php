<?php
/**
 * Page-cache adapter catalog (no cache plugins loaded).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class PageCacheTest extends TestCase {

	public function test_adapter_ids_are_unique(): void {
		$ids = array_map( static fn( array $row ) => $row['id'], WPAgent_Page_Cache::known_adapters() );
		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );
		$this->assertContains( 'object_cache', $ids );
		$this->assertContains( 'litespeed', $ids );
		$this->assertContains( 'wp_rocket', $ids );
	}

	public function test_auto_flush_includes_builder_and_theme_publish(): void {
		$this->assertTrue( WPAgent_Page_Cache::should_flush_after( 'save_builder_page' ) );
		$this->assertTrue( WPAgent_Page_Cache::should_flush_after( 'delete_page' ) );
		$this->assertTrue( WPAgent_Page_Cache::should_flush_after( 'delete_post' ) );
		$this->assertTrue( WPAgent_Page_Cache::should_flush_after( 'publish_draft_theme' ) );
		$this->assertTrue( WPAgent_Page_Cache::should_flush_after( 'install_plugin' ) );
		$this->assertFalse( WPAgent_Page_Cache::should_flush_after( 'create_post' ) );
		$this->assertFalse( WPAgent_Page_Cache::should_flush_after( 'list_posts' ) );
	}

	public function test_flush_without_wordpress_skips_adapters(): void {
		$out = WPAgent_Page_Cache::flush();
		$this->assertTrue( $out['flushed'] );
		$this->assertSame( array(), $out['ran'] );
		$this->assertContains( 'object_cache', $out['skipped'] );
	}

	public function test_flush_page_cache_is_a_write(): void {
		$def = WPAgent_Allowlist::get( 'flush_page_cache' );
		$this->assertTrue( $def['write'] );
		$this->assertEmpty( $def['requires_confirm'] ?? false );
	}
}
