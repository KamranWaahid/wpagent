<?php
/**
 * Page-cache flush via each cache plugin's own API (plus the object cache).
 *
 * @package WPAgent
 */

class WPAgent_Page_Cache {

	/**
	 * Writes that leave a stale HTML page if a full-page cache is on.
	 *
	 * @return string[]
	 */
	public static function auto_flush_commands(): array {
		return array(
			'save_builder_page',
			'delete_page',
			'delete_post',
			'publish_draft_theme',
			'run_search_replace',
			'install_plugin',
			'activate_plugin',
			'deactivate_plugin',
			'update_plugin',
			'update_theme',
			'update_core',
			'flush_page_cache',
			'manage_nav_menu',
			'delete_nav_menu',
		);
	}

	public static function should_flush_after( string $command ): bool {
		return in_array( $command, self::auto_flush_commands(), true );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function known_adapters(): array {
		return array(
			array(
				'id'        => 'object_cache',
				'label'     => 'WordPress object cache',
				'available' => static fn() => function_exists( 'wp_cache_flush' ),
				'flush'     => static function () {
					wp_cache_flush();
					if ( function_exists( 'wp_cache_flush_runtime' ) ) {
						wp_cache_flush_runtime();
					}
				},
			),
			array(
				'id'        => 'litespeed',
				'label'     => 'LiteSpeed Cache',
				'available' => static fn() => function_exists( 'do_action' ) && ( defined( 'LSCWP_V' ) || class_exists( '\LiteSpeed\Purge' ) ),
				'flush'     => static function () {
					if ( class_exists( '\LiteSpeed\Purge' ) && is_callable( array( '\LiteSpeed\Purge', 'purge_all' ) ) ) {
						\LiteSpeed\Purge::purge_all();
					}
					do_action( 'litespeed_purge_all' );
				},
			),
			array(
				'id'        => 'wp_rocket',
				'label'     => 'WP Rocket',
				'available' => static fn() => function_exists( 'rocket_clean_domain' ),
				'flush'     => static function () {
					rocket_clean_domain();
					if ( function_exists( 'rocket_clean_minify' ) ) {
						rocket_clean_minify();
					}
				},
			),
			array(
				'id'        => 'wp_super_cache',
				'label'     => 'WP Super Cache',
				'available' => static fn() => function_exists( 'wp_cache_clear_cache' ),
				'flush'     => static function () {
					wp_cache_clear_cache();
				},
			),
			array(
				'id'        => 'w3_total_cache',
				'label'     => 'W3 Total Cache',
				'available' => static fn() => function_exists( 'w3tc_flush_all' ),
				'flush'     => static function () {
					w3tc_flush_all();
				},
			),
			array(
				'id'        => 'autoptimize',
				'label'     => 'Autoptimize',
				'available' => static fn() => class_exists( 'autoptimizeCache' ) && is_callable( array( 'autoptimizeCache', 'clearall' ) ),
				'flush'     => static function () {
					autoptimizeCache::clearall();
				},
			),
			array(
				'id'        => 'cache_enabler',
				'label'     => 'Cache Enabler',
				'available' => static fn() => class_exists( 'Cache_Enabler' ) && is_callable( array( 'Cache_Enabler', 'clear_complete_cache' ) ),
				'flush'     => static function () {
					Cache_Enabler::clear_complete_cache();
				},
			),
			array(
				'id'        => 'wp_fastest_cache',
				'label'     => 'WP Fastest Cache',
				'available' => static fn() => class_exists( 'WpFastestCache' ),
				'flush'     => static function () {
					$cache = new WpFastestCache();
					if ( method_exists( $cache, 'deleteCache' ) ) {
						$cache->deleteCache( true );
					}
				},
			),
			array(
				'id'        => 'breeze',
				'label'     => 'Breeze',
				'available' => static fn() => function_exists( 'do_action' ) && ( defined( 'BREEZE_VERSION' ) || has_action( 'breeze_clear_all_cache' ) ),
				'flush'     => static function () {
					do_action( 'breeze_clear_all_cache' );
				},
			),
			array(
				'id'        => 'sg_optimizer',
				'label'     => 'SiteGround Optimizer',
				'available' => static fn() => function_exists( 'sg_cachepress_purge_cache' ),
				'flush'     => static function () {
					sg_cachepress_purge_cache();
				},
			),
			array(
				'id'        => 'flyingpress',
				'label'     => 'FlyingPress',
				'available' => static fn() => class_exists( '\FlyingPress\Purge' ) && is_callable( array( '\FlyingPress\Purge', 'purge_all' ) ),
				'flush'     => static function () {
					\FlyingPress\Purge::purge_all();
				},
			),
			array(
				'id'        => 'hummingbird',
				'label'     => 'Hummingbird',
				'available' => static fn() => function_exists( 'do_action' ) && has_action( 'wphb_clear_page_cache' ),
				'flush'     => static function () {
					do_action( 'wphb_clear_page_cache' );
				},
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function flush(): array {
		$ran      = array();
		$skipped  = array();
		$warnings = array();
		foreach ( self::known_adapters() as $adapter ) {
			$available = (bool) call_user_func( $adapter['available'] );
			if ( ! $available ) {
				$skipped[] = $adapter['id'];
				continue;
			}
			try {
				call_user_func( $adapter['flush'] );
				$ran[] = array(
					'id'    => $adapter['id'],
					'label' => $adapter['label'],
				);
			} catch ( Throwable $e ) {
				$warnings[] = array(
					'id'      => $adapter['id'],
					'message' => $e->getMessage(),
				);
			}
		}

		return array(
			'flushed'  => true,
			'ran'      => $ran,
			'skipped'  => $skipped,
			'warnings' => $warnings,
		);
	}
}
