<?php
/**
 * Install a plugin from wordpress.org only (slug → official zip).
 *
 * @package WPAgent
 */

class WPAgent_Plugin_Install {

	public static function normalize_slug( string $slug ): string {
		$slug = strtolower( trim( $slug ) );
		if ( str_contains( $slug, '/' ) || str_contains( $slug, '\\' ) || str_contains( $slug, '.' ) ) {
			throw new InvalidArgumentException( 'Pass a wordpress.org slug (e.g. akismet), not a path or URL.' );
		}
		$slug = preg_replace( '/[^a-z0-9-]/', '', $slug ) ?? '';
		if ( '' === $slug || strlen( $slug ) > 80 ) {
			throw new InvalidArgumentException( 'Plugin slug must be a wordpress.org slug such as akismet.' );
		}
		return $slug;
	}

	public static function assert_wordpress_org_zip( string $url ): string {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $parts ) ) {
			throw new InvalidArgumentException( 'wordpress.org did not return a usable download URL.' );
		}
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$path   = (string) ( $parts['path'] ?? '' );
		if ( 'https' !== $scheme || 'downloads.wordpress.org' !== $host ) {
			throw new InvalidArgumentException( 'Refusing a download that is not https://downloads.wordpress.org/.' );
		}
		if ( ! str_starts_with( $path, '/plugin/' ) || ! str_ends_with( strtolower( $path ), '.zip' ) ) {
			throw new InvalidArgumentException( 'Download path must be /plugin/<slug>.*.zip on downloads.wordpress.org.' );
		}
		return $url;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function from_slug( string $slug ): array {
		$slug = self::normalize_slug( $slug );
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			throw new RuntimeException( 'Plugin installs are locked on this host (DISALLOW_FILE_MODS).' );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$existing = self::find_installed( $slug );
		if ( $existing ) {
			return array(
				'installed'         => false,
				'already_installed' => true,
				'slug'              => $slug,
				'plugin'            => $existing,
			);
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'sections' => false,
					'downloadlink' => true,
				),
			)
		);
		if ( is_wp_error( $api ) ) {
			throw new RuntimeException( $api->get_error_message() );
		}
		$link = is_object( $api ) ? (string) ( $api->download_link ?? '' ) : '';
		$link = self::assert_wordpress_org_zip( $link );

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $link );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
		if ( false === $result ) {
			$messages = method_exists( $skin, 'get_upgrade_messages' ) ? $skin->get_upgrade_messages() : array();
			throw new RuntimeException( $messages ? implode( ' ', $messages ) : 'Plugin install failed.' );
		}

		wp_clean_plugins_cache( true );
		$file = self::find_installed( $slug );
		return array(
			'installed'         => true,
			'already_installed' => false,
			'slug'              => $slug,
			'plugin'            => $file ?: ( $upgrader->plugin_info() ?: '' ),
			'name'              => is_object( $api ) ? (string) ( $api->name ?? $slug ) : $slug,
			'version'           => is_object( $api ) ? (string) ( $api->version ?? '' ) : '',
			'activated'         => false,
			'note'              => 'Installed but not activated. Use activate_plugin if you want it on.',
		);
	}

	public static function find_installed( string $slug ): ?string {
		if ( ! function_exists( 'get_plugins' ) ) {
			return null;
		}
		foreach ( get_plugins() as $file => $_data ) {
			$dir = dirname( $file );
			if ( $slug === $dir || $file === $slug . '.php' ) {
				return $file;
			}
		}
		return null;
	}
}
