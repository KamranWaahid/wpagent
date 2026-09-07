<?php
/**
 * Post/page type mismatch messages.
 *
 * @package WPAgent
 */

class WPAgent_Post_Lookup {

	/**
	 * Error when an ID is missing or is the wrong post type.
	 *
	 * @param object|null $post Post-like object with post_type, or null.
	 */
	public static function type_mismatch_message( int $id, string $expected, $post ): string {
		$label = ucfirst( $expected );
		if ( ! is_object( $post ) || empty( $post->post_type ) ) {
			return sprintf( '%s not found.', $label );
		}

		$actual = (string) $post->post_type;
		if ( $actual === $expected ) {
			return sprintf( '%s not found.', $label );
		}

		$hint = self::tool_hint( $expected, $actual );
		return sprintf(
			'%s not found (id %d is a %s).%s',
			$label,
			$id,
			$actual,
			$hint ? ' ' . $hint : ''
		);
	}

	public static function tool_hint( string $expected, string $actual ): string {
		if ( 'post' === $expected && 'page' === $actual ) {
			return 'Use get_page / update_page / delete_page.';
		}
		if ( 'page' === $expected && 'post' === $actual ) {
			return 'Use get_post / update_post / delete_post.';
		}
		return sprintf( 'Expected type "%s".', $expected );
	}
}
