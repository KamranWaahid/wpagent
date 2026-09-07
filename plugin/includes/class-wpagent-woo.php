<?php
/**
 * WooCommerce and mail helpers that never return payment or SMTP secrets.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Woo {

	public const MAX_ORDERS    = 50;
	public const MAX_PRODUCTS  = 50;
	public const MAX_COUPONS   = 50;

	/**
	 * Safe yes/no and title flags from a gateway settings array.
	 *
	 * @param mixed             $raw  Option value.
	 * @param array<string,string> $keys Map of setting key => yesno|title.
	 * @return array<string, mixed>
	 */
	public static function gateway_flags( $raw, array $keys ): array {
		if ( ! is_array( $raw ) ) {
			return array( 'present' => false );
		}

		$out = array( 'present' => true );
		foreach ( $keys as $key => $type ) {
			$val = $raw[ $key ] ?? null;
			if ( 'yesno' === $type ) {
				$out[ $key ] = 'yes' === $val;
			} elseif ( 'title' === $type ) {
				$out[ $key ] = is_string( $val ) ? self::plain_text( $val, 80 ) : '';
			}
		}

		return $out;
	}

	/**
	 * Whether a test email may be sent to this address.
	 *
	 * @param string   $to           Candidate recipient.
	 * @param string   $admin_email  WordPress admin_email.
	 * @param string   $home_host    Host from home_url (no scheme).
	 * @param string[] $store_emails Extra allowlisted store addresses.
	 */
	public static function is_allowed_test_recipient( string $to, string $admin_email, string $home_host, array $store_emails = array() ): bool {
		$to = strtolower( trim( $to ) );
		if ( ! self::looks_like_email( $to ) ) {
			return false;
		}

		if ( $to === strtolower( trim( $admin_email ) ) ) {
			return true;
		}

		foreach ( $store_emails as $extra ) {
			if ( $to === strtolower( trim( (string) $extra ) ) ) {
				return true;
			}
		}

		$host   = strtolower( (string) preg_replace( '/^www\./', '', $home_host ) );
		$domain = strtolower( (string) preg_replace( '/^www\./', '', substr( strrchr( $to, '@' ) ?: '', 1 ) ) );

		return '' !== $host && '' !== $domain && $host === $domain;
	}

	public static function looks_like_email( string $value ): bool {
		return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Host / port / encryption / username only. Password keys are dropped.
	 *
	 * @param mixed $raw FluentSMTP or similar settings.
	 * @return array<string, mixed>
	 */
	public static function summarize_smtp_settings( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array( 'configured' => false );
		}

		$found = array();
		self::walk_smtp_settings( $raw, $found );
		if ( ! $found ) {
			return array( 'configured' => false );
		}

		return array_merge( array( 'configured' => true ), $found );
	}

	/**
	 * @param array<string, mixed> $data Nested settings.
	 * @param array<string, mixed> $found Collected public fields.
	 */
	private static function walk_smtp_settings( array $data, array &$found ): void {
		$public = array( 'host', 'port', 'encryption', 'username', 'sender_email', 'sender_name', 'provider', 'provider_key', 'auth' );
		foreach ( $data as $key => $value ) {
			$key = strtolower( (string) $key );
			if ( preg_match( '/(password|secret|api_key|token|auth_key)/', $key ) ) {
				continue;
			}
			if ( in_array( $key, $public, true ) && ( is_string( $value ) || is_int( $value ) || is_bool( $value ) ) ) {
				if ( 'username' === $key || 'sender_email' === $key ) {
					$found[ $key ] = is_string( $value ) && self::looks_like_email( $value )
						? strtolower( $value )
						: array( 'set' => '' !== (string) $value, 'length' => strlen( (string) $value ) );
				} elseif ( 'port' === $key ) {
					$found[ $key ] = (int) $value;
				} else {
					$found[ $key ] = is_string( $value ) ? self::plain_text( (string) $value, 80 ) : $value;
				}
			}
			if ( is_array( $value ) ) {
				self::walk_smtp_settings( $value, $found );
			}
		}
	}

	public static function sanitize_mail_error( string $message ): string {
		$message = preg_replace( '/password["\'\s:=]+[^\s\'"]+/i', 'password=[redacted]', $message ) ?? $message;
		return self::plain_text( $message, 300 );
	}

	public static function payment_status(): array {
		$wcpay  = self::gateway_flags(
			function_exists( 'get_option' ) ? get_option( 'woocommerce_woocommerce_payments_settings' ) : null,
			array(
				'enabled'        => 'yesno',
				'test_mode'      => 'yesno',
				'manual_capture' => 'yesno',
				'title'          => 'title',
			)
		);
		$stripe = self::gateway_flags(
			function_exists( 'get_option' ) ? get_option( 'woocommerce_stripe_settings' ) : null,
			array(
				'enabled'  => 'yesno',
				'testmode' => 'yesno',
				'title'    => 'title',
			)
		);

		return array(
			'woocommerce_active' => self::woo_active(),
			'woopayments'        => $wcpay,
			'stripe'             => $stripe,
			'paypal'             => self::gateway_flags(
				self::first_option(
					array(
						'woocommerce_ppcp-gateway_settings',
						'woocommerce-ppcp-settings',
						'woocommerce_paypal_settings',
					)
				),
				array(
					'enabled' => 'yesno',
					'sandbox' => 'yesno',
					'title'   => 'title',
				)
			),
			'square'             => self::gateway_flags(
				self::first_option( array( 'woocommerce_square_credit_card_settings' ) ),
				array(
					'enabled' => 'yesno',
					'title'   => 'title',
				)
			),
			'cod'                => self::gateway_flags(
				self::first_option( array( 'woocommerce_cod_settings' ) ),
				array(
					'enabled' => 'yesno',
					'title'   => 'title',
				)
			),
			'bacs'               => self::gateway_flags(
				self::first_option( array( 'woocommerce_bacs_settings' ) ),
				array(
					'enabled' => 'yesno',
					'title'   => 'title',
				)
			),
			'cheque'             => self::gateway_flags(
				self::first_option( array( 'woocommerce_cheque_settings' ) ),
				array(
					'enabled' => 'yesno',
					'title'   => 'title',
				)
			),
			'note'               => 'Secrets, account numbers, and API keys are never returned.',
		);
	}

	public static function mail_status(): array {
		$disabled = array_filter(
			array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) )
		);

		$smtp_plugins = array();
		if ( function_exists( 'is_plugin_active' ) || function_exists( 'get_option' ) ) {
			$active = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
			$map    = array(
				'fluent-smtp/fluent-smtp.php'         => 'fluent-smtp',
				'wp-mail-smtp/wp_mail_smtp.php'       => 'wp-mail-smtp',
				'easy-wp-smtp/easy-wp-smtp.php'       => 'easy-wp-smtp',
				'post-smtp/postman-smtp.php'          => 'post-smtp',
			);
			foreach ( $map as $file => $slug ) {
				if ( in_array( $file, $active, true ) ) {
					$smtp_plugins[] = $slug;
				}
			}
		}

		$fluent = function_exists( 'get_option' ) ? get_option( 'fluentmail-settings' ) : null;

		return array(
			'mail_function'     => function_exists( 'mail' ) && ! in_array( 'mail', $disabled, true ),
			'mail_disabled'     => in_array( 'mail', $disabled, true ),
			'smtp_plugins'      => $smtp_plugins,
			'admin_email'       => function_exists( 'get_option' ) ? (string) get_option( 'admin_email' ) : '',
			'woocommerce_from'  => function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_email_from_address' ) : '',
			'woocommerce_name'  => function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_email_from_name' ) : '',
			'fluent_smtp'       => self::summarize_smtp_settings( is_array( $fluent ) ? $fluent : null ),
			'last_sends'        => self::recent_smtp_logs( 8 ),
		);
	}

	public static function health_mail_extras(): array {
		$status = self::mail_status();
		return array(
			'mail_function' => $status['mail_function'],
			'mail_disabled' => $status['mail_disabled'],
			'smtp_plugins'  => $status['smtp_plugins'],
			'from_address'  => $status['woocommerce_from'] ?: $status['admin_email'],
		);
	}

	public static function send_test_mail( ?string $to = null ): array {
		if ( ! function_exists( 'wp_mail' ) ) {
			throw new RuntimeException( 'wp_mail is not available.' );
		}

		$admin = (string) get_option( 'admin_email' );
		$host  = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$store = array_filter(
			array(
				(string) get_option( 'woocommerce_email_from_address' ),
				(string) get_option( 'woocommerce_email_reply_to_address' ),
			)
		);
		$to    = $to ? strtolower( trim( $to ) ) : strtolower( $admin );

		if ( ! self::is_allowed_test_recipient( $to, $admin, $host, $store ) ) {
			throw new InvalidArgumentException( 'Recipient must be admin_email, the Woo From/Reply-To address, or the same domain as the site.' );
		}

		$fail    = '';
		$handler = static function ( $error ) use ( &$fail ) {
			if ( $error instanceof WP_Error ) {
				$fail = WPAgent_Woo::sanitize_mail_error( $error->get_error_message() );
			}
		};
		add_action( 'wp_mail_failed', $handler, 20 );
		$ok = wp_mail(
			$to,
			'[WPAgent] Test email',
			'This is a WPAgent mail probe. It is only sent to an allowlisted store address.'
		);
		remove_action( 'wp_mail_failed', $handler, 20 );

		return array(
			'ok'        => (bool) $ok,
			'to'        => $to,
			'error'     => $fail,
			'sent_at'   => gmdate( 'c' ),
			'note'      => $ok ? 'Check the inbox (and spam) for [WPAgent] Test email.' : 'wp_mail returned false. See error.',
		);
	}

	public static function list_orders( int $limit = 20, string $status = '', int $offset = 0 ): array {
		self::require_woo();
		$limit  = max( 1, min( $limit, self::MAX_ORDERS ) );
		$offset = max( 0, $offset );
		$args   = array(
			'limit'   => $limit,
			'offset'  => $offset,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		);
		if ( '' !== $status ) {
			$args['status'] = sanitize_key( $status );
		}

		$orders = wc_get_orders( $args );
		$items  = array();
		foreach ( (array) $orders as $order ) {
			if ( $order instanceof WC_Order ) {
				$items[] = self::summarize_order( $order, false );
			}
		}

		return array(
			'items' => $items,
			'limit' => $limit,
			'count' => count( $items ),
		);
	}

	public static function get_order( int $id ): array {
		self::require_woo();
		$order = wc_get_order( $id );
		if ( ! $order instanceof WC_Order ) {
			throw new InvalidArgumentException( 'Order not found.' );
		}

		return self::summarize_order( $order, true );
	}

	public static function update_order( int $id, string $status ): array {
		self::require_woo();
		$status = sanitize_key( $status );
		$allowed = array( 'cancelled', 'trash', 'pending', 'on-hold', 'processing', 'completed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			throw new InvalidArgumentException( 'Status must be pending, on-hold, processing, completed, cancelled, or trash.' );
		}

		$order = wc_get_order( $id );
		if ( ! $order instanceof WC_Order ) {
			throw new InvalidArgumentException( 'Order not found.' );
		}

		$before = $order->get_status();
		if ( 'trash' === $status ) {
			$order->delete( false );
			$after = 'trash';
		} else {
			$order->update_status( $status, 'Status changed via WPAgent.' );
			$after = $status;
		}

		return array(
			'id'     => $id,
			'before' => $before,
			'status' => $after,
		);
	}

	public static function list_products( int $limit = 20, string $status = '', string $search = '', int $offset = 0 ): array {
		self::require_woo();
		$limit  = max( 1, min( $limit, self::MAX_PRODUCTS ) );
		$offset = max( 0, $offset );
		$args   = array(
			'limit'  => $limit,
			'offset' => $offset,
			'orderby' => 'date',
			'order'  => 'DESC',
			'return' => 'objects',
		);
		if ( '' !== $status ) {
			$args['status'] = sanitize_key( $status );
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$products = wc_get_products( $args );
		$items    = array();
		foreach ( (array) $products as $product ) {
			if ( $product instanceof WC_Product ) {
				$items[] = self::summarize_product( $product, false );
			}
		}

		return array(
			'items' => $items,
			'limit' => $limit,
			'count' => count( $items ),
		);
	}

	public static function get_product( int $id ): array {
		self::require_woo();
		$product = wc_get_product( $id );
		if ( ! $product instanceof WC_Product ) {
			throw new InvalidArgumentException( 'Product not found.' );
		}

		return self::summarize_product( $product, true );
	}

	/**
	 * @param array<string, mixed> $fields Allowlisted product fields.
	 */
	public static function update_product( int $id, array $fields ): array {
		self::require_woo();
		$product = wc_get_product( $id );
		if ( ! $product instanceof WC_Product ) {
			throw new InvalidArgumentException( 'Product not found.' );
		}

		$changed = array();
		if ( isset( $fields['status'] ) ) {
			$status = sanitize_key( (string) $fields['status'] );
			if ( ! in_array( $status, array( 'draft', 'pending', 'private', 'publish' ), true ) ) {
				throw new InvalidArgumentException( 'Product status must be draft, pending, private, or publish.' );
			}
			$product->set_status( $status );
			$changed[] = 'status';
		}
		if ( isset( $fields['catalog_visibility'] ) ) {
			$vis = sanitize_key( (string) $fields['catalog_visibility'] );
			if ( ! in_array( $vis, array( 'visible', 'catalog', 'search', 'hidden' ), true ) ) {
				throw new InvalidArgumentException( 'catalog_visibility must be visible, catalog, search, or hidden.' );
			}
			$product->set_catalog_visibility( $vis );
			$changed[] = 'catalog_visibility';
		}
		if ( array_key_exists( 'featured', $fields ) ) {
			$product->set_featured( ! empty( $fields['featured'] ) );
			$changed[] = 'featured';
		}
		if ( isset( $fields['stock_status'] ) ) {
			$stock = sanitize_key( (string) $fields['stock_status'] );
			if ( ! in_array( $stock, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
				throw new InvalidArgumentException( 'stock_status must be instock, outofstock, or onbackorder.' );
			}
			$product->set_stock_status( $stock );
			$changed[] = 'stock_status';
		}
		if ( array_key_exists( 'manage_stock', $fields ) ) {
			$product->set_manage_stock( ! empty( $fields['manage_stock'] ) );
			$changed[] = 'manage_stock';
		}
		if ( array_key_exists( 'stock_quantity', $fields ) ) {
			$qty = $fields['stock_quantity'];
			if ( null !== $qty && '' !== $qty && ! is_numeric( $qty ) ) {
				throw new InvalidArgumentException( 'stock_quantity must be numeric.' );
			}
			$product->set_stock_quantity( '' === $qty || null === $qty ? null : (int) $qty );
			$changed[] = 'stock_quantity';
		}
		if ( array_key_exists( 'regular_price', $fields ) ) {
			$product->set_regular_price( self::sanitize_price( $fields['regular_price'] ) );
			$changed[] = 'regular_price';
		}
		if ( array_key_exists( 'sale_price', $fields ) ) {
			$sale = $fields['sale_price'];
			$product->set_sale_price( ( '' === $sale || null === $sale ) ? '' : self::sanitize_price( $sale ) );
			$changed[] = 'sale_price';
		}
		if ( isset( $fields['name'] ) ) {
			$name = self::plain_text( (string) $fields['name'], 200 );
			if ( '' === $name ) {
				throw new InvalidArgumentException( 'name must not be empty.' );
			}
			$product->set_name( $name );
			$changed[] = 'name';
		}
		if ( isset( $fields['sku'] ) ) {
			$product->set_sku( self::plain_text( (string) $fields['sku'], 80 ) );
			$changed[] = 'sku';
		}
		if ( isset( $fields['short_description'] ) ) {
			$text = (string) $fields['short_description'];
			if ( function_exists( 'wp_kses_post' ) ) {
				$text = wp_kses_post( $text );
			}
			$product->set_short_description( self::plain_text( wp_strip_all_tags( $text ), 2000 ) );
			$changed[] = 'short_description';
		}
		if ( isset( $fields['category_ids'] ) ) {
			$ids = is_array( $fields['category_ids'] ) ? $fields['category_ids'] : array();
			$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
			$ids = array_values( array_filter( $ids, static fn( $n ) => $n > 0 ) );
			$product->set_category_ids( $ids );
			$changed[] = 'category_ids';
		}

		if ( ! $changed ) {
			throw new InvalidArgumentException( 'No allowed product fields were provided.' );
		}

		$product->save();

		return array(
			'id'      => $id,
			'changed' => $changed,
			'product' => self::summarize_product( wc_get_product( $id ), false ),
		);
	}

	public static function list_coupons( int $limit = 20, int $offset = 0 ): array {
		self::require_woo();
		$limit  = max( 1, min( $limit, self::MAX_COUPONS ) );
		$offset = max( 0, $offset );
		$posts  = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$items = array();
		foreach ( $posts as $post ) {
			$coupon = new WC_Coupon( $post->ID );
			$items[] = self::summarize_coupon( $coupon );
		}

		return array(
			'items' => $items,
			'limit' => $limit,
			'count' => count( $items ),
		);
	}

	public static function get_coupon( int $id ): array {
		self::require_woo();
		$coupon = new WC_Coupon( $id );
		if ( ! $coupon->get_id() ) {
			throw new InvalidArgumentException( 'Coupon not found.' );
		}

		return self::summarize_coupon( $coupon );
	}

	public static function list_shipping_zones(): array {
		self::require_woo();
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			throw new RuntimeException( 'WooCommerce shipping is not available.' );
		}

		$zones = array();
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			$zones[] = self::summarize_shipping_zone( $zone );
		}
		$rest = WC_Shipping_Zones::get_zone( 0 );
		if ( $rest ) {
			$zones[] = self::summarize_shipping_zone(
				array(
					'id'               => 0,
					'zone_name'        => $rest->get_zone_name(),
					'zone_order'       => $rest->get_zone_order(),
					'zone_locations'   => $rest->get_zone_locations(),
					'shipping_methods' => $rest->get_shipping_methods(),
				)
			);
		}

		return array(
			'zones' => $zones,
			'count' => count( $zones ),
		);
	}

	/**
	 * @param mixed $raw Price value.
	 */
	public static function sanitize_price( $raw ): string {
		$value = trim( (string) $raw );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^\d+(\.\d{1,6})?$/', $value ) ) {
			throw new InvalidArgumentException( 'Price must be a non-negative number.' );
		}
		return $value;
	}

	/**
	 * @param mixed $raw Option value.
	 * @return mixed
	 */
	private static function first_option( array $keys ) {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}
		foreach ( $keys as $key ) {
			$value = get_option( $key );
			if ( is_array( $value ) && $value ) {
				return $value;
			}
		}
		return null;
	}

	private static function require_woo(): void {
		if ( ! self::woo_active() ) {
			throw new RuntimeException( 'WooCommerce is not active.' );
		}
	}

	private static function woo_active(): bool {
		return function_exists( 'wc_get_orders' ) && class_exists( 'WC_Order' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function summarize_product( WC_Product $product, bool $detail ): array {
		$cats = array();
		if ( function_exists( 'wc_get_product_term_ids' ) && function_exists( 'get_term' ) ) {
			foreach ( wc_get_product_term_ids( $product->get_id(), 'product_cat' ) as $term_id ) {
				$term = get_term( (int) $term_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$cats[] = $term->name;
				}
			}
		}

		$out = array(
			'id'                 => $product->get_id(),
			'name'               => $product->get_name(),
			'sku'                => $product->get_sku(),
			'type'               => $product->get_type(),
			'status'             => $product->get_status(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'featured'           => $product->get_featured(),
			'regular_price'      => $product->get_regular_price(),
			'sale_price'         => $product->get_sale_price(),
			'price'              => $product->get_price(),
			'stock_status'       => $product->get_stock_status(),
			'manage_stock'       => $product->get_manage_stock(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'categories'         => $cats,
		);

		if ( ! $detail ) {
			return $out;
		}

		$images = array();
		if ( function_exists( 'wp_get_attachment_url' ) ) {
			$image_id = $product->get_image_id();
			if ( $image_id ) {
				$url = wp_get_attachment_url( (int) $image_id );
				if ( $url ) {
					$images[] = $url;
				}
			}
			foreach ( array_slice( $product->get_gallery_image_ids(), 0, 6 ) as $gid ) {
				$url = wp_get_attachment_url( (int) $gid );
				if ( $url ) {
					$images[] = $url;
				}
			}
		}

		$out['permalink']         = $product->get_permalink();
		$out['short_description'] = self::plain_text( wp_strip_all_tags( $product->get_short_description() ), 400 );
		$out['description']       = self::plain_text( wp_strip_all_tags( $product->get_description() ), 800 );
		$out['images']            = $images;
		$out['weight']            = $product->get_weight();
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function summarize_coupon( WC_Coupon $coupon ): array {
		$expires = $coupon->get_date_expires();
		return array(
			'id'                 => $coupon->get_id(),
			'code'               => $coupon->get_code(),
			'amount'             => $coupon->get_amount(),
			'discount_type'      => $coupon->get_discount_type(),
			'individual_use'     => $coupon->get_individual_use(),
			'usage_count'        => $coupon->get_usage_count(),
			'usage_limit'        => $coupon->get_usage_limit(),
			'date_expires'       => $expires ? $expires->date( 'c' ) : '',
			'product_ids'        => array_values( array_map( 'intval', $coupon->get_product_ids() ) ),
			'minimum_amount'     => $coupon->get_minimum_amount(),
			'maximum_amount'     => $coupon->get_maximum_amount(),
			'free_shipping'      => $coupon->get_free_shipping(),
			'email_restriction_count' => count( $coupon->get_email_restrictions() ),
		);
	}

	/**
	 * @param array<string, mixed> $zone Zone array from WC_Shipping_Zones::get_zones().
	 * @return array<string, mixed>
	 */
	private static function summarize_shipping_zone( array $zone ): array {
		$locations = array();
		foreach ( (array) ( $zone['zone_locations'] ?? array() ) as $location ) {
			$code = is_object( $location ) ? ( $location->code ?? '' ) : ( $location['code'] ?? '' );
			$type = is_object( $location ) ? ( $location->type ?? '' ) : ( $location['type'] ?? '' );
			$locations[] = array(
				'code' => (string) $code,
				'type' => (string) $type,
			);
		}

		$methods = array();
		foreach ( (array) ( $zone['shipping_methods'] ?? array() ) as $method ) {
			if ( ! is_object( $method ) ) {
				continue;
			}
			$row = array(
				'id'        => (int) ( $method->instance_id ?? 0 ),
				'method_id' => (string) ( $method->id ?? '' ),
				'title'     => self::plain_text( (string) ( $method->title ?? $method->method_title ?? '' ), 80 ),
				'enabled'   => ! empty( $method->enabled ) && 'yes' === $method->enabled,
			);
			if ( isset( $method->instance_settings['cost'] ) && is_scalar( $method->instance_settings['cost'] ) ) {
				$row['cost'] = (string) $method->instance_settings['cost'];
			}
			$methods[] = $row;
		}

		return array(
			'id'        => (int) ( $zone['id'] ?? $zone['zone_id'] ?? 0 ),
			'name'      => (string) ( $zone['zone_name'] ?? '' ),
			'order'     => (int) ( $zone['zone_order'] ?? 0 ),
			'locations' => $locations,
			'methods'   => $methods,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function summarize_order( WC_Order $order, bool $detail ): array {
		$email_sent = $order->get_meta( '_new_order_email_sent' );
		if ( '' === $email_sent && function_exists( 'wc_get_container' ) ) {
			$email_sent = $order->get_meta( 'new_order_email_sent' );
		}

		$out = array(
			'id'                    => $order->get_id(),
			'status'                => $order->get_status(),
			'total'                 => $order->get_total(),
			'currency'              => $order->get_currency(),
			'date_created'          => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '',
			'billing_email'         => $order->get_billing_email(),
			'payment_method'        => $order->get_payment_method(),
			'payment_method_title'  => $order->get_payment_method_title(),
			'customer_id'           => $order->get_customer_id(),
			'wcpay_mode'            => (string) $order->get_meta( '_wcpay_mode' ),
			'new_order_email_sent'  => in_array( (string) $email_sent, array( '1', 'true', 'yes' ), true ),
		);

		if ( ! $detail ) {
			return $out;
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
			);
		}

		$notes = array();
		if ( function_exists( 'wc_get_order_notes' ) ) {
			foreach ( array_slice( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ), 0, 8 ) as $note ) {
				$notes[] = array(
					'date'    => isset( $note->date_created ) ? (string) $note->date_created : '',
					'content' => self::plain_text( (string) ( $note->content ?? '' ), 240 ),
				);
			}
		}

		$out['shipping_total'] = $order->get_shipping_total();
		$out['line_items']     = $items;
		$out['notes']          = $notes;
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function recent_smtp_logs( int $limit ): array {
		if ( ! function_exists( 'wpagent_boot' ) && ! isset( $GLOBALS['wpdb'] ) ) {
			return array();
		}
		global $wpdb;
		if ( ! $wpdb ) {
			return array();
		}

		$table = $wpdb->prefix . 'fsmpt_email_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( 'SELECT id, `to`, `from`, subject, status, response, created_at FROM `' . esc_sql( $table ) . '` ORDER BY id DESC LIMIT ' . (int) $limit, ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$to = $row['to'] ?? '';
			if ( is_string( $to ) && self::looks_serialized( $to ) ) {
				$parsed = @unserialize( $to, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				$to     = is_array( $parsed ) ? (string) ( $parsed[0]['email'] ?? '' ) : '';
			}
			$out[] = array(
				'id'         => (int) ( $row['id'] ?? 0 ),
				'to'         => is_string( $to ) ? $to : '',
				'from'       => self::plain_text( (string) ( $row['from'] ?? '' ), 80 ),
				'subject'    => self::plain_text( (string) ( $row['subject'] ?? '' ), 120 ),
				'status'     => sanitize_key( (string) ( $row['status'] ?? '' ) ),
				'error'      => self::sanitize_mail_error( self::first_error_from_response( $row['response'] ?? '' ) ),
				'created_at' => (string) ( $row['created_at'] ?? '' ),
			);
		}

		return $out;
	}

	/**
	 * @param mixed $response Serialized FluentSMTP response.
	 */
	private static function first_error_from_response( $response ): string {
		if ( ! is_string( $response ) || '' === $response ) {
			return '';
		}
		if ( self::looks_serialized( $response ) ) {
			$parsed = @unserialize( $response, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			if ( is_array( $parsed ) ) {
				return (string) ( $parsed['message'] ?? '' );
			}
		}
		return $response;
	}

	private static function looks_serialized( string $value ): bool {
		if ( function_exists( 'is_serialized' ) ) {
			return is_serialized( $value );
		}
		return (bool) preg_match( '/^[adObis]:/', $value );
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
