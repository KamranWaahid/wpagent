<?php
/**
 * Page-builder detection and save dispatch.
 *
 * Writes go through each builder's own APIs (or the documented save composition
 * when they expose no writer). Raw post-meta dumps are not a supported path.
 *
 * @package WPAgent
 */

class WPAgent_Builders {

	public const SLUGS = array( 'elementor', 'bricks', 'beaver', 'breakdance', 'divi' );

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function catalog_meta(): array {
		return array(
			'elementor'   => array(
				'label'   => 'Elementor',
				'payload' => 'elements (list of root Elementor nodes)',
			),
			'bricks'      => array(
				'label'   => 'Bricks',
				'payload' => 'elements (flat {id, name, parent, children, settings})',
			),
			'beaver'      => array(
				'label'   => 'Beaver Builder',
				'payload' => 'data (node_id => {type, parent, settings})',
			),
			'breakdance'  => array(
				'label'   => 'Breakdance',
				'payload' => 'tree or tree_json_string',
			),
			'divi'        => array(
				'label'   => 'Divi',
				'payload' => 'content ([et_pb_section] shortcodes)',
			),
		);
	}

	public static function normalize( string $slug ): string {
		$slug = sanitize_key( $slug );
		if ( ! in_array( $slug, self::SLUGS, true ) ) {
			throw new InvalidArgumentException( 'Unknown builder. Use elementor, bricks, beaver, breakdance, or divi.' );
		}
		return $slug;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function inventory(): array {
		$items = array();
		foreach ( self::SLUGS as $slug ) {
			$adapter = self::adapter( $slug );
			$meta    = self::catalog_meta()[ $slug ];
			$items[] = array(
				'slug'    => $slug,
				'label'   => $meta['label'],
				'active'  => $adapter->active(),
				'version' => $adapter->version(),
				'payload' => $meta['payload'],
			);
		}
		return array(
			'items'  => $items,
			'active' => array_values(
				array_map(
					static fn( array $row ) => $row['slug'],
					array_filter( $items, static fn( array $row ) => ! empty( $row['active'] ) )
				)
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function catalog( string $slug, ?string $item = null ): array {
		$slug    = self::normalize( $slug );
		$adapter = self::require_active( $slug );
		return $adapter->catalog( $item ? sanitize_key( $item ) : null );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_page( string $slug, int $id ): array {
		$slug    = self::normalize( $slug );
		$adapter = self::require_active( $slug );
		$post    = self::require_post( $id );
		self::assert_can_edit( $post );
		$layout = $adapter->read( $id );
		return array(
			'builder'  => $slug,
			'id'       => $id,
			'type'     => $post->post_type,
			'status'   => $post->post_status,
			'title'    => $post->post_title,
			'url'      => get_permalink( $id ),
			'edit_url' => $adapter->edit_url( $id ),
			'layout'   => $layout,
		);
	}

	/**
	 * @param array<string, mixed> $args Save arguments.
	 * @return array<string, mixed>
	 */
	public static function save( string $slug, array $args ): array {
		$slug     = self::normalize( $slug );
		$adapter  = self::require_active( $slug );
		$explicit = ! empty( $args['explicit_publish'] );
		$status   = WPAgent_Status_Policy::resolve(
			isset( $args['status'] ) ? (string) $args['status'] : null,
			$explicit
		);
		$post_type = sanitize_key( (string) ( $args['post_type'] ?? 'page' ) );
		if ( '' === $post_type ) {
			$post_type = 'page';
		}
		$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$id    = isset( $args['id'] ) ? absint( $args['id'] ) : 0;

		if ( ! empty( $args['widget_id'] ) && $id > 0 && ! isset( $args['status'] ) ) {
			$existing = get_post( $id );
			if ( $existing ) {
				$status = $existing->post_status;
			}
		}

		if ( ! empty( $args['widget_id'] ) ) {
			if ( 'elementor' !== $slug ) {
				throw new InvalidArgumentException( 'widget_id patches are only supported for Elementor.' );
			}
			if ( $id <= 0 ) {
				throw new InvalidArgumentException( 'widget_id patch requires an existing page id.' );
			}
		}

		$payload = $adapter->normalize_write( $args );
		self::assert_post_caps( $post_type, $id, $status );

		$created_id = 0;
		if ( $id > 0 ) {
			$post = self::require_post( $id );
			self::assert_can_edit( $post );
		} else {
			if ( '' === $title ) {
				throw new InvalidArgumentException( 'title is required when creating a builder page.' );
			}
			$insert = wp_insert_post(
				array(
					'post_type'   => $post_type,
					'post_status' => 'draft',
					'post_title'  => $title,
				),
				true
			);
			if ( is_wp_error( $insert ) ) {
				throw new RuntimeException( $insert->get_error_message() );
			}
			$id         = (int) $insert;
			$created_id = $id;
		}

		$warnings = array();
		try {
			$adapter->write( $id, $payload, $status, $title, $created_id, $args, $warnings );
		} catch ( Throwable $e ) {
			if ( $created_id > 0 && function_exists( 'wp_delete_post' ) ) {
				wp_delete_post( $created_id, true );
			}
			throw $e;
		}

		$post = get_post( $id );
		$out  = array(
			'builder'      => $slug,
			'id'           => $id,
			'status'       => $post ? $post->post_status : $status,
			'title'        => $post ? $post->post_title : $title,
			'url'          => get_permalink( $id ),
			'edit_url'     => $adapter->edit_url( $id ),
			'draft_forced' => WPAgent_Status_Policy::is_public( (string) ( $args['status'] ?? '' ) ) && ! $explicit && 'draft' === $status,
			'warnings'     => $warnings,
		);
		if ( ! empty( $args['widget_id'] ) ) {
			$out['patch'] = array(
				'widget_id' => (string) $args['widget_id'],
				'merged'    => true,
			);
		}
		return $out;
	}

	public static function adapter( string $slug ): object {
		return match ( $slug ) {
			'elementor'  => new WPAgent_Builder_Elementor(),
			'bricks'     => new WPAgent_Builder_Bricks(),
			'beaver'     => new WPAgent_Builder_Beaver(),
			'breakdance' => new WPAgent_Builder_Breakdance(),
			'divi'       => new WPAgent_Builder_Divi(),
			default      => throw new InvalidArgumentException( 'Unknown builder.' ),
		};
	}

	private static function require_active( string $slug ): object {
		$adapter = self::adapter( $slug );
		if ( ! $adapter->active() ) {
			$label = self::catalog_meta()[ $slug ]['label'];
			throw new RuntimeException( sprintf( '%s is not active on this site.', $label ) );
		}
		return $adapter;
	}

	/**
	 * @return WP_Post
	 */
	public static function require_post( int $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			throw new InvalidArgumentException( sprintf( 'Post #%d does not exist.', $id ) );
		}
		return $post;
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function assert_can_edit( $post ): void {
		if ( ! current_user_can( 'edit_post', (int) $post->ID ) ) {
			throw new RuntimeException( sprintf( 'You cannot edit post #%d.', (int) $post->ID ) );
		}
	}

	public static function assert_post_caps( string $post_type, int $id, string $status ): void {
		$pto = get_post_type_object( $post_type );
		if ( ! $pto ) {
			throw new InvalidArgumentException( sprintf( 'Unknown post type: %s', $post_type ) );
		}
		if ( $id > 0 ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				throw new RuntimeException( sprintf( 'You cannot edit post #%d.', $id ) );
			}
		} elseif ( ! current_user_can( $pto->cap->create_posts ) ) {
			throw new RuntimeException( sprintf( 'You cannot create %s.', $post_type ) );
		}
		if ( in_array( $status, array( 'publish', 'private', 'future' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
			throw new RuntimeException( sprintf( 'You cannot publish %s.', $post_type ) );
		}
	}

	/**
	 * @param callable $fn Step.
	 * @param array<int, array<string, string>> $warnings Warnings.
	 */
	public static function warn_step( array &$warnings, string $code, callable $fn ): void {
		try {
			$fn();
		} catch ( Throwable $e ) {
			$warnings[] = array(
				'code'    => $code,
				'message' => $e->getMessage(),
			);
		}
	}
}
