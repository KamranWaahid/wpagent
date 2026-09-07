<?php
/**
 * Media listing and sideload from URL or base64.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Media extends WPAgent_REST_Controller {

	public function register_routes(): void {
		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/media',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_media' ),
					'permission_callback' => WPAgent_Permissions::callback( 'list_media' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload_media' ),
					'permission_callback' => WPAgent_Permissions::callback( 'upload_media' ),
				),
			)
		);

		register_rest_route(
			WPAGENT_REST_NAMESPACE,
			'/media/read-pdf',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'read_pdf' ),
				'permission_callback' => WPAgent_Permissions::callback( 'read_pdf' ),
			)
		);
	}

	public function read_pdf( WP_REST_Request $request ) {
		return $this->execute(
			'read_pdf',
			$request,
			static function () use ( $request ) {
				return WPAgent_Pdf_Reader::read(
					array(
						'id'        => $request->get_param( 'id' ),
						'path'      => $request->get_param( 'path' ),
						'url'       => $request->get_param( 'url' ),
						'max_pages' => $request->get_param( 'max_pages' ),
					)
				);
			}
		);
	}

	public function list_media( WP_REST_Request $request ) {
		return $this->execute(
			'list_media',
			$request,
			function () use ( $request ) {
				$paging = $this->pagination( $request );
				$query  = new WP_Query(
					array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'posts_per_page' => $paging['per_page'],
						'paged'          => $paging['page'],
						's'              => sanitize_text_field( (string) $request->get_param( 'search' ) ),
					)
				);

				return array(
					'total'    => (int) $query->found_posts,
					'page'     => $paging['page'],
					'per_page' => $paging['per_page'],
					'items'    => array_map( array( $this, 'format_attachment' ), $query->posts ),
				);
			}
		);
	}

	public function upload_media( WP_REST_Request $request ) {
		return $this->execute(
			'upload_media',
			$request,
			function () use ( $request ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				$url    = esc_url_raw( (string) $request->get_param( 'url' ) );
				$base64 = (string) $request->get_param( 'base64' );
				$name   = sanitize_file_name( (string) ( $request->get_param( 'filename' ) ?: 'wpagent-upload' ) );
				$title  = sanitize_text_field( (string) $request->get_param( 'title' ) );
				$alt    = sanitize_text_field( (string) $request->get_param( 'alt' ) );
				$attr   = sanitize_text_field( (string) $request->get_param( 'attribution' ) );

				if ( $url ) {
					$tmp = download_url( $url, 30 );
					if ( is_wp_error( $tmp ) ) {
						throw new RuntimeException( $tmp->get_error_message() );
					}
					$path_name = $name;
					if ( ! pathinfo( $path_name, PATHINFO_EXTENSION ) ) {
						$path     = wp_parse_url( $url, PHP_URL_PATH );
						$basename = $path ? basename( $path ) : '';
						$path_name = $basename ?: $path_name . '.jpg';
					}
					$file_array = array(
						'name'     => sanitize_file_name( $path_name ),
						'tmp_name' => $tmp,
					);
				} elseif ( $base64 ) {
					if ( preg_match( '/^data:([^;]+);base64,(.+)$/', $base64, $m ) ) {
						$base64 = $m[2];
					}
					$bytes = base64_decode( $base64, true );
					if ( false === $bytes ) {
						throw new InvalidArgumentException( 'Invalid base64 payload.' );
					}
					if ( strlen( $bytes ) > 15 * 1024 * 1024 ) {
						throw new InvalidArgumentException( 'Upload exceeds 15MB limit.' );
					}
					$tmp = wp_tempnam( $name );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					file_put_contents( $tmp, $bytes );
					$file_array = array(
						'name'     => $name,
						'tmp_name' => $tmp,
					);
				} else {
					throw new InvalidArgumentException( 'Provide url or base64.' );
				}

				$id = media_handle_sideload( $file_array, 0, $title ?: null );
				if ( is_wp_error( $id ) ) {
					@unlink( $file_array['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					throw new RuntimeException( $id->get_error_message() );
				}

				if ( $alt ) {
					update_post_meta( $id, '_wp_attachment_image_alt', $alt );
				}
				if ( $attr ) {
					update_post_meta( $id, '_wpagent_attribution', $attr );
					$excerpt = get_post_field( 'post_excerpt', $id );
					if ( ! $excerpt ) {
						wp_update_post(
							array(
								'ID'           => $id,
								'post_excerpt' => $attr,
							)
						);
					}
				}

				$post = get_post( $id );
				return $this->format_attachment( $post );
			},
			array( 'object_type' => 'attachment' )
		);
	}

	/**
	 * @param WP_Post $post Attachment.
	 * @return array<string, mixed>
	 */
	private function format_attachment( WP_Post $post ): array {
		return array(
			'id'           => (int) $post->ID,
			'title'        => $post->post_title,
			'caption'      => $post->post_excerpt,
			'alt'          => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'mime'         => $post->post_mime_type,
			'url'          => wp_get_attachment_url( $post->ID ),
			'date'         => $post->post_date_gmt,
			'attribution'  => (string) get_post_meta( $post->ID, '_wpagent_attribution', true ),
		);
	}
}
