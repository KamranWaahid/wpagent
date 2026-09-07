<?php
/**
 * Divi save path: enable the builder, then persist the shortcode tree on the post.
 *
 * @package WPAgent
 */

class WPAgent_Builder_Divi {

	public function active(): bool {
		return defined( 'ET_BUILDER_VERSION' ) || function_exists( 'et_pb_is_pagebuilder_used' ) || class_exists( 'ET_Builder_Element' );
	}

	public function version(): ?string {
		return defined( 'ET_BUILDER_VERSION' ) ? (string) ET_BUILDER_VERSION : null;
	}

	public function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'et_fb' => '1',
				'PageSpeed' => 'off',
			),
			get_permalink( $id ) ? get_permalink( $id ) : admin_url( 'post.php?post=' . $id . '&action=edit' )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function catalog( ?string $item ): array {
		$items = array();
		if ( class_exists( 'ET_Builder_Element' ) && is_callable( array( 'ET_Builder_Element', 'get_parent_shortcodes' ) ) ) {
			foreach ( (array) ET_Builder_Element::get_parent_shortcodes() as $slug ) {
				$items[] = array( 'slug' => (string) $slug );
			}
		}
		if ( array() === $items ) {
			$items = array(
				array( 'slug' => 'et_pb_section' ),
				array( 'slug' => 'et_pb_row' ),
				array( 'slug' => 'et_pb_column' ),
			);
		}
		if ( $item ) {
			$items = array_values(
				array_filter( $items, static fn( array $row ) => $row['slug'] === $item )
			);
		}
		return array(
			'builder' => 'divi',
			'count'   => count( $items ),
			'items'   => $items,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function read( int $id ): array {
		$post = get_post( $id );
		$used = function_exists( 'et_pb_is_pagebuilder_used' ) ? et_pb_is_pagebuilder_used( $id ) : ( 'on' === get_post_meta( $id, '_et_pb_use_builder', true ) );
		return array(
			'builder_used' => (bool) $used,
			'content'      => $post ? (string) $post->post_content : '',
		);
	}

	/**
	 * @param array<string, mixed> $args Save args.
	 * @return array<string, mixed>
	 */
	public function normalize_write( array $args ): array {
		return array(
			'content' => WPAgent_Builder_Payload::divi_content( $args['content'] ?? null ),
		);
	}

	/**
	 * @param array<string, mixed> $payload Normalized payload.
	 * @param array<string, mixed> $args    Original args.
	 * @param array<int, mixed>    $warnings Warnings.
	 */
	public function write( int $id, array $payload, string $status, string $title, int $created_id, array $args, array &$warnings ): void {
		if ( function_exists( 'et_pb_is_allowed' ) && ! et_pb_is_allowed( 'use_visual_builder' ) ) {
			throw new RuntimeException( 'This account is not allowed to use the Divi Visual Builder.' );
		}

		if ( function_exists( 'et_pb_set_builder_status' ) ) {
			et_pb_set_builder_status( 'on', $id );
		} else {
			update_post_meta( $id, '_et_pb_use_builder', 'on' );
		}

		$update = array(
			'ID'           => $id,
			'post_status'  => $status,
			'post_content' => $payload['content'],
		);
		if ( '' !== $title ) {
			$update['post_title'] = $title;
		}
		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}

		WPAgent_Builders::warn_step(
			$warnings,
			'divi_cache_clear_failed',
			static function () use ( $id ) {
				if ( class_exists( 'ET_Core_PageResource' ) && is_callable( array( 'ET_Core_PageResource', 'remove_static_resources' ) ) ) {
					ET_Core_PageResource::remove_static_resources( $id, 'all' );
				}
			}
		);
	}
}
