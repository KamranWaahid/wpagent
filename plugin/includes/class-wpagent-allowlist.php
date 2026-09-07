<?php
/**
 * Default-deny command allowlist.
 *
 * Anything not listed here is rejected. Admins may disable entries; they cannot
 * invent new commands from the settings UI.
 *
 * @package WPAgent
 */

class WPAgent_Allowlist {

	/**
	 * Canonical command catalog.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions(): array {
		return array(
			'list_posts'                   => array(
				'capability'   => 'edit_posts',
				'write'        => false,
				'destructive'  => false,
				'description'  => 'List posts',
			),
			'get_post'                     => array(
				'capability'   => 'edit_posts',
				'write'        => false,
				'destructive'  => false,
				'description'  => 'Get a single post',
			),
			'create_post'                  => array(
				'capability'   => 'edit_posts',
				'write'        => true,
				'destructive'  => false,
				'draft_first'  => true,
				'description'  => 'Create a post (draft by default)',
			),
			'update_post'                  => array(
				'capability'   => 'edit_posts',
				'write'        => true,
				'destructive'  => false,
				'draft_first'  => true,
				'description'  => 'Update a post',
			),
			'delete_post'                  => array(
				'capability'   => 'delete_posts',
				'write'        => true,
				'destructive'  => true,
				'trash_only'   => true,
				'description'  => 'Move a post to trash',
			),
			'permanent_delete_post'        => array(
				'capability'       => 'delete_posts',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Permanently delete a post',
			),
			'list_pages'                   => array(
				'capability'  => 'edit_pages',
				'write'       => false,
				'destructive' => false,
				'description' => 'List pages',
			),
			'get_page'                     => array(
				'capability'  => 'edit_pages',
				'write'       => false,
				'destructive' => false,
				'description' => 'Get a single page',
			),
			'create_page'                  => array(
				'capability'  => 'edit_pages',
				'write'       => true,
				'destructive' => false,
				'draft_first' => true,
				'description' => 'Create a page (draft by default)',
			),
			'update_page'                  => array(
				'capability'  => 'edit_pages',
				'write'       => true,
				'destructive' => false,
				'draft_first' => true,
				'description' => 'Update a page',
			),
			'delete_page'                  => array(
				'capability'  => 'delete_pages',
				'write'       => true,
				'destructive' => true,
				'trash_only'  => true,
				'description' => 'Move a page to trash',
			),
			'permanent_delete_page'        => array(
				'capability'       => 'delete_pages',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Permanently delete a page',
			),
			'search_content'               => array(
				'capability'  => 'edit_posts',
				'write'       => false,
				'destructive' => false,
				'description' => 'Full-text search posts and pages',
			),
			'manage_taxonomy'              => array(
				'capability'  => 'manage_categories',
				'write'       => true,
				'destructive' => false,
				'description' => 'List, create, or assign categories and tags',
			),
			'manage_comments'              => array(
				'capability'  => 'moderate_comments',
				'write'       => true,
				'destructive' => false,
				'description' => 'List or moderate comments',
			),
			'list_media'                   => array(
				'capability'  => 'upload_files',
				'write'       => false,
				'destructive' => false,
				'description' => 'List media items',
			),
			'upload_media'                 => array(
				'capability'  => 'upload_files',
				'write'       => true,
				'destructive' => false,
				'description' => 'Upload media from a URL or base64 payload',
			),
			'read_pdf'                     => array(
				'capability'  => 'upload_files',
				'write'       => false,
				'destructive' => false,
				'description' => 'Extract text from a PDF in the media library (uploads only)',
			),
			'get_site_health'              => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Inspect PHP, theme, plugins, disk, and site health',
			),
			'list_plugins'                 => array(
				'capability'  => 'activate_plugins',
				'write'       => false,
				'destructive' => false,
				'description' => 'List installed plugins',
			),
			'list_themes'                  => array(
				'capability'  => 'switch_themes',
				'write'       => false,
				'destructive' => false,
				'description' => 'List installed themes',
			),
			'activate_plugin'              => array(
				'capability'       => 'activate_plugins',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Activate a plugin',
			),
			'deactivate_plugin'            => array(
				'capability'       => 'activate_plugins',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Deactivate a plugin',
			),
			'install_plugin'               => array(
				'capability'       => 'install_plugins',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Install a plugin from wordpress.org by slug (not activated)',
			),
			'update_plugin'                => array(
				'capability'       => 'update_plugins',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Update a plugin (never auto-runs)',
			),
			'update_theme'                 => array(
				'capability'       => 'update_themes',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Update a theme (never auto-runs)',
			),
			'update_core'                  => array(
				'capability'       => 'update_core',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Update WordPress core (never bundled with other updates)',
			),
			'get_option'                   => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Read an allowlisted option',
			),
			'update_option'                => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Update an allowlisted option',
			),
			'flush_page_cache'             => array(
				'capability'  => 'manage_options',
				'write'       => true,
				'destructive' => false,
				'description' => 'Flush object cache and known full-page caches (LiteSpeed, Rocket, Super Cache, …)',
			),
			'list_nav_menus'               => array(
				'capability'  => 'edit_theme_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List navigation menus and theme locations',
			),
			'get_nav_menu'                 => array(
				'capability'  => 'edit_theme_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Get one navigation menu and its items',
			),
			'manage_nav_menu'              => array(
				'capability'  => 'edit_theme_options',
				'write'       => true,
				'destructive' => false,
				'description' => 'Create a menu, add/update/remove items, or assign a theme location',
			),
			'delete_nav_menu'              => array(
				'capability'       => 'edit_theme_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Delete a navigation menu',
			),
			'run_search_replace'           => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'dry_run_default'  => true,
				'requires_confirm' => true,
				'description'      => 'Search-replace post content (dry-run by default)',
			),
			'list_users'                   => array(
				'capability'  => 'list_users',
				'write'       => false,
				'destructive' => false,
				'description' => 'List users',
			),
			'get_current_user_capabilities' => array(
				'capability'  => 'read',
				'write'       => false,
				'destructive' => false,
				'description' => 'Return the authenticated user and relevant capabilities',
			),
			'inspect_rendered_html'        => array(
				'capability'  => 'edit_posts',
				'write'       => false,
				'destructive' => false,
				'description' => 'Fetch a same-origin URL and return simplified HTML/text',
			),
			'query_db'                     => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'read_only'   => true,
				'description' => 'Run a read-only SELECT or SHOW TABLES with a row limit',
			),
			'get_audit_log'                => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Read the append-only audit log',
			),
			'create_draft_theme'           => array(
				'capability'  => 'edit_themes',
				'write'       => true,
				'destructive' => false,
				'description' => 'Clone the active theme into a sandboxed draft',
			),
			'get_draft_theme'              => array(
				'capability'  => 'edit_themes',
				'write'       => false,
				'destructive' => false,
				'description' => 'Show the current draft theme, if any',
			),
			'delete_draft_theme'           => array(
				'capability'       => 'edit_themes',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Delete the draft theme sandbox',
			),
			'publish_draft_theme'          => array(
				'capability'       => 'switch_themes',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Copy the draft over the live theme after preview',
			),
			'get_theme_preview_url'        => array(
				'capability'  => 'edit_themes',
				'write'       => false,
				'destructive' => false,
				'description' => 'Preview URL that renders the draft theme',
			),
			'list_theme_files'             => array(
				'capability'  => 'edit_themes',
				'write'       => false,
				'destructive' => false,
				'description' => 'List files in the draft theme (or active theme if no draft)',
			),
			'read_theme_file'              => array(
				'capability'  => 'edit_themes',
				'write'       => false,
				'destructive' => false,
				'description' => 'Read one theme file from the sandbox',
			),
			'write_theme_file'             => array(
				'capability'  => 'edit_themes',
				'write'       => true,
				'destructive' => false,
				'description' => 'Write one file in the draft theme only',
			),
			'search_theme_files'           => array(
				'capability'  => 'edit_themes',
				'write'       => false,
				'destructive' => false,
				'description' => 'Search text inside the sandboxed theme',
			),
			'list_wp_cli_commands'         => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List allowlisted WP-CLI-style commands',
			),
			'run_wp_cli'                   => array(
				'capability'  => 'manage_options',
				'write'       => true,
				'destructive' => false,
				'description' => 'Run one allowlisted WP-CLI-style command in PHP (no shell). Destructive verbs need an approval ticket.',
			),
			'discover_abilities'           => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List abilities registered by WordPress and plugins (WP 6.9+)',
			),
			'get_ability_info'             => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Inspect one ability including input/output schemas',
			),
			'run_ability'                  => array(
				'capability'  => 'manage_options',
				'write'       => true,
				'destructive' => false,
				'description' => 'Execute a registered ability. Non-readonly abilities need confirm=true.',
			),
			'list_page_builders'           => array(
				'capability'  => 'edit_posts',
				'write'       => false,
				'destructive' => false,
				'description' => 'List installed page builders and whether each is active',
			),
			'list_builder_catalog'         => array(
				'capability'  => 'edit_posts',
				'write'       => false,
				'destructive' => false,
				'description' => 'List widgets/elements/modules for one page builder',
			),
			'get_builder_page'             => array(
				'capability'  => 'edit_posts',
				'write'       => false,
				'destructive' => false,
				'description' => 'Read a post layout from the active page builder',
			),
			'save_builder_page'            => array(
				'capability'  => 'edit_posts',
				'write'       => true,
				'destructive' => false,
				'draft_first' => true,
				'description' => 'Create or update a page through the builder save pipeline (draft unless explicit_publish)',
			),
			'send_test_mail'               => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Send one wp_mail probe to admin_email or a same-domain address',
			),
			'get_mail_status'              => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Mail() availability, SMTP plugin, From address, last send status',
			),
			'get_payment_status'           => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Read-only WooPayments/Stripe flags (no secrets)',
			),
			'list_orders'                  => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List WooCommerce orders (HPOS-aware, no card data)',
			),
			'get_order'                    => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Get one WooCommerce order without payment secrets',
			),
			'update_order'                 => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Set a WooCommerce order status (pending/on-hold/processing/completed/cancelled/trash)',
			),
			'list_products'                => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List WooCommerce products (no cost secrets)',
			),
			'get_product'                  => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Get one WooCommerce product',
			),
			'update_product'               => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Update product name, status, visibility, stock, or prices',
			),
			'list_coupons'                 => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List WooCommerce coupons (no customer emails)',
			),
			'get_coupon'                   => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Get one WooCommerce coupon',
			),
			'list_shipping_zones'          => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List WooCommerce shipping zones and method titles',
			),
			'get_integrations_status'      => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Google, Meta, SEO, and payment plugin status (public IDs only)',
			),
			'list_customers'               => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List WooCommerce customers (id, name, email, order totals)',
			),
			'get_customer'                 => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Get one WooCommerce customer',
			),
			'get_store_report'             => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'Order count and revenue for a date range (no card data)',
			),
			'list_low_stock'               => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List products at or below a stock threshold',
			),
			'list_reviews'                 => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List product reviews',
			),
			'moderate_review'              => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Approve, hold, or trash a product review',
			),
			'create_coupon'                => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Create a WooCommerce coupon',
			),
			'update_coupon'                => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Update a WooCommerce coupon',
			),
			'list_variations'              => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List variations of a variable product',
			),
			'update_variation'             => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Update variation stock or prices',
			),
			'add_order_note'               => array(
				'capability'  => 'manage_options',
				'write'       => true,
				'destructive' => false,
				'description' => 'Add a private staff note on an order (never emails the customer)',
			),
			'create_refund'                => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Record a WooCommerce refund without calling the payment gateway',
			),
			'get_woo_emails'               => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List WooCommerce transactional email enabled flags',
			),
			'update_woo_email'             => array(
				'capability'       => 'manage_options',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Enable or disable one WooCommerce email type',
			),
			'get_seo'                      => array(
				'capability'  => 'edit_posts',
				'write'       => false,
				'destructive' => false,
				'description' => 'Read Yoast / Rank Math title and robots for one post',
			),
			'update_seo'                   => array(
				'capability'       => 'edit_posts',
				'write'            => true,
				'destructive'      => true,
				'requires_confirm' => true,
				'description'      => 'Update Yoast / Rank Math title, description, or canonical',
			),
			'list_tax_rates'               => array(
				'capability'  => 'manage_options',
				'write'       => false,
				'destructive' => false,
				'description' => 'List WooCommerce tax rates',
			),
		);
	}

	/**
	 * Return a command definition or null if unknown.
	 *
	 * @param string $command Command name.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $command ): ?array {
		$defs = self::definitions();
		return $defs[ $command ] ?? null;
	}

	/**
	 * Whether the command exists on the allowlist and is not disabled.
	 */
	public static function is_permitted( string $command, array $disabled = array() ): bool {
		if ( null === self::get( $command ) ) {
			return false;
		}

		return ! in_array( $command, $disabled, true );
	}

	/**
	 * Capability required for a command, or null if unknown.
	 */
	public static function capability_for( string $command ): ?string {
		$def = self::get( $command );
		return is_array( $def ) ? (string) $def['capability'] : null;
	}

	/**
	 * Normalize a stored disabled-commands option to a string list.
	 *
	 * @param mixed $raw Raw option value.
	 * @return string[]
	 */
	public static function normalize_disabled( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$known = array_keys( self::definitions() );
		$out   = array();
		foreach ( $raw as $item ) {
			if ( is_string( $item ) && in_array( $item, $known, true ) ) {
				$out[] = $item;
			}
		}

		return array_values( array_unique( $out ) );
	}
}
