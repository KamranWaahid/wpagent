<?php
/**
 * Elementor save path: Documents_Manager + Document::save().
 *
 * @package WPAgent
 */

class WPAgent_Builder_Elementor {

	public function active(): bool {
		return class_exists( '\Elementor\Plugin' );
	}

	public function version(): ?string {
		return defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : null;
	}

	public function edit_url( int $id ): string {
		return admin_url( 'post.php?post=' . $id . '&action=elementor' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function catalog( ?string $item ): array {
		$manager = \Elementor\Plugin::$instance->widgets_manager;
		$types   = method_exists( $manager, 'get_widget_types' ) ? $manager->get_widget_types() : array();
		$items   = array();
		foreach ( $types as $slug => $widget ) {
			$row = array(
				'slug'  => (string) $slug,
				'title' => method_exists( $widget, 'get_title' ) ? (string) $widget->get_title() : (string) $slug,
			);
			if ( $item && $item === $slug && method_exists( $widget, 'get_controls' ) ) {
				$row['controls'] = $widget->get_controls();
			}
			$items[] = $row;
		}
		return array(
			'builder' => 'elementor',
			'count'   => count( $items ),
			'items'   => $items,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function read( int $id ): array {
		$document = \Elementor\Plugin::$instance->documents->get( $id, false );
		$elements = array();
		if ( $document && method_exists( $document, 'get_elements_data' ) ) {
			$elements = $document->get_elements_data();
		}
		return array(
			'edit_mode'     => get_post_meta( $id, '_elementor_edit_mode', true ),
			'template_type' => get_post_meta( $id, '_elementor_template_type', true ),
			'elements'      => is_array( $elements ) ? $elements : array(),
		);
	}

	/**
	 * @param array<string, mixed> $args Save args.
	 * @return array<string, mixed>
	 */
	public function normalize_write( array $args ): array {
		$widget_id = isset( $args['widget_id'] ) ? trim( (string) $args['widget_id'] ) : '';
		if ( '' !== $widget_id ) {
			$settings = $args['settings'] ?? null;
			if ( ! is_array( $settings ) ) {
				throw new InvalidArgumentException( 'widget_id patch needs a `settings` object to merge.' );
			}
			return array(
				'mode'      => 'patch',
				'widget_id' => $widget_id,
				'settings'  => $settings,
			);
		}
		return array(
			'mode'     => 'replace',
			'elements' => WPAgent_Builder_Payload::elementor_elements( $args['elements'] ?? null ),
		);
	}

	/**
	 * @param array<string, mixed> $payload Normalized payload.
	 * @param array<string, mixed> $args    Original args.
	 * @param array<int, mixed>    $warnings Warnings.
	 */
	public function write( int $id, array $payload, string $status, string $title, int $created_id, array $args, array &$warnings ): void {
		$template_type = sanitize_key( (string) ( $args['template_type'] ?? 'wp-page' ) );
		if ( '' === $template_type ) {
			$template_type = 'wp-page';
		}

		$update = array( 'ID' => $id, 'post_status' => $status );
		if ( '' !== $title ) {
			$update['post_title'] = $title;
		}
		wp_update_post( $update );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', $template_type );
		$document = \Elementor\Plugin::$instance->documents->get( $id, false );

		if ( ! $document || ! method_exists( $document, 'save' ) ) {
			throw new RuntimeException( 'Could not initialize an Elementor document for this post.' );
		}

		$elements = $payload['elements'] ?? array();
		if ( 'patch' === ( $payload['mode'] ?? '' ) ) {
			$current  = $this->read( $id );
			$elements = WPAgent_Builder_Payload::patch_elementor_widget(
				is_array( $current['elements'] ?? null ) ? $current['elements'] : array(),
				(string) $payload['widget_id'],
				is_array( $payload['settings'] ?? null ) ? $payload['settings'] : array()
			);
		}

		$document->save( array( 'elements' => $elements ) );

		$page_template = isset( $args['page_template'] ) ? sanitize_text_field( (string) $args['page_template'] ) : '';
		if ( '' !== $page_template ) {
			update_post_meta( $id, '_wp_page_template', $page_template );
		}

		WPAgent_Builders::warn_step(
			$warnings,
			'css_regen_failed',
			static function () use ( $id ) {
				if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
					\Elementor\Core\Files\CSS\Post::create( $id )->update();
				}
			}
		);
	}
}
