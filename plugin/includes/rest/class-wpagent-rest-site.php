<?php
/**
 * Site health, plugins, themes, options, search-replace.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Site extends WPAgent_REST_Controller {

	public function register_routes(): void {
		$ns = WPAGENT_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/site/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_site_health' ),
			)
		);

		register_rest_route(
			$ns,
			'/plugins',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_plugins' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_plugins' ),
			)
		);

		register_rest_route(
			$ns,
			'/plugins/install',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'install_plugin' ),
				'permission_callback' => WPAgent_Permissions::callback( 'install_plugin' ),
			)
		);

		register_rest_route(
			$ns,
			'/cache/flush',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'flush_page_cache' ),
				'permission_callback' => WPAgent_Permissions::callback( 'flush_page_cache' ),
			)
		);

		register_rest_route(
			$ns,
			'/plugins/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'activate_plugin' ),
				'permission_callback' => WPAgent_Permissions::callback( 'activate_plugin' ),
			)
		);

		register_rest_route(
			$ns,
			'/plugins/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'deactivate_plugin' ),
				'permission_callback' => WPAgent_Permissions::callback( 'deactivate_plugin' ),
			)
		);

		register_rest_route(
			$ns,
			'/plugins/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_plugin' ),
				'permission_callback' => WPAgent_Permissions::callback( 'update_plugin' ),
			)
		);

		register_rest_route(
			$ns,
			'/themes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_themes' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_themes' ),
			)
		);

		register_rest_route(
			$ns,
			'/themes/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_theme' ),
				'permission_callback' => WPAgent_Permissions::callback( 'update_theme' ),
			)
		);

		register_rest_route(
			$ns,
			'/core/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_core' ),
				'permission_callback' => WPAgent_Permissions::callback( 'update_core' ),
			)
		);

		register_rest_route(
			$ns,
			'/options/(?P<key>[a-zA-Z0-9_]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_option' ),
					'permission_callback' => WPAgent_Permissions::callback( 'get_option' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_option' ),
					'permission_callback' => WPAgent_Permissions::callback( 'update_option' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/search-replace',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'search_replace' ),
				'permission_callback' => WPAgent_Permissions::callback( 'run_search_replace' ),
			)
		);
	}

	public function health( WP_REST_Request $request ) {
		return $this->execute(
			'get_site_health',
			$request,
			function () use ( $request ) {
				$theme   = wp_get_theme();
				$plugins = get_option( 'active_plugins', array() );
				$issues  = array();

				if ( ! is_ssl() && ! get_option( 'wpagent_allow_insecure_app_passwords' ) ) {
					$issues[] = 'Site is not served over HTTPS. Application Passwords may be unavailable.';
				}

				if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
					$issues[] = 'PHP 8.1+ is recommended for WPAgent.';
				}

				$disk_free = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$disk_total = @disk_total_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

				$health_counts = array();
				if ( class_exists( 'WP_Site_Health' ) ) {
					wp_raise_memory_limit( 'admin' );
					$site_health = WP_Site_Health::get_instance();
					$tests       = $site_health->get_tests();
					$health_counts = array(
						'direct_tests' => isset( $tests['direct'] ) ? count( $tests['direct'] ) : 0,
						'async_tests'  => isset( $tests['async'] ) ? count( $tests['async'] ) : 0,
					);
				}

				$payload = array(
					'wordpress'      => get_bloginfo( 'version' ),
					'php'            => PHP_VERSION,
					'mysql'          => $GLOBALS['wpdb']->db_version(),
					'multisite'      => is_multisite(),
					'home_url'       => home_url( '/' ),
					'site_url'       => site_url( '/' ),
					'abspath'        => ABSPATH,
					'theme'          => array(
						'name'       => $theme->get( 'Name' ),
						'version'    => $theme->get( 'Version' ),
						'stylesheet' => $theme->get_stylesheet(),
					),
					'active_plugins' => array_values( (array) $plugins ),
					'plugin_count'   => count( (array) $plugins ),
					'disk'           => array(
						'free_bytes'  => is_numeric( $disk_free ) ? (int) $disk_free : null,
						'total_bytes' => is_numeric( $disk_total ) ? (int) $disk_total : null,
					),
					'site_health'    => $health_counts,
					'issues'         => $issues,
					'wpagent'        => array(
						'version'   => WPAGENT_VERSION,
						'namespace' => WPAGENT_REST_NAMESPACE,
					),
				);

				if ( rest_sanitize_boolean( $request->get_param( 'updates' ) ) ) {
					$payload['updates'] = $this->update_summary();
				}

				return $payload;
			}
		);
	}

	public function list_plugins( WP_REST_Request $request ) {
		return $this->execute(
			'list_plugins',
			$request,
			function () {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$all     = get_plugins();
				$active  = (array) get_option( 'active_plugins', array() );
				$pending = get_site_transient( 'update_plugins' );
				$items   = array();
				foreach ( $all as $file => $data ) {
					$update = ( is_object( $pending ) && isset( $pending->response[ $file ] ) ) ? $pending->response[ $file ] : null;
					$items[] = array(
						'file'             => $file,
						'name'             => $data['Name'] ?? $file,
						'version'          => $data['Version'] ?? '',
						'description'      => wp_strip_all_tags( $data['Description'] ?? '' ),
						'active'           => in_array( $file, $active, true ),
						'update_available' => null !== $update,
						'new_version'      => is_object( $update ) ? ( $update->new_version ?? '' ) : '',
					);
				}
				return array( 'items' => $items );
			}
		);
	}

	public function install_plugin( WP_REST_Request $request ) {
		return $this->execute(
			'install_plugin',
			$request,
			static function () use ( $request ) {
				return WPAgent_Plugin_Install::from_slug( (string) $request->get_param( 'slug' ) );
			},
			array( 'object_type' => 'plugin' )
		);
	}

	public function flush_page_cache( WP_REST_Request $request ) {
		return $this->execute(
			'flush_page_cache',
			$request,
			static fn() => WPAgent_Page_Cache::flush()
		);
	}

	public function activate_plugin( WP_REST_Request $request ) {
		return $this->plugin_toggle( $request, 'activate_plugin', true );
	}

	public function deactivate_plugin( WP_REST_Request $request ) {
		return $this->plugin_toggle( $request, 'deactivate_plugin', false );
	}

	public function update_plugin( WP_REST_Request $request ) {
		return $this->execute(
			'update_plugin',
			$request,
			function () use ( $request ) {
				$this->refuse_core_update( $request );
				$plugin = $this->plugin_file( $request );
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$all     = get_plugins();
				$before_v = $all[ $plugin ]['Version'] ?? '';
				$dir      = dirname( WP_PLUGIN_DIR . '/' . $plugin );
				$before_m = WPAgent_File_Manifest::snapshot( $dir );

				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				wp_update_plugins();
				$skin     = new Automatic_Upgrader_Skin();
				$upgrader = new Plugin_Upgrader( $skin );
				$result   = $upgrader->upgrade( $plugin );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( false === $result ) {
					throw new RuntimeException( 'Plugin update failed or no update was available.' );
				}

				wp_clean_plugins_cache( true );
				$all_after = get_plugins();
				$after_v   = $all_after[ $plugin ]['Version'] ?? $before_v;
				$after_m   = WPAgent_File_Manifest::snapshot( $dir );
				$health    = WPAgent_Health_Probe::probe();

				return array(
					'updated'     => true,
					'plugin'      => $plugin,
					'snapshot'    => array(
						'version_before' => $before_v,
						'version_after'  => $after_v,
						'files'          => WPAgent_File_Manifest::diff( $before_m, $after_m ),
					),
					'health'      => $health,
				);
			},
			array( 'object_type' => 'plugin' )
		);
	}

	public function list_themes( WP_REST_Request $request ) {
		return $this->execute(
			'list_themes',
			$request,
			function () {
				$current = wp_get_theme();
				$items   = array();
				foreach ( wp_get_themes() as $stylesheet => $theme ) {
					$items[] = array(
						'stylesheet' => $stylesheet,
						'name'       => $theme->get( 'Name' ),
						'version'    => $theme->get( 'Version' ),
						'active'     => $stylesheet === $current->get_stylesheet(),
					);
				}
				return array( 'items' => $items );
			}
		);
	}

	public function update_theme( WP_REST_Request $request ) {
		return $this->execute(
			'update_theme',
			$request,
			function () use ( $request ) {
				$this->refuse_core_update( $request );
				$stylesheet = sanitize_text_field( (string) $request->get_param( 'stylesheet' ) );
				if ( '' === $stylesheet ) {
					throw new InvalidArgumentException( 'stylesheet is required.' );
				}
				$theme    = wp_get_theme( $stylesheet );
				$before_v = $theme->exists() ? (string) $theme->get( 'Version' ) : '';
				$dir      = $theme->exists() ? $theme->get_stylesheet_directory() : get_theme_root() . '/' . $stylesheet;
				$before_m = WPAgent_File_Manifest::snapshot( $dir );

				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/theme.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				wp_update_themes();
				$skin     = new Automatic_Upgrader_Skin();
				$upgrader = new Theme_Upgrader( $skin );
				$result   = $upgrader->upgrade( $stylesheet );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( false === $result ) {
					throw new RuntimeException( 'Theme update failed or no update was available.' );
				}

				$after_theme = wp_get_theme( $stylesheet );
				$after_m     = WPAgent_File_Manifest::snapshot( $dir );
				$health      = WPAgent_Health_Probe::probe();

				return array(
					'updated'    => true,
					'stylesheet' => $stylesheet,
					'snapshot'   => array(
						'version_before' => $before_v,
						'version_after'  => $after_theme->exists() ? (string) $after_theme->get( 'Version' ) : $before_v,
						'files'          => WPAgent_File_Manifest::diff( $before_m, $after_m ),
					),
					'health'     => $health,
				);
			},
			array( 'object_type' => 'theme' )
		);
	}

	public function update_core( WP_REST_Request $request ) {
		return $this->execute(
			'update_core',
			$request,
			function () {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/update.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

				$before = get_bloginfo( 'version' );
				wp_version_check();
				$updates = get_core_updates();
				if ( ! is_array( $updates ) || ! $updates ) {
					throw new RuntimeException( 'No WordPress core update is available.' );
				}
				$offer = $updates[0];
				if ( isset( $offer->response ) && 'latest' === $offer->response ) {
					throw new RuntimeException( 'WordPress core is already up to date.' );
				}

				$skin     = new Automatic_Upgrader_Skin();
				$upgrader = new Core_Upgrader( $skin );
				$result   = $upgrader->upgrade( $offer );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( false === $result ) {
					throw new RuntimeException( 'WordPress core update failed.' );
				}

				$health = WPAgent_Health_Probe::probe();
				return array(
					'updated'  => true,
					'snapshot' => array(
						'version_before' => $before,
						'version_after'  => is_string( $result ) ? $result : get_bloginfo( 'version' ),
					),
					'health'   => $health,
					'note'     => 'Core updates are never included in plugin or theme update tools.',
				);
			},
			array( 'object_type' => 'core' )
		);
	}

	public function get_option( WP_REST_Request $request ) {
		return $this->execute(
			'get_option',
			$request,
			function () use ( $request ) {
				$key = sanitize_key( (string) $request['key'] );
				if ( ! WPAgent_Options_Allowlist::can_read( $key ) ) {
					throw WPAgent_Options_Allowlist::reject_read( $key );
				}
				$meta = WPAgent_Options_Allowlist::keys()[ $key ] ?? array(
					'writable'    => WPAgent_Options_Allowlist::can_write( $key ),
					'description' => 'WooCommerce shipping method instance settings',
				);
				return array(
					'key'         => $key,
					'value'       => get_option( $key ),
					'writable'    => $meta['writable'],
					'description' => $meta['description'],
				);
			}
		);
	}

	public function update_option( WP_REST_Request $request ) {
		$key    = sanitize_key( (string) $request['key'] );
		$before = get_option( $key );
		return $this->execute(
			'update_option',
			$request,
			function () use ( $request, $key ) {
				if ( ! WPAgent_Options_Allowlist::can_write( $key ) ) {
					throw WPAgent_Options_Allowlist::reject_write( $key );
				}
				$value = $request->get_param( 'value' );
				update_option( $key, $value );
				return array(
					'key'   => $key,
					'value' => get_option( $key ),
				);
			},
			array(
				'object_type'  => 'option',
				'before_state' => array( $key => $before ),
			)
		);
	}

	public function search_replace( WP_REST_Request $request ) {
		return $this->execute(
			'run_search_replace',
			$request,
			function () use ( $request ) {
				$search  = (string) $request->get_param( 'search' );
				$replace = (string) $request->get_param( 'replace' );
				$confirm = rest_sanitize_boolean( $request->get_param( 'confirm' ) );

				if ( '' === $search ) {
					throw new InvalidArgumentException( 'search is required.' );
				}

				$types = $request->get_param( 'post_types' );
				$types = $types ? array_map( 'sanitize_key', (array) $types ) : array( 'post', 'page' );
				$allowed_types = array( 'post', 'page' );
				$types = array_values( array_intersect( $types, $allowed_types ) );
				if ( ! $types ) {
					$types = $allowed_types;
				}

				$query = new WP_Query(
					array(
						'post_type'      => $types,
						'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
						'posts_per_page' => 200,
						'fields'         => 'ids',
					)
				);

				$preview = array();
				$changed = 0;

				foreach ( $query->posts as $id ) {
					$post    = get_post( (int) $id );
					$fields  = array( 'post_content' => $post->post_content, 'post_title' => $post->post_title, 'post_excerpt' => $post->post_excerpt );
					$updates = array();
					$diffs   = array();
					foreach ( $fields as $field => $value ) {
						if ( ! is_string( $value ) || ! str_contains( $value, $search ) ) {
							continue;
						}
						$updates[ $field ] = str_replace( $search, $replace, $value );
						$diffs[ $field ]   = WPAgent_Diff::field_snippets( $value, $search, $replace );
					}
					if ( ! $updates ) {
						continue;
					}
					++$changed;
					if ( count( $preview ) < WPAgent_Diff::MAX_ROWS ) {
						$preview[] = array(
							'id'     => (int) $id,
							'type'   => $post->post_type,
							'title'  => $post->post_title,
							'fields' => array_keys( $updates ),
							'diff'   => $diffs,
						);
					}

					if ( $confirm ) {
						$updates['ID'] = (int) $id;
						wp_update_post( $updates );
					}
				}

				$payload = array(
					'search'            => $search,
					'replace'           => $replace,
					'matches'           => $changed,
					'preview'           => $preview,
					'preview_truncated' => $changed > count( $preview ),
				);

				if ( ! $confirm ) {
					$payload['dry_run'] = true;
					$payload['note']    = 'No writes were performed. Re-run with confirm: true to apply after reviewing the diffs.';
					return $payload;
				}

				$payload['dry_run'] = false;
				$payload['updated'] = $changed;
				return $payload;
			},
			array( 'object_type' => 'content' )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function update_summary(): array {
		$core    = get_site_transient( 'update_core' );
		$plugins = get_site_transient( 'update_plugins' );
		$themes  = get_site_transient( 'update_themes' );

		$core_available = false;
		$core_version   = '';
		if ( is_object( $core ) && ! empty( $core->updates ) && is_array( $core->updates ) ) {
			$offer = $core->updates[0];
			if ( isset( $offer->response ) && 'upgrade' === $offer->response ) {
				$core_available = true;
				$core_version   = (string) ( $offer->current ?? $offer->version ?? '' );
			}
		}

		$plugin_updates = array();
		if ( is_object( $plugins ) && ! empty( $plugins->response ) && is_array( $plugins->response ) ) {
			foreach ( $plugins->response as $file => $info ) {
				$plugin_updates[] = array(
					'file'        => $file,
					'new_version' => is_object( $info ) ? ( $info->new_version ?? '' ) : '',
				);
			}
		}

		$theme_updates = array();
		if ( is_object( $themes ) && ! empty( $themes->response ) && is_array( $themes->response ) ) {
			foreach ( $themes->response as $stylesheet => $info ) {
				$theme_updates[] = array(
					'stylesheet'  => $stylesheet,
					'new_version' => is_array( $info ) ? ( $info['new_version'] ?? '' ) : '',
				);
			}
		}

		return array(
			'core'    => array(
				'update_available' => $core_available,
				'new_version'      => $core_version,
				'note'             => 'Core updates require the standalone update_core tool.',
			),
			'plugins' => $plugin_updates,
			'themes'  => $theme_updates,
		);
	}

	private function plugin_toggle( WP_REST_Request $request, string $command, bool $activate ) {
		$file = (string) $request->get_param( 'plugin' );
		return $this->execute(
			$command,
			$request,
			function () use ( $request, $activate ) {
				if ( ! function_exists( 'activate_plugin' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$plugin = $this->plugin_file( $request );
				if ( $activate ) {
					$result = activate_plugin( $plugin );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				} else {
					if ( plugin_basename( WPAGENT_FILE ) === $plugin ) {
						throw new InvalidArgumentException( 'WPAgent cannot deactivate itself through the API.' );
					}
					deactivate_plugins( $plugin );
				}
				return array(
					'plugin' => $plugin,
					'active' => $activate,
				);
			},
			array(
				'object_type'  => 'plugin',
				'before_state' => array( 'plugin' => $file, 'active' => ! $activate ),
			)
		);
	}

	private function plugin_file( WP_REST_Request $request ): string {
		$plugin = sanitize_text_field( (string) $request->get_param( 'plugin' ) );
		if ( '' === $plugin || ! str_contains( $plugin, '/' ) && ! str_ends_with( $plugin, '.php' ) ) {
			throw new InvalidArgumentException( 'plugin must be a plugin file path such as akismet/akismet.php.' );
		}
		return $plugin;
	}

	private function refuse_core_update( WP_REST_Request $request ): void {
		$plugin = strtolower( (string) $request->get_param( 'plugin' ) );
		if ( in_array( $plugin, array( 'wordpress', 'core', 'wordpress-core', 'wordpress/wordpress.php' ), true ) ) {
			throw new InvalidArgumentException( 'WordPress core updates require the standalone update_core tool with its own confirmation.' );
		}
	}
}
