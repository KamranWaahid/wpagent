<?php
/**
 * Navigation menus via WordPress menu APIs (not raw term/meta writes).
 *
 * @package WPAgent
 */

class WPAgent_Menus {

	/**
	 * @return string[]
	 */
	public static function actions(): array {
		return array( 'create', 'add_item', 'update_item', 'remove_item', 'assign_location' );
	}

	public static function normalize_action( string $action ): string {
		$action = sanitize_key( $action );
		if ( ! in_array( $action, self::actions(), true ) ) {
			throw new InvalidArgumentException( 'action must be create, add_item, update_item, remove_item, or assign_location.' );
		}
		return $action;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function list_all(): array {
		$registered = get_registered_nav_menus();
		$assigned   = get_nav_menu_locations();
		$menus      = wp_get_nav_menus();
		$items      = array();
		foreach ( $menus as $menu ) {
			$items[] = self::summarize_menu( $menu, $assigned, $registered );
		}
		$locations = array();
		foreach ( $registered as $slug => $label ) {
			$locations[] = array(
				'slug'    => $slug,
				'label'   => $label,
				'menu_id' => isset( $assigned[ $slug ] ) ? (int) $assigned[ $slug ] : 0,
			);
		}
		return array(
			'menus'     => $items,
			'locations' => $locations,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_menu( int $id ): array {
		$menu = wp_get_nav_menu_object( $id );
		if ( ! $menu ) {
			throw new InvalidArgumentException( sprintf( 'Menu #%d was not found.', $id ) );
		}
		$assigned = get_nav_menu_locations();
		$out      = self::summarize_menu( $menu, $assigned, get_registered_nav_menus() );
		$raw      = wp_get_nav_menu_items( $id );
		$out['items'] = array();
		foreach ( is_array( $raw ) ? $raw : array() as $item ) {
			$out['items'][] = self::format_item( $item );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $args Action payload.
	 * @return array<string, mixed>
	 */
	public static function manage( array $args ): array {
		$action = self::normalize_action( (string) ( $args['action'] ?? '' ) );
		return match ( $action ) {
			'create'          => self::create( (string) ( $args['name'] ?? '' ) ),
			'add_item'        => self::upsert_item( $args, 0 ),
			'update_item'     => self::upsert_item( $args, isset( $args['item_id'] ) ? (int) $args['item_id'] : 0 ),
			'remove_item'     => self::remove_item( (int) ( $args['item_id'] ?? 0 ) ),
			'assign_location' => self::assign_location( (int) ( $args['menu_id'] ?? 0 ), (string) ( $args['location'] ?? '' ) ),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function delete_menu( int $id ): array {
		$menu = wp_get_nav_menu_object( $id );
		if ( ! $menu ) {
			throw new InvalidArgumentException( sprintf( 'Menu #%d was not found.', $id ) );
		}
		$name   = (string) $menu->name;
		$result = wp_delete_nav_menu( $id );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
		if ( ! $result ) {
			throw new RuntimeException( 'Failed to delete the menu.' );
		}
		return array(
			'deleted' => true,
			'id'      => $id,
			'name'    => $name,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function create( string $name ): array {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			throw new InvalidArgumentException( 'name is required to create a menu.' );
		}
		$id = wp_create_nav_menu( $name );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		return self::get_menu( (int) $id );
	}

	/**
	 * @param array<string, mixed> $args Item fields.
	 * @return array<string, mixed>
	 */
	private static function upsert_item( array $args, int $item_id ): array {
		$menu_id = (int) ( $args['menu_id'] ?? 0 );
		if ( $menu_id <= 0 ) {
			throw new InvalidArgumentException( 'menu_id is required.' );
		}
		if ( ! wp_get_nav_menu_object( $menu_id ) ) {
			throw new InvalidArgumentException( sprintf( 'Menu #%d was not found.', $menu_id ) );
		}
		if ( $item_id > 0 ) {
			$existing = get_post( $item_id );
			if ( ! $existing || 'nav_menu_item' !== $existing->post_type ) {
				throw new InvalidArgumentException( sprintf( 'Menu item #%d was not found.', $item_id ) );
			}
		}

		$type = sanitize_key( (string) ( $args['type'] ?? 'custom' ) );
		$map  = array(
			'custom'   => array( 'custom', 'custom' ),
			'post'     => array( 'post_type', 'post' ),
			'page'     => array( 'post_type', 'page' ),
			'category' => array( 'taxonomy', 'category' ),
		);
		if ( ! isset( $map[ $type ] ) ) {
			throw new InvalidArgumentException( 'type must be custom, post, page, or category.' );
		}
		[ $wp_type, $object ] = $map[ $type ];

		$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$url   = isset( $args['url'] ) ? esc_url_raw( (string) $args['url'] ) : '';
		$oid   = isset( $args['object_id'] ) ? (int) $args['object_id'] : 0;

		if ( 'custom' === $type ) {
			if ( '' === $url ) {
				throw new InvalidArgumentException( 'url is required for a custom menu item.' );
			}
			if ( '' === $title ) {
				$title = $url;
			}
		} else {
			if ( $oid <= 0 ) {
				throw new InvalidArgumentException( 'object_id is required for post, page, or category items.' );
			}
			if ( '' === $title ) {
				$title = 'page' === $type || 'post' === $type
					? (string) get_the_title( $oid )
					: ( ( $term = get_term( $oid ) ) && ! is_wp_error( $term ) ? (string) $term->name : '' );
			}
			if ( '' === $title ) {
				$title = $object . ' #' . $oid;
			}
		}

		$item_data = array(
			'menu-item-title'     => $title,
			'menu-item-status'    => 'publish',
			'menu-item-type'      => $wp_type,
			'menu-item-object'    => $object,
			'menu-item-object-id' => $oid,
			'menu-item-url'       => $url,
			'menu-item-parent-id' => isset( $args['parent_id'] ) ? (int) $args['parent_id'] : 0,
			'menu-item-position'  => isset( $args['position'] ) ? (int) $args['position'] : 0,
		);

		$saved = wp_update_nav_menu_item( $menu_id, $item_id, $item_data );
		if ( is_wp_error( $saved ) ) {
			throw new RuntimeException( $saved->get_error_message() );
		}
		return array(
			'menu_id' => $menu_id,
			'item_id' => (int) $saved,
			'item'    => self::format_item( get_post( (int) $saved ) ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function remove_item( int $item_id ): array {
		if ( $item_id <= 0 ) {
			throw new InvalidArgumentException( 'item_id is required.' );
		}
		$item = get_post( $item_id );
		if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
			throw new InvalidArgumentException( sprintf( 'Menu item #%d was not found.', $item_id ) );
		}
		$ok = wp_delete_post( $item_id, true );
		if ( ! $ok ) {
			throw new RuntimeException( 'Failed to remove the menu item.' );
		}
		return array(
			'removed' => true,
			'item_id' => $item_id,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function assign_location( int $menu_id, string $location ): array {
		$location = sanitize_key( $location );
		if ( $menu_id <= 0 || '' === $location ) {
			throw new InvalidArgumentException( 'menu_id and location are required.' );
		}
		if ( ! wp_get_nav_menu_object( $menu_id ) ) {
			throw new InvalidArgumentException( sprintf( 'Menu #%d was not found.', $menu_id ) );
		}
		$registered = get_registered_nav_menus();
		if ( ! isset( $registered[ $location ] ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Unknown menu location "%s". Known: %s', $location, implode( ', ', array_keys( $registered ) ) )
			);
		}
		$locs              = get_nav_menu_locations();
		$locs[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locs );
		return array(
			'menu_id'  => $menu_id,
			'location' => $location,
			'assigned' => true,
		);
	}

	/**
	 * @param WP_Term              $menu      Menu term.
	 * @param array<string, int>   $assigned  location => menu id.
	 * @param array<string, string> $registered Locations.
	 * @return array<string, mixed>
	 */
	private static function summarize_menu( $menu, array $assigned, array $registered ): array {
		$locs = array();
		foreach ( $assigned as $slug => $id ) {
			if ( (int) $id === (int) $menu->term_id && isset( $registered[ $slug ] ) ) {
				$locs[] = $slug;
			}
		}
		return array(
			'id'        => (int) $menu->term_id,
			'name'      => (string) $menu->name,
			'slug'      => (string) $menu->slug,
			'count'     => (int) $menu->count,
			'locations' => $locs,
		);
	}

	/**
	 * @param WP_Post|object|null $item Menu item.
	 * @return array<string, mixed>|null
	 */
	private static function format_item( $item ): ?array {
		if ( ! $item ) {
			return null;
		}
		return array(
			'id'        => (int) $item->ID,
			'title'     => (string) ( $item->title ?? $item->post_title ?? '' ),
			'type'      => (string) ( $item->type ?? '' ),
			'object'    => (string) ( $item->object ?? '' ),
			'object_id' => (int) ( $item->object_id ?? 0 ),
			'url'       => (string) ( $item->url ?? '' ),
			'parent'    => (int) ( $item->menu_item_parent ?? 0 ),
			'position'  => (int) ( $item->menu_order ?? 0 ),
		);
	}
}
