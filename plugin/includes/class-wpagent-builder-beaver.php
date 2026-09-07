<?php
/**
 * Beaver Builder save path: FLBuilderModel layout draft + save_layout().
 *
 * @package WPAgent
 */

class WPAgent_Builder_Beaver {

	public function active(): bool {
		return class_exists( 'FLBuilderModel' );
	}

	public function version(): ?string {
		return defined( 'FL_BUILDER_VERSION' ) ? (string) FL_BUILDER_VERSION : null;
	}

	public function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'fl_builder' => '',
			),
			get_permalink( $id ) ? get_permalink( $id ) : admin_url( 'post.php?post=' . $id . '&action=edit' )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function catalog( ?string $item ): array {
		$slugs = array();
		if ( is_callable( array( 'FLBuilderModel', 'get_enabled_modules' ) ) ) {
			$slugs = FLBuilderModel::get_enabled_modules();
		}
		$items = array();
		foreach ( (array) $slugs as $slug ) {
			$row = array( 'slug' => (string) $slug );
			if ( $item && $item === $slug && class_exists( 'FLBuilderModel' ) && is_callable( array( 'FLBuilderModel', 'get_module' ) ) ) {
				$row['module'] = FLBuilderModel::get_module( $slug );
			}
			$items[] = $row;
		}
		return array(
			'builder' => 'beaver',
			'count'   => count( $items ),
			'items'   => $items,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function read( int $id ): array {
		$data = array();
		if ( is_callable( array( 'FLBuilderModel', 'get_layout_data' ) ) ) {
			$data = FLBuilderModel::get_layout_data( 'published', $id );
		}
		return array(
			'enabled' => get_post_meta( $id, '_fl_builder_enabled', true ),
			'data'    => $data,
		);
	}

	/**
	 * @param array<string, mixed> $args Save args.
	 * @return array<string, mixed>
	 */
	public function normalize_write( array $args ): array {
		return array(
			'data' => WPAgent_Builder_Payload::beaver_nodes( $args['data'] ?? null ),
		);
	}

	/**
	 * @param array<string, mixed> $payload Normalized payload.
	 * @param array<string, mixed> $args    Original args.
	 * @param array<int, mixed>    $warnings Warnings.
	 */
	public function write( int $id, array $payload, string $status, string $title, int $created_id, array $args, array &$warnings ): void {
		$post_type = get_post_type( $id ) ?: 'page';
		if ( is_callable( array( 'FLBuilderModel', 'get_post_types' ) ) ) {
			$enabled = FLBuilderModel::get_post_types();
			if ( is_array( $enabled ) && ! in_array( $post_type, $enabled, true ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Beaver Builder is not enabled for post type "%s".', $post_type )
				);
			}
		}

		$nodes = $payload['data'];
		if ( is_callable( array( 'FLBuilderModel', 'user_has_unfiltered_html' ) )
			&& ! FLBuilderModel::user_has_unfiltered_html()
			&& is_callable( array( 'FLBuilderModel', 'verify_settings' ) ) ) {
			foreach ( $nodes as $node_id => $node ) {
				if ( true !== FLBuilderModel::verify_settings( $node->settings ) ) {
					throw new RuntimeException( sprintf( 'Node %s has HTML this account cannot save (unfiltered_html).', (string) $node_id ) );
				}
				foreach ( array( 'bb_js_code', 'code', 'js', 'css' ) as $field ) {
					if ( isset( $node->settings->{$field} ) ) {
						unset( $node->settings->{$field} );
						$warnings[] = array(
							'code'    => 'beaver_code_stripped',
							'message' => sprintf( 'Removed executable field "%s" on node %s.', $field, (string) $node_id ),
						);
					}
				}
			}
		}

		if ( 0 === $created_id && '' !== $title ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $title,
				)
			);
		}

		FLBuilderModel::update_layout_data( $nodes, 'draft', $id );
		FLBuilderModel::set_post_id( $id );
		try {
			FLBuilderModel::save_layout( 'publish' === $status || 'future' === $status || 'private' === $status );
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => $status,
				)
			);
		} finally {
			if ( is_callable( array( 'FLBuilderModel', 'reset_post_id' ) ) ) {
				FLBuilderModel::reset_post_id();
			}
		}
	}
}
