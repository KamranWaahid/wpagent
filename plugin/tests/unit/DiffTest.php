<?php
/**
 * Search-replace preview diffs.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class DiffTest extends TestCase {

	public function test_snippets_include_before_after_and_unified(): void {
		$original = 'Hello old-domain.com is the site and old-domain.com again.';
		$snippets = WPAgent_Diff::field_snippets( $original, 'old-domain.com', 'new-domain.com', 2 );
		$this->assertCount( 2, $snippets );
		$this->assertStringContainsString( 'old-domain.com', $snippets[0]['before'] );
		$this->assertStringContainsString( 'new-domain.com', $snippets[0]['after'] );
		$this->assertStringStartsWith( '- ', $snippets[0]['unified'] );
		$this->assertStringContainsString( "\n+ ", $snippets[0]['unified'] );
	}

	public function test_no_match_returns_empty(): void {
		$this->assertSame( array(), WPAgent_Diff::field_snippets( 'nothing here', 'zzz', 'yyy' ) );
	}
}
