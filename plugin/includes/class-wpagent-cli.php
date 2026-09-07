<?php
/**
 * Allowlisted WP-CLI-style commands, dispatched in PHP. No shell.
 *
 * @package WPAgent
 */

class WPAgent_Cli {

	/**
	 * @return array<string, array{tier:string,cap:string,description:string}>
	 */
	public static function catalog(): array {
		return array(
			'plugin list'       => array( 'tier' => 'read', 'cap' => 'activate_plugins', 'description' => 'List installed plugins' ),
			'plugin activate'   => array( 'tier' => 'write', 'cap' => 'activate_plugins', 'description' => 'Activate a plugin file (hello.php or akismet/akismet.php)' ),
			'plugin deactivate' => array( 'tier' => 'write', 'cap' => 'activate_plugins', 'description' => 'Deactivate a plugin. Cannot deactivate WPAgent.' ),
			'plugin delete'     => array( 'tier' => 'destructive', 'cap' => 'delete_plugins', 'description' => 'Delete a plugin. Requires an approval ticket. Cannot delete WPAgent.' ),
			'plugin install'    => array( 'tier' => 'destructive', 'cap' => 'install_plugins', 'description' => 'Install a plugin from wordpress.org by slug. Requires an approval ticket. Does not activate.' ),
			'theme list'        => array( 'tier' => 'read', 'cap' => 'switch_themes', 'description' => 'List installed themes' ),
			'theme activate'    => array( 'tier' => 'write', 'cap' => 'switch_themes', 'description' => 'Activate a theme stylesheet. Draft sandboxes cannot be activated this way.' ),
			'option get'        => array( 'tier' => 'read', 'cap' => 'manage_options', 'description' => 'Read an allowlisted option' ),
			'option list'       => array( 'tier' => 'read', 'cap' => 'manage_options', 'description' => 'List allowlisted options' ),
			'option update'     => array( 'tier' => 'write', 'cap' => 'manage_options', 'description' => 'Update an allowlisted writable option' ),
			'user list'         => array( 'tier' => 'read', 'cap' => 'list_users', 'description' => 'List users' ),
			'user delete'       => array( 'tier' => 'destructive', 'cap' => 'delete_users', 'description' => 'Delete a user. Requires --reassign=<id> and an approval ticket.' ),
			'post list'         => array( 'tier' => 'read', 'cap' => 'edit_posts', 'description' => 'List posts or pages (--post_type=page)' ),
			'post delete'       => array( 'tier' => 'write', 'cap' => 'delete_posts', 'description' => 'Move a post or page to trash by id. --force is refused; use permanent_delete_post / permanent_delete_page.' ),
			'post update'       => array( 'tier' => 'write', 'cap' => 'delete_posts', 'description' => 'Currently only --post_status=trash (same as post delete). Other edits use update_post / update_page.' ),
			'db query'          => array( 'tier' => 'read', 'cap' => 'manage_options', 'description' => 'Read-only SELECT. Mutating SQL is refused, not approval-gated.' ),
			'db prefix'         => array( 'tier' => 'read', 'cap' => 'manage_options', 'description' => 'Table prefix' ),
			'db tables'         => array( 'tier' => 'read', 'cap' => 'manage_options', 'description' => 'List tables' ),
			'core version'      => array( 'tier' => 'read', 'cap' => 'manage_options', 'description' => 'WordPress version' ),
			'cache flush'       => array( 'tier' => 'write', 'cap' => 'manage_options', 'description' => 'Flush object cache and known full-page caches' ),
			'rewrite flush'     => array( 'tier' => 'write', 'cap' => 'manage_options', 'description' => 'Flush rewrite rules' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function run( string $line, bool $confirm = false, string $approval_id = '' ): array {
		$parsed = WPAgent_Cli_Parse::parse( $line );
		[ $command, $meta, $positional ] = WPAgent_Cli_Parse::resolve( $parsed['head'], self::catalog() );
		$args  = array_merge( $positional, $parsed['args'] );
		$flags = $parsed['flags'];
		$canon = WPAgent_Cli_Parse::canonical( $command, $args, $flags );

		if ( ! current_user_can( $meta['cap'] ) ) {
			throw new RuntimeException( sprintf( 'Missing capability "%s" for %s.', $meta['cap'], $command ) );
		}

		if ( 'destructive' === $meta['tier'] ) {
			$preview = self::preview( $command, $args, $flags );
			if ( ! $confirm || '' === $approval_id ) {
				return WPAgent_Cli_Approval::issue( $canon, $preview );
			}
			WPAgent_Cli_Approval::consume( $approval_id, $canon );
		}

		$result = self::dispatch( $command, $args, $flags );
		return array(
			'command'   => $command,
			'canonical' => $canon,
			'tier'      => $meta['tier'],
			'result'    => $result,
		);
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function preview( string $command, array $args, array $flags ): array {
		if ( 'plugin delete' === $command ) {
			$file = self::plugin_file( $args, $flags );
			return array(
				'action' => 'delete_plugin',
				'plugin' => $file,
				'note'   => 'The plugin directory will be removed. This cannot be undone from trash.',
			);
		}
		if ( 'plugin install' === $command ) {
			$slug = WPAgent_Plugin_Install::normalize_slug( (string) ( $flags['slug'] ?? $args[0] ?? '' ) );
			return array(
				'action' => 'install_plugin',
				'slug'   => $slug,
				'source' => 'https://wordpress.org/plugins/' . $slug . '/',
				'note'   => 'Downloads the official zip from downloads.wordpress.org. The plugin will not be activated.',
			);
		}
		if ( 'user delete' === $command ) {
			return array(
				'action'   => 'delete_user',
				'user'     => $args[0] ?? '',
				'reassign' => $flags['reassign'] ?? null,
				'note'     => 'The user is permanently removed. Posts can be reassigned with --reassign=<id>.',
			);
		}
		return array( 'action' => $command, 'args' => $args, 'flags' => $flags );
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return mixed
	 */
	private static function dispatch( string $command, array $args, array $flags ) {
		return match ( $command ) {
			'plugin list'       => self::plugin_list( $flags ),
			'plugin activate'   => self::plugin_toggle( $args, $flags, true ),
			'plugin deactivate' => self::plugin_toggle( $args, $flags, false ),
			'plugin delete'     => self::plugin_delete( $args, $flags ),
			'plugin install'    => WPAgent_Plugin_Install::from_slug( (string) ( $flags['slug'] ?? $args[0] ?? '' ) ),
			'theme list'        => self::theme_list(),
			'theme activate'    => self::theme_activate( $args, $flags ),
			'option get'        => self::option_get( $args ),
			'option list'       => self::option_list(),
			'option update'     => self::option_update( $args, $flags ),
			'user list'         => self::user_list( $flags ),
			'user delete'       => self::user_delete( $args, $flags ),
			'post list'         => self::post_list( $flags ),
			'post delete'       => self::post_delete( $args, $flags ),
			'post update'       => self::post_update( $args, $flags ),
			'db query'          => self::db_query( $args, $flags ),
			'db prefix'         => array( 'prefix' => $GLOBALS['wpdb']->prefix ),
			'db tables'         => self::db_tables(),
			'core version'      => array( 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION ),
			'cache flush'       => self::cache_flush(),
			'rewrite flush'     => self::rewrite_flush(),
			default             => throw new InvalidArgumentException( 'Unhandled command.' ),
		};
	}

	/**
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function plugin_list( array $flags ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		$items  = array();
		foreach ( get_plugins() as $file => $data ) {
			$row = array(
				'file'    => $file,
				'name'    => $data['Name'] ?? $file,
				'version' => $data['Version'] ?? '',
				'active'  => in_array( $file, $active, true ),
			);
			if ( isset( $flags['status'] ) ) {
				$want = (string) $flags['status'];
				if ( 'active' === $want && ! $row['active'] ) {
					continue;
				}
				if ( 'inactive' === $want && $row['active'] ) {
					continue;
				}
			}
			$items[] = $row;
		}
		return array( 'items' => $items );
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function plugin_toggle( array $args, array $flags, bool $activate ): array {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$file = self::plugin_file( $args, $flags );
		if ( plugin_basename( WPAGENT_FILE ) === $file && ! $activate ) {
			throw new InvalidArgumentException( 'WPAgent cannot deactivate itself.' );
		}
		if ( $activate ) {
			$result = activate_plugin( $file );
			if ( is_wp_error( $result ) ) {
				throw new RuntimeException( $result->get_error_message() );
			}
		} else {
			deactivate_plugins( $file );
		}
		return array( 'plugin' => $file, 'active' => $activate );
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function plugin_delete( array $args, array $flags ): array {
		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$file = self::plugin_file( $args, $flags );
		if ( plugin_basename( WPAGENT_FILE ) === $file ) {
			throw new InvalidArgumentException( 'WPAgent cannot delete itself.' );
		}
		deactivate_plugins( $file );
		$result = delete_plugins( array( $file ) );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
		return array( 'deleted' => true, 'plugin' => $file );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function theme_list(): array {
		$current = wp_get_theme();
		$items   = array();
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$items[] = array(
				'stylesheet' => $stylesheet,
				'name'       => $theme->get( 'Name' ),
				'active'     => $stylesheet === $current->get_stylesheet(),
				'draft'      => str_ends_with( $stylesheet, WPAgent_Draft_Theme::SUFFIX ),
			);
		}
		return array( 'items' => $items );
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function theme_activate( array $args, array $flags ): array {
		$slug = sanitize_key( (string) ( $flags['stylesheet'] ?? $args[0] ?? '' ) );
		if ( '' === $slug ) {
			throw new InvalidArgumentException( 'theme activate needs a stylesheet slug.' );
		}
		if ( str_ends_with( $slug, WPAgent_Draft_Theme::SUFFIX ) ) {
			throw new InvalidArgumentException( 'Activate a draft with publish_draft_theme, not theme activate.' );
		}
		$theme = wp_get_theme( $slug );
		if ( ! $theme->exists() ) {
			throw new InvalidArgumentException( 'Theme not found: ' . $slug );
		}
		switch_theme( $slug );
		return array( 'stylesheet' => $slug, 'active' => true );
	}

	/**
	 * @param string[] $args
	 * @return array<string, mixed>
	 */
	private static function option_get( array $args ): array {
		$key = sanitize_key( (string) ( $args[0] ?? '' ) );
		if ( ! WPAgent_Options_Allowlist::can_read( $key ) ) {
			throw WPAgent_Options_Allowlist::reject_read( $key );
		}
		return array( 'key' => $key, 'value' => get_option( $key ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function option_list(): array {
		$items = array();
		foreach ( WPAgent_Options_Allowlist::keys() as $key => $meta ) {
			$items[] = array(
				'key'      => $key,
				'writable' => ! empty( $meta['writable'] ),
				'value'    => get_option( $key ),
			);
		}
		return array( 'items' => $items );
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function option_update( array $args, array $flags ): array {
		$key   = sanitize_key( (string) ( $args[0] ?? '' ) );
		$value = $flags['value'] ?? $args[1] ?? null;
		if ( null === $value ) {
			throw new InvalidArgumentException( 'option update needs a key and --value= or a second argument.' );
		}
		if ( ! WPAgent_Options_Allowlist::can_write( $key ) ) {
			throw WPAgent_Options_Allowlist::reject_write( $key );
		}
		update_option( $key, $value );
		return array( 'key' => $key, 'value' => get_option( $key ) );
	}

	/**
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function user_list( array $flags ): array {
		$users = get_users(
			array(
				'number' => min( 50, max( 1, (int) ( $flags['number'] ?? 20 ) ) ),
			)
		);
		$items = array();
		foreach ( $users as $user ) {
			$items[] = array(
				'id'    => (int) $user->ID,
				'login' => $user->user_login,
				'roles' => $user->roles,
			);
		}
		return array( 'items' => $items );
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function user_delete( array $args, array $flags ): array {
		$id = (int) ( $args[0] ?? 0 );
		if ( $id < 1 ) {
			throw new InvalidArgumentException( 'user delete needs a user id.' );
		}
		if ( $id === get_current_user_id() ) {
			throw new InvalidArgumentException( 'Refused: will not delete the connected account.' );
		}
		$reassign = isset( $flags['reassign'] ) ? (int) $flags['reassign'] : 0;
		if ( $reassign < 1 ) {
			throw new InvalidArgumentException( 'user delete requires --reassign=<id> so posts are not orphaned.' );
		}
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		$ok = wp_delete_user( $id, $reassign );
		if ( ! $ok ) {
			throw new RuntimeException( 'User delete failed.' );
		}
		return array( 'deleted' => true, 'user_id' => $id, 'reassign' => $reassign );
	}

	/**
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function post_list( array $flags ): array {
		$query = new WP_Query(
			array(
				'post_type'      => sanitize_key( (string) ( $flags['post_type'] ?? 'post' ) ),
				'post_status'    => sanitize_key( (string) ( $flags['post_status'] ?? 'any' ) ),
				'posts_per_page' => min( 50, max( 1, (int) ( $flags['posts_per_page'] ?? 10 ) ) ),
				'fields'         => 'ids',
			)
		);
		$items = array();
		foreach ( $query->posts as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post ) {
				continue;
			}
			$items[] = array(
				'id'     => (int) $post->ID,
				'title'  => get_the_title( $post ),
				'status' => $post->post_status,
				'type'   => $post->post_type,
			);
		}
		return array( 'items' => $items, 'total' => (int) $query->found_posts );
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function post_delete( array $args, array $flags ): array {
		$id = (int) ( $args[0] ?? $flags['id'] ?? 0 );
		if ( $id < 1 ) {
			throw new InvalidArgumentException( 'post delete needs a numeric id.' );
		}
		if ( ! empty( $flags['force'] ) ) {
			throw new InvalidArgumentException( 'Permanent delete is not available via WP-CLI. Use permanent_delete_post or permanent_delete_page with confirm=true.' );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			throw new InvalidArgumentException( sprintf( 'Post %d was not found.', $id ) );
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			throw new RuntimeException( sprintf( 'Missing capability to delete %s %d.', $post->post_type, $id ) );
		}
		$ok = wp_trash_post( $id );
		if ( ! $ok ) {
			throw new RuntimeException( 'Failed to move item to trash.' );
		}
		return array(
			'id'     => $id,
			'type'   => $post->post_type,
			'status' => 'trash',
			'title'  => $post->post_title,
			'note'   => 'Moved to trash. Permanent deletion requires permanent_delete_post or permanent_delete_page.',
		);
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function post_update( array $args, array $flags ): array {
		$status = sanitize_key( (string) ( $flags['post_status'] ?? '' ) );
		if ( 'trash' !== $status ) {
			throw new InvalidArgumentException( 'post update only supports --post_status=trash. Use update_post or update_page for other edits.' );
		}
		return self::post_delete( $args, array() );
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 * @return array<string, mixed>
	 */
	private static function db_query( array $args, array $flags ): array {
		$sql = (string) ( $flags['sql'] ?? $args[0] ?? '' );
		$validated = WPAgent_SQL_Guard::assert_read_only( $sql, (int) ( $flags['limit'] ?? WPAgent_SQL_Guard::DEFAULT_ROW_LIMIT ) );
		$sql       = WPAgent_SQL_Guard::apply_limit( $validated['sql'], $validated['limit'] );
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( $wpdb->last_error ) {
			throw new InvalidArgumentException( $wpdb->last_error );
		}
		return WPAgent_SQL_Guard::format_result( $rows ?: array(), $validated['limit'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function db_tables(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		return array( 'prefix' => $wpdb->prefix, 'tables' => $tables ?: array() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function cache_flush(): array {
		return WPAgent_Page_Cache::flush();
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function rewrite_flush(): array {
		flush_rewrite_rules();
		return array( 'flushed' => true );
	}

	/**
	 * @param string[]                   $args
	 * @param array<string, string|true> $flags
	 */
	private static function plugin_file( array $args, array $flags ): string {
		$file = (string) ( $flags['plugin'] ?? $args[0] ?? '' );
		if ( '' === $file || ( ! str_contains( $file, '/' ) && ! str_ends_with( $file, '.php' ) ) ) {
			throw new InvalidArgumentException( 'plugin must be a plugin file such as akismet/akismet.php.' );
		}
		return $file;
	}
}
