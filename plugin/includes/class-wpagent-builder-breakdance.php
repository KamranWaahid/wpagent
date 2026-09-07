<?php
/**
 * Breakdance save path: encoder + cache regen (not raw serialized meta).
 *
 * @package WPAgent
 */

class WPAgent_Builder_Breakdance {

	public function active(): bool {
		return function_exists( '\Breakdance\Data\set_meta' ) || defined( '__BREAKDANCE_VERSION' );
	}

	public function version(): ?string {
		return defined( '__BREAKDANCE_VERSION' ) ? (string) __BREAKDANCE_VERSION : null;
	}

	public function edit_url( int $id ): string {
		if ( function_exists( '\Breakdance\Admin\get_builder_loader_url' ) ) {
			return \Breakdance\Admin\get_builder_loader_url( (string) $id );
		}
		return admin_url( 'post.php?post=' . $id . '&action=edit' );
	}

	private function data_key(): string {
		if ( function_exists( '\Breakdance\BreakdanceOxygen\Strings\__bdox' ) ) {
			$prefix = \Breakdance\BreakdanceOxygen\Strings\__bdox( '_meta_prefix' );
			if ( is_string( $prefix ) && '' !== $prefix ) {
				return $prefix . 'data';
			}
		}
		return '_breakdance_data';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function catalog( ?string $item ): array {
		$items = array();
		foreach ( get_declared_classes() as $class ) {
			if ( ! is_subclass_of( $class, '\Breakdance\Elements\Element' ) ) {
				continue;
			}
			$row = array( 'type' => $class );
			if ( $item && sanitize_key( $item ) === sanitize_key( $class ) ) {
				$row['class'] = $class;
			}
			$items[] = $row;
		}
		return array(
			'builder' => 'breakdance',
			'count'   => count( $items ),
			'items'   => $items,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function read( int $id ): array {
		$raw = get_post_meta( $id, '_breakdance_data', true );
		if ( function_exists( '\Breakdance\Data\get_meta' ) ) {
			$raw = \Breakdance\Data\get_meta( $id, $this->data_key() );
		}
		return array(
			'data' => $raw,
		);
	}

	/**
	 * @param array<string, mixed> $args Save args.
	 * @return array<string, mixed>
	 */
	public function normalize_write( array $args ): array {
		return array(
			'tree' => WPAgent_Builder_Payload::breakdance_tree( $args['tree'] ?? null, $args['tree_json_string'] ?? null ),
		);
	}

	/**
	 * @param array<string, mixed> $payload Normalized payload.
	 * @param array<string, mixed> $args    Original args.
	 * @param array<int, mixed>    $warnings Warnings.
	 */
	public function write( int $id, array $payload, string $status, string $title, int $created_id, array $args, array &$warnings ): void {
		$post_type = get_post_type( $id ) ?: 'page';
		if ( in_array( $post_type, array( 'product', 'attachment' ), true ) ) {
			throw new InvalidArgumentException( 'Breakdance does not open the product or attachment post types.' );
		}

		if ( ! function_exists( '\Breakdance\Permissions\hasMinimumPermission' )
			|| ! \Breakdance\Permissions\hasMinimumPermission( 'edit' ) ) {
			throw new RuntimeException( 'This account does not have Breakdance builder edit permission.' );
		}

		if ( ! function_exists( '\Breakdance\Data\set_meta' ) ) {
			throw new RuntimeException( 'Breakdance Data\\set_meta is not available.' );
		}

		$template = isset( $args['template'] ) ? sanitize_key( (string) $args['template'] ) : '';
		if ( 'blank_canvas' === $template ) {
			update_post_meta( $id, '_wp_page_template', 'breakdance_blank_canvas' );
		} elseif ( 'theme' === $template ) {
			delete_post_meta( $id, '_wp_page_template' );
		}

		\Breakdance\Data\set_meta(
			$id,
			$this->data_key(),
			array(
				'tree_json_string' => wp_json_encode( $payload['tree'] ),
			)
		);

		$update = array(
			'ID'          => $id,
			'post_status' => $status,
		);
		if ( 0 === $created_id && '' !== $title ) {
			$update['post_title'] = $title;
		}
		wp_update_post( $update );

		if ( function_exists( '\Breakdance\Render\generateCacheForPost' ) ) {
			$cache = \Breakdance\Render\generateCacheForPost( $id );
			if ( array() === $cache ) {
				$warnings[] = array(
					'code'    => 'breakdance_empty_cache',
					'message' => 'Breakdance generated no CSS for this save. Check the front-end URL.',
				);
			}
		}

		do_action( 'breakdance_after_save_document', $id );
	}
}
