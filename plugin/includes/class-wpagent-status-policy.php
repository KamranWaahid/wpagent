<?php
/**
 * Draft-first status policy for posts and pages.
 *
 * @package WPAgent
 */

class WPAgent_Status_Policy {

	public const ALLOWED = array( 'draft', 'pending', 'private', 'publish', 'future', 'trash' );

	/**
	 * Resolve the post status to persist.
	 *
	 * Defaults to draft unless $explicit_publish is true (the human said "publish")
	 * AND the requested status is publish/future. trash is never remapped to draft.
	 *
	 * @param string|null $requested         Requested status.
	 * @param bool        $explicit_publish  Whether the caller confirmed publish intent.
	 * @param string      $default           Default status.
	 */
	public static function resolve( ?string $requested, bool $explicit_publish = false, string $default = 'draft' ): string {
		$status = is_string( $requested ) ? strtolower( trim( $requested ) ) : $default;

		if ( ! in_array( $status, self::ALLOWED, true ) ) {
			$status = $default;
		}

		if ( 'trash' === $status ) {
			return 'trash';
		}

		if ( in_array( $status, array( 'publish', 'future' ), true ) && ! $explicit_publish ) {
			return 'draft';
		}

		return $status;
	}

	/**
	 * Whether a status is a live, public-facing publication.
	 */
	public static function is_public( string $status ): bool {
		return in_array( $status, array( 'publish', 'future' ), true );
	}
}
