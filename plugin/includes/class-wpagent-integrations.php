<?php
/**
 * Famous-plugin status: Google, Meta, SEO, payments — public IDs only.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Integrations {

	/**
	 * Known plugin file => catalog slug.
	 *
	 * @return array<string, string>
	 */
	public static function plugin_map(): array {
		return array(
			'woocommerce/woocommerce.php'                             => 'woocommerce',
			'woocommerce-payments/woocommerce-payments.php'           => 'woopayments',
			'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' => 'stripe',
			'woocommerce-paypal-payments/woocommerce-paypal-payments.php' => 'paypal',
			'woocommerce-square/woocommerce-square.php'               => 'square',
			'facebook-for-woocommerce/facebook-for-woocommerce.php'   => 'facebook-for-woocommerce',
			'official-facebook-pixel/official-facebook-pixel.php'     => 'facebook-pixel',
			'pixelyoursite/facebook-pixel-master.php'                 => 'pixelyoursite',
			'google-site-kit/google-site-kit.php'                     => 'google-site-kit',
			'google-listings-and-ads/google-listings-and-ads.php'     => 'google-listings-and-ads',
			'google-analytics-for-wordpress/googleanalytics.php'      => 'monsterinsights',
			'google-analytics-premium/googleanalytics-premium.php'    => 'monsterinsights-pro',
			'ga-google-analytics/ga-google-analytics.php'             => 'ga-google-analytics',
			'wordpress-seo/wp-seo.php'                                => 'yoast',
			'wordpress-seo-premium/wp-seo-premium.php'                => 'yoast-premium',
			'seo-by-rank-math/rank-math.php'                          => 'rank-math',
			'all-in-one-seo-pack/all_in_one_seo_pack.php'             => 'aioseo',
			'jetpack/jetpack.php'                                     => 'jetpack',
			'mailchimp-for-woocommerce/mailchimp-woocommerce.php'     => 'mailchimp-for-woocommerce',
			'klaviyo/klaviyo.php'                                     => 'klaviyo',
			'elementor/elementor.php'                                 => 'elementor',
			'litespeed-cache/litespeed-cache.php'                     => 'litespeed-cache',
			'wp-mail-smtp/wp_mail_smtp.php'                           => 'wp-mail-smtp',
			'fluent-smtp/fluent-smtp.php'                             => 'fluent-smtp',
		);
	}

	public static function looks_secret_key( string $key ): bool {
		return (bool) preg_match( '/(password|secret|token|api_key|access_token|refresh|private_key|license|auth_key|client_secret|webhook)/i', $key );
	}

	public static function looks_secret_value( string $value ): bool {
		return (bool) preg_match( '/^(sk_live_|sk_test_|rk_live_|rk_test_|whsec_|AIza|ya29\.|EAAG|pk_live_[a-z0-9]{20,})/i', $value );
	}

	public static function is_public_tracking_id( string $value ): bool {
		$value = trim( $value );
		return (bool) preg_match( '/^(G-[A-Z0-9]+|UA-\d+-\d+|GTM-[A-Z0-9]+|AW-\d+|DC-[A-Z0-9]+|pub-\d+|\d{5,20})$/i', $value );
	}

	/**
	 * Copy allowlisted scalar fields. Secret keys and token-shaped values are dropped.
	 *
	 * @param mixed             $raw  Option blob.
	 * @param array<int,string> $keys Public keys to keep.
	 * @return array<string, mixed>
	 */
	public static function pick_public( $raw, array $keys ): array {
		if ( ! is_array( $raw ) ) {
			return array( 'present' => false );
		}

		$out = array( 'present' => true );
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $raw ) || self::looks_secret_key( (string) $key ) ) {
				continue;
			}
			$value = $raw[ $key ];
			if ( is_string( $value ) && self::looks_secret_value( $value ) ) {
				$out[ $key ] = array( 'set' => true );
				continue;
			}
			if ( is_bool( $value ) || is_int( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_string( $value ) ) {
				$out[ $key ] = self::plain_text( $value, 160 );
			}
		}

		return $out;
	}

	public static function status(): array {
		$active = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
		$plugins = array();
		foreach ( self::plugin_map() as $file => $slug ) {
			$plugins[ $slug ] = array(
				'file'   => $file,
				'active' => in_array( $file, $active, true ),
			);
		}

		return array(
			'plugins'  => $plugins,
			'google'   => self::google_status(),
			'meta'     => self::meta_status(),
			'seo'      => self::seo_status(),
			'payments' => class_exists( 'WPAgent_Woo' ) ? WPAgent_Woo::payment_status() : array(),
			'note'     => 'OAuth tokens, API keys, licenses, and webhook secrets are never returned.',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function google_status(): array {
		$sitekit_modules = self::option_value( 'googlesitekit_active_modules' );
		$analytics       = self::pick_public(
			self::option_value( 'googlesitekit_analytics-4_settings' ),
			array( 'measurementID', 'useSnippet', 'propertyID', 'adsConversionID' )
		);
		$search          = self::pick_public(
			self::option_value( 'googlesitekit_search-console_settings' ),
			array( 'property_id' )
		);
		$gtm             = self::pick_public(
			self::option_value( 'googlesitekit_tagmanager_settings' ),
			array( 'containerID', 'useSnippet' )
		);
		$ads             = self::pick_public(
			self::option_value( 'googlesitekit_ads_settings' ),
			array( 'conversionID' )
		);
		$adsense         = self::pick_public(
			self::option_value( 'googlesitekit_adsense_settings' ),
			array( 'accountID', 'useSnippet' )
		);

		foreach ( array( &$analytics, &$gtm, &$ads, &$adsense ) as &$block ) {
			self::keep_public_ids( $block, array( 'measurementID', 'containerID', 'conversionID', 'adsConversionID', 'accountID' ) );
		}
		unset( $block );

		$monster = self::pick_public(
			self::option_value( 'monsterinsights_site_profile' ),
			array( 'v4' )
		);
		if ( ! $monster['present'] ) {
			$monster = self::pick_public(
				self::option_value( 'monsterinsights_settings' ),
				array( 'analytics_profile', 'ua', 'gtag' )
			);
		}

		$ga_legacy = self::option_value( 'gaog_options' );
		if ( ! is_array( $ga_legacy ) ) {
			$ga_legacy = self::option_value( 'gap_options' );
		}

		return array(
			'site_kit' => array(
				'modules'    => is_array( $sitekit_modules ) ? array_values( array_map( 'strval', $sitekit_modules ) ) : array(),
				'analytics'  => $analytics,
				'search'     => $search,
				'tagmanager' => $gtm,
				'ads'        => $ads,
				'adsense'    => $adsense,
				'connected'  => is_array( $sitekit_modules ) && $sitekit_modules !== array(),
			),
			'listings_and_ads' => array(
				'merchant_id_set' => '' !== (string) self::option_scalar( 'gla_merchant_id' ),
				'connected'       => (bool) self::option_scalar( 'gla_mc_setup_completed' ) || (bool) self::option_scalar( 'gla_ads_setup_completed' ),
			),
			'monsterinsights'  => $monster,
			'ga_plugin'        => self::pick_public( is_array( $ga_legacy ) ? $ga_legacy : null, array( 'tracking_id', 'ga_id' ) ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function meta_status(): array {
		$pixel = (string) self::option_scalar( 'wc_facebook_pixel_id' );
		$page  = (string) self::option_scalar( 'wc_facebook_page_id' );
		$catalog = (string) self::option_scalar( 'wc_facebook_catalog_id' );
		if ( '' === $catalog ) {
			$catalog = (string) self::option_scalar( 'wc_facebook_product_catalog_id' );
		}

		$official = self::pick_public(
			self::option_value( 'facebook_config' ),
			array( 'pixel_id', 'is_loaded' )
		);
		self::keep_public_ids( $official, array( 'pixel_id' ) );

		$pys = self::option_value( 'pys' );
		$pys_pixel = '';
		if ( is_array( $pys ) ) {
			$candidate = $pys['facebook']['pixel_id'] ?? $pys['facebook']['main_pixel'] ?? '';
			if ( is_string( $candidate ) && self::is_public_tracking_id( $candidate ) ) {
				$pys_pixel = $candidate;
			}
		}

		return array(
			'facebook_for_woocommerce' => array(
				'pixel_id'     => self::is_public_tracking_id( $pixel ) ? $pixel : ( '' !== $pixel ? array( 'set' => true ) : '' ),
				'page_id'      => self::is_public_tracking_id( $page ) ? $page : ( '' !== $page ? array( 'set' => true ) : '' ),
				'catalog_id'   => self::is_public_tracking_id( $catalog ) ? $catalog : ( '' !== $catalog ? array( 'set' => true ) : '' ),
				'capi_enabled' => (bool) self::option_scalar( 'wc_facebook_enable_s2s' ) || (bool) self::option_scalar( 'wc_facebook_enable_meta_capi' ),
				'token_set'    => '' !== (string) self::option_scalar( 'wc_facebook_access_token' ) || '' !== (string) self::option_scalar( 'wc_facebook_system_user_access_token' ),
			),
			'official_pixel' => $official,
			'pixelyoursite'  => array(
				'pixel_id' => $pys_pixel,
				'present'  => is_array( $pys ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function seo_status(): array {
		$yoast = self::pick_public(
			self::option_value( 'wpseo' ),
			array( 'enable_xml_sitemap', 'company_or_person', 'company_name', 'disable-author', 'disable-date' )
		);
		$social = self::pick_public(
			self::option_value( 'wpseo_social' ),
			array( 'facebook_site', 'twitter_site', 'instagram_url', 'linkedin_url', 'pinterest_url' )
		);
		$rank   = self::pick_public(
			self::option_value( 'rank-math-options-general' ),
			array( 'attachment_redirect_urls', 'nofollow_external_links', 'breadcrumbs' )
		);

		return array(
			'yoast'     => $yoast,
			'yoast_social' => $social,
			'rank_math' => $rank,
			'aioseo'    => array(
				'present' => null !== self::option_value( 'aioseo_options' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $block Public pick result.
	 * @param string[]             $id_keys Keys that must look like tracking IDs.
	 */
	/**
	 * Yoast / Rank Math title and robots for one post, page, or product.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_post_seo( int $id ): array {
		if ( ! function_exists( 'get_post' ) ) {
			throw new RuntimeException( 'WordPress is not loaded.' );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			throw new InvalidArgumentException( 'Content not found.' );
		}

		$robots = get_post_meta( $id, 'rank_math_robots', true );

		return array(
			'id'        => $id,
			'type'      => $post->post_type,
			'title'     => $post->post_title,
			'yoast'     => array(
				'title'       => (string) get_post_meta( $id, '_yoast_wpseo_title', true ),
				'description' => (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ),
				'canonical'   => (string) get_post_meta( $id, '_yoast_wpseo_canonical', true ),
				'noindex'     => (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true ),
			),
			'rank_math' => array(
				'title'       => (string) get_post_meta( $id, 'rank_math_title', true ),
				'description' => (string) get_post_meta( $id, 'rank_math_description', true ),
				'canonical'   => (string) get_post_meta( $id, 'rank_math_canonical_url', true ),
				'robots'      => is_array( $robots ) ? array_values( array_map( 'strval', $robots ) ) : $robots,
			),
		);
	}

	/**
	 * @param array<string, mixed> $fields Allowlisted SEO fields.
	 * @return array<string, mixed>
	 */
	public static function update_post_seo( int $id, array $fields ): array {
		if ( ! function_exists( 'get_post' ) ) {
			throw new RuntimeException( 'WordPress is not loaded.' );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			throw new InvalidArgumentException( 'Content not found.' );
		}

		$map = array(
			'yoast_title'       => '_yoast_wpseo_title',
			'yoast_description' => '_yoast_wpseo_metadesc',
			'yoast_canonical'   => '_yoast_wpseo_canonical',
			'yoast_noindex'     => '_yoast_wpseo_meta-robots-noindex',
			'rank_math_title'   => 'rank_math_title',
			'rank_math_description' => 'rank_math_description',
			'rank_math_canonical'   => 'rank_math_canonical_url',
		);
		$changed = array();
		foreach ( $map as $field => $meta ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}
			$value = self::plain_text( (string) $fields[ $field ], 'yoast_description' === $field || 'rank_math_description' === $field ? 320 : 200 );
			if ( 'yoast_noindex' === $field ) {
				$value = in_array( $value, array( '1', 'true', 'yes' ), true ) ? '1' : '';
			}
			if ( in_array( $field, array( 'yoast_canonical', 'rank_math_canonical' ), true ) && '' !== $value ) {
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					throw new InvalidArgumentException( 'Canonical must be a valid URL.' );
				}
			}
			update_post_meta( $id, $meta, $value );
			$changed[] = $field;
		}
		if ( ! $changed ) {
			throw new InvalidArgumentException( 'No allowed SEO fields were provided.' );
		}

		return array(
			'id'      => $id,
			'changed' => $changed,
			'seo'     => self::get_post_seo( $id ),
		);
	}

	public static function keep_public_ids( array &$block, array $id_keys ): void {
		foreach ( $id_keys as $key ) {
			if ( ! isset( $block[ $key ] ) || ! is_string( $block[ $key ] ) ) {
				continue;
			}
			if ( '' !== $block[ $key ] && ! self::is_public_tracking_id( $block[ $key ] ) ) {
				$block[ $key ] = array( 'set' => true );
			}
		}
	}

	/**
	 * @return mixed
	 */
	private static function option_value( string $key ) {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}
		return get_option( $key );
	}

	/**
	 * @return mixed
	 */
	private static function option_scalar( string $key ) {
		$value = self::option_value( $key );
		return is_scalar( $value ) ? $value : '';
	}

	private static function plain_text( string $value, int $max ): string {
		$value = preg_replace( '/[\x00-\x1F]+/', ' ', $value ) ?? $value;
		$value = trim( $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}
		return substr( $value, 0, $max );
	}
}
