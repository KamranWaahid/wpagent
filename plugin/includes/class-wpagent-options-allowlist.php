<?php
/**
 * Allowlisted wp_options keys. Arbitrary option writes are never exposed.
 * Built-in keys plus admin-added extras (still default-deny; forbidden keys cannot be added).
 *
 * @package WPAgent
 */

class WPAgent_Options_Allowlist {

	/**
	 * Keys that must never be added as custom writable (or at all).
	 *
	 * @return string[]
	 */
	public static function forbidden(): array {
		return array(
			'active_plugins',
			'active_sitewide_plugins',
			'uninstall_plugins',
			'recently_activated',
			'cron',
			'rewrite_rules',
			'user_roles',
			'wp_user_roles',
			'auth_key',
			'secure_auth_key',
			'logged_in_key',
			'nonce_key',
			'siteurl',
			'home',
			'admin_email',
			'users_can_register',
			'wpagent_encrypted_secrets',
			'wpagent_crypto_salt',
			'wpagent_option_allowlist',
			'hsts_probe_token',
			'woocommerce_stripe_settings',
			'woocommerce_woocommerce_payments_settings',
			'googlesitekit_credentials',
			'wc_facebook_access_token',
			'wc_facebook_system_user_access_token',
			'monsterinsights_license',
			'exactmetrics_license',
			'jetpack_private_options',
		);
	}

	/**
	 * Built-in catalog.
	 *
	 * @return array<string, array{writable:bool,description:string,source:string}>
	 */
	public static function builtin(): array {
		$keys = array(
			'blogname'               => array( 'writable' => true,  'description' => 'Site title' ),
			'blogdescription'        => array( 'writable' => true,  'description' => 'Tagline' ),
			'siteurl'                => array( 'writable' => false, 'description' => 'WordPress address (read-only)' ),
			'home'                   => array( 'writable' => false, 'description' => 'Site address (read-only)' ),
			'posts_per_page'         => array( 'writable' => true,  'description' => 'Blog pages show at most' ),
			'date_format'            => array( 'writable' => true,  'description' => 'Date format' ),
			'time_format'            => array( 'writable' => true,  'description' => 'Time format' ),
			'timezone_string'        => array( 'writable' => true,  'description' => 'Timezone' ),
			'start_of_week'          => array( 'writable' => true,  'description' => 'Week starts on' ),
			'default_comment_status' => array( 'writable' => true,  'description' => 'Default comment status (open/closed)' ),
			'comment_registration'   => array( 'writable' => true,  'description' => 'Users must be registered to comment' ),
			'show_on_front'          => array( 'writable' => true,  'description' => 'Front page displays (posts/page)' ),
			'page_on_front'          => array( 'writable' => true,  'description' => 'Static front page ID' ),
			'page_for_posts'         => array( 'writable' => true,  'description' => 'Posts page ID' ),
			'permalink_structure'    => array( 'writable' => false, 'description' => 'Permalink structure (read-only)' ),
			'blog_public'            => array( 'writable' => true,  'description' => 'Search engine visibility (1 = indexed)' ),
			'users_can_register'     => array( 'writable' => false, 'description' => 'Anyone can register (read-only)' ),
			'admin_email'            => array( 'writable' => false, 'description' => 'Administration email (read-only)' ),
			'WPLANG'                 => array( 'writable' => false, 'description' => 'Site language (read-only)' ),
			'thumbnail_size_w'       => array( 'writable' => true,  'description' => 'Thumbnail width' ),
			'thumbnail_size_h'       => array( 'writable' => true,  'description' => 'Thumbnail height' ),
			'medium_size_w'          => array( 'writable' => true,  'description' => 'Medium width' ),
			'medium_size_h'          => array( 'writable' => true,  'description' => 'Medium height' ),
			'large_size_w'           => array( 'writable' => true,  'description' => 'Large width' ),
			'large_size_h'           => array( 'writable' => true,  'description' => 'Large height' ),
		);

		$keys = array_merge( $keys, self::headers_security_advanced_hsts_keys(), self::woocommerce_store_keys() );

		foreach ( $keys as $key => $meta ) {
			$keys[ $key ]['source'] = 'builtin';
		}

		return $keys;
	}

	/**
	 * WooCommerce storefront settings that are safe to read/write (no payment secrets).
	 *
	 * @return array<string, array{writable:bool,description:string}>
	 */
	private static function woocommerce_store_keys(): array {
		return array(
			'woocommerce_shipping_hide_rates_when_free'               => array( 'writable' => true,  'description' => 'Hide paid shipping rates when free shipping applies (yes/no)' ),
			'woocommerce_allowed_countries'                           => array( 'writable' => true,  'description' => 'Selling countries (all/all_except/specific)' ),
			'woocommerce_specific_allowed_countries'                  => array( 'writable' => true,  'description' => 'Specific selling country codes' ),
			'woocommerce_ship_to_countries'                           => array( 'writable' => true,  'description' => 'Shipping countries (empty=allowed, specific, or disabled)' ),
			'woocommerce_specific_ship_to_countries'                  => array( 'writable' => true,  'description' => 'Specific shipping country codes' ),
			'woocommerce_checkout_privacy_policy_text'                => array( 'writable' => true,  'description' => 'Checkout privacy sentence ([privacy_policy] placeholder)' ),
			'woocommerce_checkout_terms_and_conditions_checkbox_text' => array( 'writable' => true,  'description' => 'Terms checkbox text ([terms] placeholder)' ),
			'woocommerce_terms_page_id'                               => array( 'writable' => true,  'description' => 'Terms and conditions page ID' ),
			'woocommerce_currency'                                    => array( 'writable' => false, 'description' => 'Store currency (read-only)' ),
			'woocommerce_default_country'                             => array( 'writable' => false, 'description' => 'Store base country (read-only)' ),
			'woocommerce_enable_guest_checkout'                       => array( 'writable' => true,  'description' => 'Allow customers to place orders without an account (yes/no)' ),
			'woocommerce_enable_signup_and_login_from_checkout'       => array( 'writable' => true,  'description' => 'Allow account creation during checkout (yes/no)' ),
			'woocommerce_enable_checkout_login_reminder'              => array( 'writable' => true,  'description' => 'Show checkout login reminder (yes/no)' ),
			'woocommerce_calc_taxes'                                  => array( 'writable' => true,  'description' => 'Enable tax calculation (yes/no)' ),
			'woocommerce_prices_include_tax'                          => array( 'writable' => true,  'description' => 'Prices entered include tax (yes/no)' ),
			'woocommerce_tax_display_shop'                            => array( 'writable' => true,  'description' => 'Shop tax display (incl/excl)' ),
			'woocommerce_tax_display_cart'                            => array( 'writable' => true,  'description' => 'Cart/checkout tax display (incl/excl)' ),
			'woocommerce_force_ssl_checkout'                          => array( 'writable' => true,  'description' => 'Force HTTPS at checkout (yes/no)' ),
			'woocommerce_email_from_name'                             => array( 'writable' => true,  'description' => 'WooCommerce email From name' ),
			'woocommerce_email_from_address'                          => array( 'writable' => true,  'description' => 'WooCommerce email From address' ),
			'woocommerce_email_reply_to_enabled'                      => array( 'writable' => true,  'description' => 'Enable WooCommerce Reply-To (yes/no)' ),
			'woocommerce_email_reply_to_name'                         => array( 'writable' => true,  'description' => 'WooCommerce Reply-To name' ),
			'woocommerce_email_reply_to_address'                      => array( 'writable' => true,  'description' => 'WooCommerce Reply-To address' ),
			'woocommerce_email_footer_text'                           => array( 'writable' => true,  'description' => 'WooCommerce email footer text' ),
			'woocommerce_store_address'                               => array( 'writable' => true,  'description' => 'Store address line 1' ),
			'woocommerce_store_address_2'                             => array( 'writable' => true,  'description' => 'Store address line 2' ),
			'woocommerce_store_city'                                  => array( 'writable' => true,  'description' => 'Store city' ),
			'woocommerce_store_postcode'                              => array( 'writable' => true,  'description' => 'Store postcode' ),
			'woocommerce_weight_unit'                                 => array( 'writable' => true,  'description' => 'Weight unit (kg/g/lbs/oz)' ),
			'woocommerce_dimension_unit'                              => array( 'writable' => true,  'description' => 'Dimension unit (m/cm/mm/in/yd)' ),
			'woocommerce_enable_reviews'                              => array( 'writable' => true,  'description' => 'Enable product reviews (yes/no)' ),
			'woocommerce_enable_review_rating'                        => array( 'writable' => true,  'description' => 'Enable review ratings (yes/no)' ),
			'woocommerce_review_rating_required'                      => array( 'writable' => true,  'description' => 'Ratings are required (yes/no)' ),
			'woocommerce_manage_stock'                                => array( 'writable' => true,  'description' => 'Enable stock management (yes/no)' ),
			'woocommerce_hold_stock_minutes'                          => array( 'writable' => true,  'description' => 'Hold stock (minutes) for unpaid orders' ),
			'woocommerce_notify_low_stock'                            => array( 'writable' => true,  'description' => 'Low stock notifications (yes/no)' ),
			'woocommerce_notify_no_stock'                             => array( 'writable' => true,  'description' => 'Out of stock notifications (yes/no)' ),
			'woocommerce_hide_out_of_stock_items'                     => array( 'writable' => true,  'description' => 'Hide out of stock items (yes/no)' ),
			'woocommerce_shop_page_id'                                => array( 'writable' => true,  'description' => 'Shop page ID' ),
			'woocommerce_cart_page_id'                                => array( 'writable' => true,  'description' => 'Cart page ID' ),
			'woocommerce_checkout_page_id'                            => array( 'writable' => true,  'description' => 'Checkout page ID' ),
			'woocommerce_myaccount_page_id'                           => array( 'writable' => true,  'description' => 'My Account page ID' ),
			'woocommerce_enable_ajax_add_to_cart'                     => array( 'writable' => true,  'description' => 'AJAX add to cart on archives (yes/no)' ),
			'woocommerce_cart_redirect_after_add'                     => array( 'writable' => true,  'description' => 'Redirect to cart after add (yes/no)' ),
			'woocommerce_demo_store'                                  => array( 'writable' => true,  'description' => 'Store notice enabled (yes/no)' ),
			'woocommerce_demo_store_notice'                           => array( 'writable' => true,  'description' => 'Store notice text' ),
			'wc_facebook_pixel_id'                                    => array( 'writable' => false, 'description' => 'Meta pixel ID (read-only)' ),
			'wc_facebook_page_id'                                     => array( 'writable' => false, 'description' => 'Meta page ID (read-only)' ),
			'wc_facebook_catalog_id'                                  => array( 'writable' => false, 'description' => 'Meta catalog ID (read-only)' ),
		);
	}

	/**
	 * Instance titles/costs such as woocommerce_flat_rate_6_settings.
	 */
	public static function is_shipping_instance_settings( string $key ): bool {
		return (bool) preg_match( '/^woocommerce_(free_shipping|flat_rate|local_pickup)_\d+_settings$/', $key );
	}

	/**
	 * Payment-gateway blobs that must never be read or written.
	 * Core forbidden() keys stay unlistable as extras; builtin read-only keys (siteurl) remain readable.
	 */
	public static function is_blocked_key( string $key ): bool {
		$always = array(
			'woocommerce_stripe_settings',
			'woocommerce_woocommerce_payments_settings',
			'googlesitekit_credentials',
			'wc_facebook_access_token',
			'wc_facebook_system_user_access_token',
			'monsterinsights_license',
			'exactmetrics_license',
			'jetpack_private_options',
		);
		if ( in_array( $key, $always, true ) ) {
			return true;
		}

		if ( preg_match( '/^woocommerce_(stripe|woocommerce_payments|ppcp|paypal|square|klarna|mollie|razorpay|braintree)/', $key ) ) {
			return true;
		}

		return (bool) preg_match( '/(access_token|refresh_token|client_secret|webhook_secret)/i', $key );
	}

	/**
	 * Headers Security Advanced & HSTS WP settings.
	 * Delivery modes are on|server|off. Probe token and writer internals stay off this list.
	 *
	 * @return array<string, array{writable:bool,description:string}>
	 */
	private static function headers_security_advanced_hsts_keys(): array {
		$keys = array(
			'hsts_max_age'                       => array( 'writable' => true,  'description' => 'HSTS max-age (seconds)' ),
			'hsts_include_subdomains'            => array( 'writable' => true,  'description' => 'HSTS includeSubDomains (0/1)' ),
			'hsts_preload'                       => array( 'writable' => true,  'description' => 'HSTS preload flag (0/1)' ),
			'hsts_csp'                           => array( 'writable' => true,  'description' => 'Content-Security-Policy value' ),
			'hsts_csp_report_uri'                => array( 'writable' => true,  'description' => 'CSP report URI' ),
			'hsts_pp'                            => array( 'writable' => true,  'description' => 'Permissions-Policy value' ),
			'hsts_x_frame_options'                => array( 'writable' => true,  'description' => 'X-Frame-Options (DENY/SAMEORIGIN/ALLOW-FROM)' ),
			'hsts_x_frame_options_allow_from_url' => array( 'writable' => true,  'description' => 'X-Frame-Options ALLOW-FROM URL' ),
			'hsts_show_migration_notice_v3'       => array( 'writable' => true,  'description' => 'Show HSTS plugin migration notice (0/1)' ),
			'hsts_detected_server'               => array( 'writable' => false, 'description' => 'Detected server (LiteSpeed/Apache/other)' ),
			'hsts_htaccess_written_headers'      => array( 'writable' => false, 'description' => 'Headers last written to .htaccess' ),
			'hsts_plugin_db_version'             => array( 'writable' => false, 'description' => 'HSTS plugin DB schema version' ),
		);

		$header_slugs = array(
			'strict_transport_security'           => 'Strict-Transport-Security',
			'content_security_policy'             => 'Content-Security-Policy',
			'permissions_policy'                  => 'Permissions-Policy',
			'x_frame_options'                     => 'X-Frame-Options',
			'x_content_type_options'              => 'X-Content-Type-Options',
			'referrer_policy'                     => 'Referrer-Policy',
			'x_permitted_cross_domain_policies'   => 'X-Permitted-Cross-Domain-Policies',
			'cross_origin_opener_policy'          => 'Cross-Origin-Opener-Policy',
			'cross_origin_resource_policy'        => 'Cross-Origin-Resource-Policy',
			'access_control_allow_methods'        => 'Access-Control-Allow-Methods',
			'access_control_allow_headers'        => 'Access-Control-Allow-Headers',
		);

		foreach ( $header_slugs as $slug => $header ) {
			$keys[ 'hsts_mode_' . $slug ]    = array(
				'writable'    => true,
				'description' => $header . ' delivery (on|server|off)',
			);
			$keys[ 'hsts_disable_' . $slug ] = array(
				'writable'    => true,
				'description' => $header . ' legacy disable flag (0/1)',
			);
		}

		return $keys;
	}

	/**
	 * @param mixed $raw Stored extras.
	 * @return array<string, array{writable:bool,description:string,source:string}>
	 */
	public static function normalize_extras( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$forbidden = self::forbidden();
		$builtin   = self::builtin();
		$out       = array();

		foreach ( $raw as $maybe_key => $item ) {
			if ( is_string( $item ) ) {
				$item = array( 'key' => $item );
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			$key = sanitize_key( (string) ( $item['key'] ?? ( is_string( $maybe_key ) && ! is_numeric( $maybe_key ) ? $maybe_key : '' ) ) );
			if ( '' === $key || isset( $builtin[ $key ] ) || in_array( $key, $forbidden, true ) ) {
				continue;
			}
			$out[ $key ] = array(
				'writable'    => ! empty( $item['writable'] ),
				'description' => sanitize_text_field( (string) ( $item['description'] ?? 'Custom allowlisted option' ) ),
				'source'      => 'custom',
			);
		}

		return $out;
	}

	/**
	 * Merged catalog.
	 *
	 * @param array<int|string, mixed>|null $extras Extra keys (or null to load from WP).
	 * @return array<string, array{writable:bool,description:string,source?:string}>
	 */
	public static function keys( ?array $extras = null ): array {
		$merged = self::builtin();
		if ( null === $extras && function_exists( 'get_option' ) ) {
			$extras = get_option( 'wpagent_option_allowlist', array() );
		}
		foreach ( self::normalize_extras( $extras ?? array() ) as $key => $meta ) {
			$merged[ $key ] = $meta;
		}
		return $merged;
	}

	public static function can_read( string $key, ?array $extras = null ): bool {
		if ( self::is_blocked_key( $key ) ) {
			return false;
		}
		if ( self::is_shipping_instance_settings( $key ) ) {
			return true;
		}
		return isset( self::keys( $extras )[ $key ] );
	}

	public static function can_write( string $key, ?array $extras = null ): bool {
		if ( self::is_blocked_key( $key ) ) {
			return false;
		}
		if ( self::is_shipping_instance_settings( $key ) ) {
			return true;
		}
		$keys = self::keys( $extras );
		return isset( $keys[ $key ] ) && true === $keys[ $key ]['writable'];
	}

	/**
	 * @return string[]
	 */
	public static function sample_keys( int $n = 12 ): array {
		return array_slice( array_keys( self::keys() ), 0, max( 1, $n ) );
	}

	public static function reject_read( string $key ): WPAgent_Validation_Exception {
		$e = new WPAgent_Validation_Exception(
			sprintf( 'Option "%s" is not on the allowlist. Run option list for readable keys (includes Woo store settings).', $key )
		);
		$e->details = array(
			'reason'          => 'not_on_allowlist',
			'hint'            => 'Run option list',
			'allowed_sample'  => self::sample_keys(),
		);
		return $e;
	}

	public static function reject_write( string $key ): WPAgent_Validation_Exception {
		if ( ! self::can_read( $key ) ) {
			return self::reject_read( $key );
		}

		$e = new WPAgent_Validation_Exception(
			sprintf( 'Option "%s" is on the allowlist but is not writable. Run option list.', $key )
		);
		$e->details = array(
			'reason' => 'not_writable',
			'hint'   => 'Run option list',
		);
		return $e;
	}
}
