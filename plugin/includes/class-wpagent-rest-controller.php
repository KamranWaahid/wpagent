<?php
/**
 * Shared REST helpers: allowlist re-check, audit, confirm gates, JSON errors.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Controller {

	/**
	 * Run a command: re-check capabilities, optionally audit, map throwables to WP_Error.
	 *
	 * @param string           $command Allowlisted command name.
	 * @param WP_REST_Request  $request Request.
	 * @param callable         $handler Callback returning data or WP_Error.
	 * @param array            $audit   Optional audit context (object_type, object_id, before_state).
	 * @return WP_REST_Response|WP_Error
	 */
	protected function execute( string $command, WP_REST_Request $request, callable $handler, array $audit = array() ) {
		$denied = WPAgent_Permissions::deny_if_unauthorized( $command );
		if ( $denied ) {
			return $denied;
		}

		$def = WPAgent_Allowlist::get( $command );
		if ( $def && ! empty( $def['requires_confirm'] ) && empty( $def['dry_run_default'] ) ) {
			$confirm_error = WPAgent_Permissions::require_confirm( $request );
			if ( $confirm_error ) {
				return $confirm_error;
			}
		}

		$request_id = wp_generate_uuid4();

		try {
			$result = $handler();
		} catch ( InvalidArgumentException $e ) {
			$data = array(
				'status' => 400,
				'code'   => 'validation',
			);
			if ( $e instanceof WPAgent_Validation_Exception ) {
				$data = array_merge( $data, $e->details );
			}
			return new WP_Error(
				'wpagent_validation',
				$e->getMessage(),
				$data
			);
		} catch ( Throwable $e ) {
			if ( ! empty( $def['write'] ) ) {
				WPAgent_Audit::log(
					array(
						'request_id'    => $request_id,
						'command'       => $command,
						'capability'    => $def['capability'] ?? '',
						'object_type'   => $audit['object_type'] ?? '',
						'object_id'     => $audit['object_id'] ?? 0,
						'before_state'  => $audit['before_state'] ?? null,
						'result'        => 'error',
						'error_message' => $e->getMessage(),
					)
				);
			}

			return new WP_Error(
				'wpagent_error',
				__( 'The command failed. See the audit log for details.', 'wpagent' ),
				array(
					'status'  => 500,
					'code'    => 'error',
					'message' => $e->getMessage(),
				)
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $def && ! empty( $def['write'] ) ) {
			$after = $audit['after_state'] ?? $result;
			WPAgent_Audit::log(
				array(
					'request_id'   => $request_id,
					'command'      => $command,
					'capability'   => $def['capability'] ?? '',
					'object_type'  => $audit['object_type'] ?? '',
					'object_id'    => $audit['object_id'] ?? ( is_array( $result ) ? (int) ( $result['id'] ?? 0 ) : 0 ),
					'before_state' => $audit['before_state'] ?? null,
					'after_state'  => $after,
					'result'       => 'success',
				)
			);
			if ( is_array( $result ) && WPAgent_Page_Cache::should_flush_after( $command ) ) {
				$result['page_cache'] = WPAgent_Page_Cache::flush();
			}
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Snapshot a post for the audit log.
	 *
	 * @param WP_Post|null $post Post.
	 * @return array<string, mixed>|null
	 */
	protected function snapshot_post( ?WP_Post $post ): ?array {
		if ( ! $post ) {
			return null;
		}

		return array(
			'id'      => (int) $post->ID,
			'type'    => $post->post_type,
			'status'  => $post->post_status,
			'title'   => $post->post_title,
			'slug'    => $post->post_name,
			'excerpt' => $post->post_excerpt,
			'content' => $post->post_content,
		);
	}

	/**
	 * Normalize a WP_Post for API output.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	protected function format_post( WP_Post $post ): array {
		$out = array(
			'id'        => (int) $post->ID,
			'type'      => $post->post_type,
			'status'    => $post->post_status,
			'title'     => get_the_title( $post ),
			'slug'      => $post->post_name,
			'excerpt'   => wp_strip_all_tags( $post->post_excerpt ),
			'content'   => $post->post_content,
			'date'      => $post->post_date_gmt,
			'modified'  => $post->post_modified_gmt,
			'author'    => (int) $post->post_author,
			'permalink' => get_permalink( $post ),
			'parent'    => (int) $post->post_parent,
			'featured_media' => (int) get_post_thumbnail_id( $post ),
		);

		if ( get_post_meta( $post->ID, '_elementor_data', true ) ) {
			$out['builder'] = 'elementor';
			$out['warning'] = 'content may be a stale Elementor widget dump. Use inspect_rendered_html for storefront copy.';
		}

		return $out;
	}

	/**
	 * Collect pagination args.
	 *
	 * @return array{page:int,per_page:int}
	 */
	protected function pagination( WP_REST_Request $request, int $default_per_page = 10 ): array {
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = (int) ( $request->get_param( 'per_page' ) ?: $default_per_page );
		$per_page = min( 100, max( 1, $per_page ) );

		return array(
			'page'     => $page,
			'per_page' => $per_page,
		);
	}
}
