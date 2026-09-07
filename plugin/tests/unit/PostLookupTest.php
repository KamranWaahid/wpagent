<?php
/**
 * Type-mismatch errors for posts vs pages.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class PostLookupTest extends TestCase {

	public function test_missing_id(): void {
		$this->assertSame( 'Post not found.', WPAgent_Post_Lookup::type_mismatch_message( 7, 'post', null ) );
		$this->assertSame( 'Page not found.', WPAgent_Post_Lookup::type_mismatch_message( 7, 'page', null ) );
	}

	public function test_page_id_used_as_post(): void {
		$post = (object) array( 'post_type' => 'page' );
		$this->assertSame(
			'Post not found (id 1686 is a page). Use get_page / update_page / delete_page.',
			WPAgent_Post_Lookup::type_mismatch_message( 1686, 'post', $post )
		);
	}

	public function test_post_id_used_as_page(): void {
		$post = (object) array( 'post_type' => 'post' );
		$this->assertSame(
			'Page not found (id 99 is a post). Use get_post / update_post / delete_post.',
			WPAgent_Post_Lookup::type_mismatch_message( 99, 'page', $post )
		);
	}
}
