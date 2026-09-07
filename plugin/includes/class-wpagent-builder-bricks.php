<?php
/**
 * Bricks save path: security check, then content meta, editor mode, wp_update_post.
 *
 * @package WPAgent
 */

class WPAgent_Builder_Bricks {

	public function active(): bool {
		return defined( 'BRICKS_VERSION' ) || class_exists( '\Bricks\Database' );
	}

	public function version(): ?string {
		return defined( 'BRICKS_VERSION' ) ? (string) BRICKS_VERSION : null;
	}

	public function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'bricks' => 'run',
			),
			get_permalink( $id ) ? get_permalink( $id ) : home_url( '/' )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function catalog( ?string $item ): array {
		if ( is_callable( array( '\Bricks\Elements', 'load_elements' ) ) ) {
			\Bricks\Elements::load_elements();
		}
		$raw = array();
		if ( is_callable( array( '\Bricks\Elements', 'get_elements' ) ) ) {
			$raw = \Bricks\Elements::get_elements();
		} elseif ( class_exists( '\Bricks\Elements' ) && isset( \Bricks\Elements::$elements ) ) {
			$raw = \Bricks\Elements::$elements;
		}
		$items = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $name => $def ) {
				$slug = is_array( $def ) && isset( $def['name'] ) ? (string) $def['name'] : (string) $name;
				$row  = array(
					'slug'  => $slug,
					'label' => is_array( $def ) && isset( $def['label'] ) ? (string) $def['label'] : $slug,
				);
				if ( $item && $item === $slug ) {
					$row['definition'] = $def;
				}
				$items[] = $row;
			}
		}
		return array(
			'builder' => 'bricks',
			'count'   => count( $items ),
			'items'   => $items,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function read( int $id ): array {
		return array(
			'editor_mode' => get_post_meta( $id, $this->meta_key( 'BRICKS_DB_EDITOR_MODE', '_bricks_editor_mode' ), true ),
			'elements'    => get_post_meta( $id, $this->meta_key( 'BRICKS_DB_PAGE_CONTENT', '_bricks_page_content_2' ), true ),
		);
	}

	/**
	 * @param array<string, mixed> $args Save args.
	 * @return array<string, mixed>
	 */
	public function normalize_write( array $args ): array {
		return array(
			'elements' => WPAgent_Builder_Payload::bricks_elements( $args['elements'] ?? null ),
		);
	}

	/**
	 * @param array<string, mixed> $payload Normalized payload.
	 * @param array<string, mixed> $args    Original args.
	 * @param array<int, mixed>    $warnings Warnings.
	 */
	public function write( int $id, array $payload, string $status, string $title, int $created_id, array $args, array &$warnings ): void {
		if ( is_callable( array( '\Bricks\Capabilities', 'current_user_can_use_builder' ) )
			&& ! \Bricks\Capabilities::current_user_can_use_builder( $id ) ) {
			throw new RuntimeException( 'This account does not have Bricks builder access for the post.' );
		}

		if ( is_callable( array( '\Bricks\Elements', 'load_elements' ) ) ) {
			\Bricks\Elements::load_elements();
		}

		$slashed = function_exists( 'wp_slash' ) ? wp_slash( $payload['elements'] ) : $payload['elements'];
		if ( is_callable( array( '\Bricks\Helpers', 'security_check_elements_before_save' ) ) ) {
			$checked = \Bricks\Helpers::security_check_elements_before_save( $slashed, $id, 'content' );
			if ( ! is_array( $checked ) || count( $checked ) !== count( $slashed ) ) {
				throw new RuntimeException( 'Bricks rejected the layout (code/SVG/query settings or missing builder permission).' );
			}
			if ( $checked !== $slashed ) {
				$warnings[] = array(
					'code'    => 'bricks_security_modified',
					'message' => 'Bricks stripped some element settings before save. Re-read the page to see what was stored.',
				);
			}
			$slashed = $checked;
		}

		$content_key = $this->meta_key( 'BRICKS_DB_PAGE_CONTENT', '_bricks_page_content_2' );
		$mode_key    = $this->meta_key( 'BRICKS_DB_EDITOR_MODE', '_bricks_editor_mode' );
		update_post_meta( $id, $content_key, $slashed );
		$stored = get_post_meta( $id, $content_key, true );
		if ( empty( $stored ) || ! is_array( $stored ) ) {
			throw new RuntimeException( 'Bricks blocked the layout write. The account may lack builder access.' );
		}
		update_post_meta( $id, $mode_key, 'bricks' );

		$update = array(
			'ID'          => $id,
			'post_status' => $status,
		);
		if ( 0 === $created_id && '' !== $title ) {
			$update['post_title'] = $title;
		}
		wp_update_post( $update );
	}

	private function meta_key( string $constant, string $fallback ): string {
		return defined( $constant ) ? (string) constant( $constant ) : $fallback;
	}
}
