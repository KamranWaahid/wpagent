<?php
/**
 * Posts, pages, search, taxonomy, comments.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Content extends WPAgent_REST_Controller {

	/**
	 * Register content routes.
	 */
	public function register_routes(): void {
		$ns = WPAGENT_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/posts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_posts' ),
					'permission_callback' => WPAgent_Permissions::callback( 'list_posts' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_post' ),
					'permission_callback' => WPAgent_Permissions::callback( 'create_post' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/posts/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_post' ),
					'permission_callback' => WPAgent_Permissions::callback( 'get_post' ),
				),
				array(
					'methods'             => 'POST,PATCH,PUT',
					'callback'            => array( $this, 'update_post' ),
					'permission_callback' => WPAgent_Permissions::callback( 'update_post' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_post' ),
					'permission_callback' => WPAgent_Permissions::callback( 'delete_post' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/posts/(?P<id>\d+)/permanent-delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'permanent_delete_post' ),
				'permission_callback' => WPAgent_Permissions::callback( 'permanent_delete_post' ),
			)
		);

		register_rest_route(
			$ns,
			'/pages',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_pages' ),
					'permission_callback' => WPAgent_Permissions::callback( 'list_pages' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_page' ),
					'permission_callback' => WPAgent_Permissions::callback( 'create_page' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/pages/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_page' ),
					'permission_callback' => WPAgent_Permissions::callback( 'get_page' ),
				),
				array(
					'methods'             => 'POST,PATCH,PUT',
					'callback'            => array( $this, 'update_page' ),
					'permission_callback' => WPAgent_Permissions::callback( 'update_page' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_page' ),
					'permission_callback' => WPAgent_Permissions::callback( 'delete_page' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/pages/(?P<id>\d+)/permanent-delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'permanent_delete_page' ),
				'permission_callback' => WPAgent_Permissions::callback( 'permanent_delete_page' ),
			)
		);

		register_rest_route(
			$ns,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_content' ),
				'permission_callback' => WPAgent_Permissions::callback( 'search_content' ),
			)
		);

		register_rest_route(
			$ns,
			'/taxonomy',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'taxonomy' ),
					'permission_callback' => WPAgent_Permissions::callback( 'manage_taxonomy' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'taxonomy' ),
					'permission_callback' => WPAgent_Permissions::callback( 'manage_taxonomy' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/comments',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'comments' ),
					'permission_callback' => WPAgent_Permissions::callback( 'manage_comments' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'comments' ),
					'permission_callback' => WPAgent_Permissions::callback( 'manage_comments' ),
				),
			)
		);
	}

	public function list_posts( WP_REST_Request $request ) {
		return $this->execute( 'list_posts', $request, fn() => $this->query_posts( $request, 'post' ) );
	}

	public function get_post( WP_REST_Request $request ) {
		return $this->execute( 'get_post', $request, fn() => $this->read_post( $request, 'post' ) );
	}

	public function create_post( WP_REST_Request $request ) {
		return $this->execute(
			'create_post',
			$request,
			fn() => $this->write_post( $request, 'post', true ),
			array( 'object_type' => 'post' )
		);
	}

	public function update_post( WP_REST_Request $request ) {
		$before = $this->snapshot_post( get_post( (int) $request['id'] ) );
		return $this->execute(
			'update_post',
			$request,
			fn() => $this->write_post( $request, 'post', false ),
			array(
				'object_type'  => 'post',
				'object_id'    => (int) $request['id'],
				'before_state' => $before,
			)
		);
	}

	public function delete_post( WP_REST_Request $request ) {
		$before = $this->snapshot_post( get_post( (int) $request['id'] ) );
		return $this->execute(
			'delete_post',
			$request,
			fn() => $this->trash_post( $request, 'post' ),
			array(
				'object_type'  => 'post',
				'object_id'    => (int) $request['id'],
				'before_state' => $before,
			)
		);
	}

	public function permanent_delete_post( WP_REST_Request $request ) {
		$before = $this->snapshot_post( get_post( (int) $request['id'] ) );
		return $this->execute(
			'permanent_delete_post',
			$request,
			fn() => $this->permanent_delete( $request, 'post' ),
			array(
				'object_type'  => 'post',
				'object_id'    => (int) $request['id'],
				'before_state' => $before,
			)
		);
	}

	public function list_pages( WP_REST_Request $request ) {
		return $this->execute( 'list_pages', $request, fn() => $this->query_posts( $request, 'page' ) );
	}

	public function get_page( WP_REST_Request $request ) {
		return $this->execute( 'get_page', $request, fn() => $this->read_post( $request, 'page' ) );
	}

	public function create_page( WP_REST_Request $request ) {
		return $this->execute(
			'create_page',
			$request,
			fn() => $this->write_post( $request, 'page', true ),
			array( 'object_type' => 'page' )
		);
	}

	public function update_page( WP_REST_Request $request ) {
		$before = $this->snapshot_post( get_post( (int) $request['id'] ) );
		return $this->execute(
			'update_page',
			$request,
			fn() => $this->write_post( $request, 'page', false ),
			array(
				'object_type'  => 'page',
				'object_id'    => (int) $request['id'],
				'before_state' => $before,
			)
		);
	}

	public function delete_page( WP_REST_Request $request ) {
		$before = $this->snapshot_post( get_post( (int) $request['id'] ) );
		return $this->execute(
			'delete_page',
			$request,
			fn() => $this->trash_post( $request, 'page' ),
			array(
				'object_type'  => 'page',
				'object_id'    => (int) $request['id'],
				'before_state' => $before,
			)
		);
	}

	public function permanent_delete_page( WP_REST_Request $request ) {
		$before = $this->snapshot_post( get_post( (int) $request['id'] ) );
		return $this->execute(
			'permanent_delete_page',
			$request,
			fn() => $this->permanent_delete( $request, 'page' ),
			array(
				'object_type'  => 'page',
				'object_id'    => (int) $request['id'],
				'before_state' => $before,
			)
		);
	}

	public function search_content( WP_REST_Request $request ) {
		return $this->execute(
			'search_content',
			$request,
			function () use ( $request ) {
				$q = sanitize_text_field( (string) $request->get_param( 'q' ) );
				if ( strlen( $q ) < 2 ) {
					throw new InvalidArgumentException( 'Search query must be at least 2 characters.' );
				}

				$paging = $this->pagination( $request );
				$query  = new WP_Query(
					array(
						's'              => $q,
						'post_type'      => array( 'post', 'page' ),
						'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
						'posts_per_page' => $paging['per_page'],
						'paged'          => $paging['page'],
					)
				);

				return array(
					'q'        => $q,
					'total'    => (int) $query->found_posts,
					'page'     => $paging['page'],
					'per_page' => $paging['per_page'],
					'items'    => array_map( array( $this, 'format_post' ), $query->posts ),
				);
			}
		);
	}

	public function taxonomy( WP_REST_Request $request ) {
		return $this->execute(
			'manage_taxonomy',
			$request,
			function () use ( $request ) {
				$action   = sanitize_key( (string) ( $request->get_param( 'action' ) ?: 'list' ) );
				$taxonomy = sanitize_key( (string) ( $request->get_param( 'taxonomy' ) ?: 'category' ) );

				if ( ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
					throw new InvalidArgumentException( 'taxonomy must be category or post_tag.' );
				}

				if ( 'list' === $action ) {
					$terms = get_terms(
						array(
							'taxonomy'   => $taxonomy,
							'hide_empty' => false,
							'number'     => 200,
						)
					);
					if ( is_wp_error( $terms ) ) {
						return $terms;
					}

					return array(
						'taxonomy' => $taxonomy,
						'items'    => array_map(
							static function ( WP_Term $term ) {
								return array(
									'id'     => (int) $term->term_id,
									'name'   => $term->name,
									'slug'   => $term->slug,
									'count'  => (int) $term->count,
									'parent' => (int) $term->parent,
								);
							},
							$terms
						),
					);
				}

				if ( 'create' === $action ) {
					$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
					if ( '' === $name ) {
						throw new InvalidArgumentException( 'name is required to create a term.' );
					}
					$created = wp_insert_term( $name, $taxonomy, array( 'slug' => sanitize_title( (string) $request->get_param( 'slug' ) ) ) );
					if ( is_wp_error( $created ) ) {
						return $created;
					}
					$term = get_term( (int) $created['term_id'], $taxonomy );
					return array(
						'created'  => true,
						'taxonomy' => $taxonomy,
						'id'       => (int) $created['term_id'],
						'name'     => $term instanceof WP_Term ? $term->name : $name,
						'slug'     => $term instanceof WP_Term ? $term->slug : '',
					);
				}

				if ( 'assign' === $action ) {
					$post_id = (int) $request->get_param( 'post_id' );
					$terms   = $request->get_param( 'terms' );
					if ( $post_id <= 0 ) {
						throw new InvalidArgumentException( 'post_id is required.' );
					}
					if ( ! is_array( $terms ) ) {
						$terms = array_filter( array_map( 'trim', explode( ',', (string) $terms ) ) );
					}
					$set = wp_set_object_terms( $post_id, $terms, $taxonomy, (bool) $request->get_param( 'append' ) );
					if ( is_wp_error( $set ) ) {
						return $set;
					}
					return array(
						'assigned' => true,
						'post_id'  => $post_id,
						'taxonomy' => $taxonomy,
						'terms'    => $set,
					);
				}

				throw new InvalidArgumentException( 'action must be list, create, or assign.' );
			},
			array( 'object_type' => 'taxonomy' )
		);
	}

	public function comments( WP_REST_Request $request ) {
		return $this->execute(
			'manage_comments',
			$request,
			function () use ( $request ) {
				$action = sanitize_key( (string) ( $request->get_param( 'action' ) ?: 'list' ) );

				if ( 'list' === $action ) {
					$paging   = $this->pagination( $request );
					$comments = get_comments(
						array(
							'number'  => $paging['per_page'],
							'offset'  => ( $paging['page'] - 1 ) * $paging['per_page'],
							'status'  => sanitize_key( (string) ( $request->get_param( 'status' ) ?: 'all' ) ),
							'post_id' => (int) $request->get_param( 'post_id' ),
							'count'   => false,
						)
					);

					return array(
						'page'     => $paging['page'],
						'per_page' => $paging['per_page'],
						'items'    => array_map(
							static function ( $comment ) {
								return array(
									'id'           => (int) $comment->comment_ID,
									'post_id'      => (int) $comment->comment_post_ID,
									'author'       => $comment->comment_author,
									'author_email' => $comment->comment_author_email,
									'date'         => $comment->comment_date_gmt,
									'content'      => $comment->comment_content,
									'status'       => wp_get_comment_status( $comment ),
								);
							},
							$comments
						),
					);
				}

				$id = (int) $request->get_param( 'id' );
				if ( $id <= 0 ) {
					throw new InvalidArgumentException( 'id is required for comment moderation.' );
				}

				$comment = get_comment( $id );
				if ( ! $comment ) {
					return new WP_Error( 'wpagent_not_found', __( 'Comment not found.', 'wpagent' ), array( 'status' => 404, 'code' => 'not_found' ) );
				}

				switch ( $action ) {
					case 'approve':
						wp_set_comment_status( $id, 'approve' );
						break;
					case 'spam':
						wp_spam_comment( $id );
						break;
					case 'trash':
					case 'delete':
						wp_trash_comment( $id );
						break;
					default:
						throw new InvalidArgumentException( 'action must be list, approve, spam, trash, or delete (trash).' );
				}

				return array(
					'id'     => $id,
					'action' => $action,
					'status' => wp_get_comment_status( $id ),
				);
			},
			array( 'object_type' => 'comment' )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function query_posts( WP_REST_Request $request, string $type ): array {
		$paging = $this->pagination( $request );
		$status = $request->get_param( 'status' );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );

		if ( is_string( $status ) && str_contains( $status, ',' ) ) {
			$status = explode( ',', $status );
		}
		$post_status = $status
			? array_map( 'sanitize_key', (array) $status )
			: array( 'publish', 'draft', 'pending', 'private', 'future' );

		$query = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => $post_status,
				's'              => $search,
				'posts_per_page' => $paging['per_page'],
				'paged'          => $paging['page'],
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		return array(
			'total'    => (int) $query->found_posts,
			'page'     => $paging['page'],
			'per_page' => $paging['per_page'],
			'items'    => array_map( array( $this, 'format_post' ), $query->posts ),
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function read_post( WP_REST_Request $request, string $type ) {
		$post = $this->require_type( (int) $request['id'], $type );
		return $this->format_post( $post );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function write_post( WP_REST_Request $request, string $type, bool $is_create ): array {
		$requested = $request->get_param( 'status' ) ? strtolower( (string) $request->get_param( 'status' ) ) : null;
		if ( 'trash' === $requested ) {
			if ( $is_create ) {
				throw new InvalidArgumentException( 'Cannot create an item already in trash. Use delete_page or delete_post on an existing item.' );
			}
			return $this->trash_post( $request, $type );
		}

		$explicit = rest_sanitize_boolean( $request->get_param( 'explicit_publish' ) );
		$status   = WPAgent_Status_Policy::resolve(
			$request->get_param( 'status' ) ? (string) $request->get_param( 'status' ) : null,
			(bool) $explicit
		);

		$data = array(
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => sanitize_text_field( (string) ( $request->get_param( 'title' ) ?? '' ) ),
			'post_content' => wp_kses_post( (string) ( $request->get_param( 'content' ) ?? '' ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $request->get_param( 'excerpt' ) ?? '' ) ),
			'post_name'    => sanitize_title( (string) ( $request->get_param( 'slug' ) ?? '' ) ),
		);

		if ( $request->get_param( 'parent' ) ) {
			$data['post_parent'] = (int) $request->get_param( 'parent' );
		}

		if ( $is_create ) {
			if ( '' === $data['post_title'] ) {
				throw new InvalidArgumentException( 'title is required.' );
			}
			$id = wp_insert_post( $data, true );
		} else {
			$id               = (int) $request['id'];
			$this->require_type( $id, $type );
			$data['ID']       = $id;
			unset( $data['post_type'] );
			if ( '' === $data['post_title'] ) {
				unset( $data['post_title'] );
			}
			if ( '' === $data['post_name'] ) {
				unset( $data['post_name'] );
			}
			$id = wp_update_post( $data, true );
		}

		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}

		$categories = $request->get_param( 'categories' );
		$tags       = $request->get_param( 'tags' );
		if ( $categories ) {
			wp_set_post_terms( (int) $id, (array) $categories, 'category' );
		}
		if ( $tags ) {
			wp_set_post_terms( (int) $id, (array) $tags, 'post_tag' );
		}

		if ( $request->get_param( 'featured_media' ) ) {
			set_post_thumbnail( (int) $id, (int) $request->get_param( 'featured_media' ) );
		}

		$post = get_post( (int) $id );
		$out  = $this->format_post( $post );
		$out['draft_forced'] = WPAgent_Status_Policy::is_public( (string) $request->get_param( 'status' ) ) && ! $explicit && 'draft' === $status;
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function trash_post( WP_REST_Request $request, string $type ): array {
		$id   = (int) $request['id'];
		$post = $this->require_type( $id, $type );
		$ok   = wp_trash_post( $id );
		if ( ! $ok ) {
			throw new RuntimeException( 'Failed to move item to trash.' );
		}

		return array(
			'id'     => $id,
			'type'   => $type,
			'status' => 'trash',
			'title'  => $post->post_title,
			'note'   => 'Moved to trash. Permanent deletion requires a separate confirm:true call.',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function permanent_delete( WP_REST_Request $request, string $type ): array {
		$id   = (int) $request['id'];
		$post = $this->require_type( $id, $type );
		$ok   = wp_delete_post( $id, true );
		if ( ! $ok ) {
			throw new RuntimeException( 'Permanent delete failed.' );
		}
		return array(
			'id'      => $id,
			'type'    => $type,
			'deleted' => true,
			'title'   => $post->post_title,
		);
	}

	private function require_type( int $id, string $type ): WP_Post {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== $type ) {
			throw new InvalidArgumentException( WPAgent_Post_Lookup::type_mismatch_message( $id, $type, $post ) );
		}
		return $post;
	}
}
