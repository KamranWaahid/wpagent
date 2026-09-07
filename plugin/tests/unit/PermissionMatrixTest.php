<?php
/**
 * Documents the permission matrix enforced on REST routes.
 *
 * Full WP_REST_Request tests require the WordPress test suite (wp-env). These
 * unit tests lock the mapping the permission_callback closures consult.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class PermissionMatrixTest extends TestCase {

	/**
	 * @return array<string, string>
	 */
	private function expected(): array {
		return array(
			'list_posts'                    => 'edit_posts',
			'get_post'                      => 'edit_posts',
			'create_post'                   => 'edit_posts',
			'update_post'                   => 'edit_posts',
			'delete_post'                   => 'delete_posts',
			'permanent_delete_post'         => 'delete_posts',
			'list_pages'                    => 'edit_pages',
			'get_page'                      => 'edit_pages',
			'create_page'                   => 'edit_pages',
			'update_page'                   => 'edit_pages',
			'delete_page'                   => 'delete_pages',
			'permanent_delete_page'         => 'delete_pages',
			'search_content'                => 'edit_posts',
			'manage_taxonomy'               => 'manage_categories',
			'manage_comments'               => 'moderate_comments',
			'list_media'                    => 'upload_files',
			'upload_media'                  => 'upload_files',
			'read_pdf'                      => 'upload_files',
			'get_site_health'               => 'manage_options',
			'list_plugins'                  => 'activate_plugins',
			'list_themes'                   => 'switch_themes',
			'activate_plugin'               => 'activate_plugins',
			'deactivate_plugin'             => 'activate_plugins',
			'install_plugin'                => 'install_plugins',
			'update_plugin'                 => 'update_plugins',
			'update_theme'                  => 'update_themes',
			'update_core'                   => 'update_core',
			'get_option'                    => 'manage_options',
			'update_option'                 => 'manage_options',
			'flush_page_cache'              => 'manage_options',
			'list_nav_menus'                => 'edit_theme_options',
			'get_nav_menu'                  => 'edit_theme_options',
			'manage_nav_menu'               => 'edit_theme_options',
			'delete_nav_menu'               => 'edit_theme_options',
			'run_search_replace'            => 'manage_options',
			'list_users'                    => 'list_users',
			'get_current_user_capabilities' => 'read',
			'inspect_rendered_html'         => 'edit_posts',
			'query_db'                      => 'manage_options',
			'get_audit_log'                 => 'manage_options',
			'create_draft_theme'            => 'edit_themes',
			'get_draft_theme'               => 'edit_themes',
			'delete_draft_theme'            => 'edit_themes',
			'publish_draft_theme'           => 'switch_themes',
			'get_theme_preview_url'         => 'edit_themes',
			'list_theme_files'              => 'edit_themes',
			'read_theme_file'               => 'edit_themes',
			'write_theme_file'              => 'edit_themes',
			'search_theme_files'            => 'edit_themes',
			'list_wp_cli_commands'          => 'manage_options',
			'run_wp_cli'                    => 'manage_options',
			'discover_abilities'            => 'manage_options',
			'get_ability_info'              => 'manage_options',
			'run_ability'                   => 'manage_options',
			'list_page_builders'            => 'edit_posts',
			'list_builder_catalog'          => 'edit_posts',
			'get_builder_page'              => 'edit_posts',
			'save_builder_page'             => 'edit_posts',
		);
	}

	public function test_every_command_maps_to_expected_capability(): void {
		foreach ( $this->expected() as $command => $cap ) {
			$this->assertSame( $cap, WPAgent_Allowlist::capability_for( $command ), $command );
		}
	}

	public function test_no_extra_or_missing_commands(): void {
		$defined = array_keys( WPAgent_Allowlist::definitions() );
		sort( $defined );
		$expected = array_keys( $this->expected() );
		sort( $expected );
		$this->assertSame( $expected, $defined );
	}

	public function test_writes_that_must_confirm(): void {
		foreach ( array( 'activate_plugin', 'deactivate_plugin', 'install_plugin', 'update_plugin', 'update_theme', 'update_core', 'update_option', 'permanent_delete_post', 'permanent_delete_page', 'delete_draft_theme', 'publish_draft_theme', 'delete_nav_menu' ) as $command ) {
			$def = WPAgent_Allowlist::get( $command );
			$this->assertTrue( ! empty( $def['requires_confirm'] ), $command );
		}
	}
}
