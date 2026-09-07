<?php
/**
 * Inspect URL helpers (no HTTP).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class InspectTest extends TestCase {

	public function test_html_excerpt_prefers_body_and_contains(): void {
		$html = '<html><head><title>x</title><link rel="stylesheet" href="huge.css"></head><body><p>Free shipping</p></body></html>';
		$this->assertStringContainsString( 'Free shipping', WPAgent_Inspect::html_excerpt( $html ) );
		$this->assertStringNotContainsString( '<head>', WPAgent_Inspect::html_excerpt( $html ) );
		$wide = str_repeat( 'a', 500 ) . 'FINDME' . str_repeat( 'b', 500 );
		$this->assertStringContainsString( 'FINDME', WPAgent_Inspect::html_excerpt( $wide, 200, 'FINDME' ) );
	}

	public function test_absolutize_relative_and_absolute(): void {
		$this->assertSame(
			'https://example.com/checkout/',
			WPAgent_Inspect::absolutize( '/checkout/', 'https://example.com/?add-to-cart=42' )
		);
		$this->assertSame(
			'https://example.com/cart/',
			WPAgent_Inspect::absolutize( 'https://example.com/cart/', 'https://example.com/' )
		);
	}

	public function test_preview_isolation_entry_shape(): void {
		$this->assertSame(
			array(
				'action' => 'wp_trash_post',
				'id'     => 1686,
			),
			WPAgent_Preview_Isolation::format_entry( 'wp_trash_post', 1686 )
		);
	}

	public function test_preview_isolation_restores_status_when_existing_post_missing(): void {
		$data = WPAgent_Preview_Isolation::block_trash_status(
			array( 'post_status' => 'trash', 'post_title' => 'Returns' ),
			array()
		);
		$this->assertSame( 'draft', $data['post_status'] );
	}
}
