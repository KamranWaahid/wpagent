<?php
/**
 * Block content mutations while a draft theme is previewed.
 *
 * Draft PHP runs on ?wpagent_preview= and can call wp_trash_post on the live DB.
 * Preview must not apply those writes; publish_draft_theme is the live path.
 *
 * @package WPAgent
 */

class WPAgent_Preview_Isolation {

	public const OPTION = 'wpagent_preview_blocked';

	public static function install(): void {
		add_filter( 'pre_trash_post', array( self::class, 'block_trash' ), 0, 3 );
		add_filter( 'pre_delete_post', array( self::class, 'block_delete' ), 0, 3 );
		add_filter( 'wp_insert_post_data', array( self::class, 'block_trash_status' ), 0, 2 );
	}

	/**
	 * @param mixed   $check Check.
	 * @param WP_Post $post  Post.
	 * @return false
	 */
	public static function block_trash( $check, $post, $previous_status = '' ) {
		unset( $check, $previous_status );
		$id = is_object( $post ) ? (int) $post->ID : 0;
		self::record( 'wp_trash_post', $id );
		return false;
	}

	/**
	 * @param mixed   $check Check.
	 * @param WP_Post $post  Post.
	 * @return false
	 */
	public static function block_delete( $check, $post, $force_delete = false ) {
		unset( $check, $force_delete );
		$id = is_object( $post ) ? (int) $post->ID : 0;
		self::record( 'wp_delete_post', $id );
		return false;
	}

	/**
	 * @param array<string, mixed> $data    Post data.
	 * @param array<string, mixed> $postarr Original.
	 * @return array<string, mixed>
	 */
	public static function block_trash_status( array $data, array $postarr ): array {
		if ( ( $data['post_status'] ?? '' ) !== 'trash' ) {
			return $data;
		}
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		self::record( 'post_status=trash', $id );
		if ( $id > 0 && function_exists( 'get_post' ) ) {
			$existing = get_post( $id );
			if ( $existing && 'trash' !== $existing->post_status ) {
				$data['post_status'] = $existing->post_status;
			} else {
				$data['post_status'] = 'draft';
			}
		} else {
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function summary(): array {
		return array(
			'content_writes' => 'blocked',
			'note'           => 'Draft theme PHP cannot trash, delete, or set post/page status to trash while ?wpagent_preview= is active. Publish the draft to run those writes on the live site.',
			'blocked'        => self::recorded(),
		);
	}

	/**
	 * @return array<int, array{action:string,id:int}>
	 */
	public static function format_entry( string $action, int $id ): array {
		return array(
			'action' => $action,
			'id'     => $id,
		);
	}

	public static function clear(): void {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::OPTION );
		}
	}

	/**
	 * @return array<int, array{action:string,id:int}>
	 */
	private static function recorded(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}
		$raw = get_option( self::OPTION, array() );
		return is_array( $raw ) ? array_values( $raw ) : array();
	}

	private static function record( string $action, int $id ): void {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}
		$items   = self::recorded();
		$items[] = self::format_entry( $action, $id );
		if ( count( $items ) > 20 ) {
			$items = array_slice( $items, -20 );
		}
		update_option( self::OPTION, $items, false );
	}
}
