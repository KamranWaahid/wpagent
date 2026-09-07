<?php
/**
 * Extra WooCommerce store ops that never touch payment secrets or customer mail.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Store {

	public const MAX = 50;

	/**
	 * @return array<string, string>
	 */
	public static function woo_email_ids(): array {
		return array(
			'new_order'                   => 'woocommerce_new_order_settings',
			'cancelled_order'             => 'woocommerce_cancelled_order_settings',
			'failed_order'                => 'woocommerce_failed_order_settings',
			'customer_on_hold_order'      => 'woocommerce_customer_on_hold_order_settings',
			'customer_processing_order'   => 'woocommerce_customer_processing_order_settings',
			'customer_completed_order'    => 'woocommerce_customer_completed_order_settings',
			'customer_refunded_order'     => 'woocommerce_customer_refunded_order_settings',
			'customer_invoice'            => 'woocommerce_customer_invoice_settings',
			'customer_note'               => 'woocommerce_customer_note_settings',
			'customer_reset_password'     => 'woocommerce_customer_reset_password_settings',
			'customer_new_account'        => 'woocommerce_customer_new_account_settings',
		);
	}

	public static function sanitize_report_date( string $value ): string {
		$value = trim( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			throw new InvalidArgumentException( 'Dates must be YYYY-MM-DD.' );
		}
		return $value;
	}

	public static function sanitize_coupon_code( string $code ): string {
		$code = strtolower( trim( $code ) );
		$code = preg_replace( '/[^a-z0-9_-]/', '', $code ) ?? '';
		if ( strlen( $code ) < 3 || strlen( $code ) > 40 ) {
			throw new InvalidArgumentException( 'Coupon code must be 3–40 letters, numbers, _ or -.' );
		}
		return $code;
	}

	public static function list_customers( int $limit = 20, int $offset = 0, string $search = '' ): array {
		self::require_woo();
		$limit  = max( 1, min( $limit, self::MAX ) );
		$offset = max( 0, $offset );
		$args   = array(
			'role'    => 'customer',
			'number'  => $limit,
			'offset'  => $offset,
			'orderby' => 'registered',
			'order'   => 'DESC',
		);
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$items = array();
		foreach ( get_users( $args ) as $user ) {
			$items[] = self::summarize_customer( (int) $user->ID );
		}

		return array(
			'items' => $items,
			'limit' => $limit,
			'count' => count( $items ),
		);
	}

	public static function get_customer( int $id ): array {
		self::require_woo();
		$user = get_user_by( 'id', $id );
		if ( ! $user ) {
			throw new InvalidArgumentException( 'Customer not found.' );
		}

		return self::summarize_customer( $id );
	}

	public static function store_report( string $after, string $before ): array {
		self::require_woo();
		$after  = self::sanitize_report_date( $after );
		$before = self::sanitize_report_date( $before );
		if ( $after > $before ) {
			throw new InvalidArgumentException( 'after must be on or before before.' );
		}

		$count     = 0;
		$revenue   = 0.0;
		$by_status = array();
		$page      = 1;

		do {
			$orders = wc_get_orders(
				array(
					'limit'        => 100,
					'page'         => $page,
					'date_created' => $after . '...' . $before,
					'return'       => 'objects',
					'type'         => 'shop_order',
				)
			);
			$batch = is_array( $orders ) ? $orders : array();
			foreach ( $batch as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				$status = $order->get_status();
				$by_status[ $status ] = ( $by_status[ $status ] ?? 0 ) + 1;
				++$count;
				if ( in_array( $status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
					$revenue += (float) $order->get_total();
				}
			}
			++$page;
		} while ( count( $batch ) === 100 && $page <= 20 );

		return array(
			'after'      => $after,
			'before'     => $before,
			'order_count'=> $count,
			'revenue'    => round( $revenue, 2 ),
			'currency'   => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'by_status'  => $by_status,
			'truncated'  => 20 < $page && 100 === count( $batch ),
			'note'       => 'Revenue sums processing, completed, and on-hold. No card data.',
		);
	}

	public static function list_low_stock( int $threshold = 2, int $limit = 20 ): array {
		self::require_woo();
		$threshold = max( 0, min( $threshold, 1000 ) );
		$limit     = max( 1, min( $limit, self::MAX ) );
		$scanned   = wc_get_products(
			array(
				'limit'  => 200,
				'status' => array( 'publish', 'private' ),
				'return' => 'objects',
			)
		);
		$items = array();
		foreach ( (array) $scanned as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$qty    = $product->get_stock_quantity();
			$status = $product->get_stock_status();
			$low    = 'outofstock' === $status
				|| ( $product->get_manage_stock() && null !== $qty && (int) $qty <= $threshold );
			if ( ! $low ) {
				continue;
			}
			$items[] = array(
				'id'             => $product->get_id(),
				'name'           => $product->get_name(),
				'sku'            => $product->get_sku(),
				'stock_status'   => $status,
				'stock_quantity' => $qty,
			);
			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		return array(
			'items'     => $items,
			'threshold' => $threshold,
			'count'     => count( $items ),
		);
	}

	public static function list_reviews( int $limit = 20, int $offset = 0, string $status = '' ): array {
		self::require_woo();
		$limit  = max( 1, min( $limit, self::MAX ) );
		$offset = max( 0, $offset );
		$args   = array(
			'type'   => 'review',
			'number' => $limit,
			'offset' => $offset,
		);
		if ( '' !== $status ) {
			$args['status'] = sanitize_key( $status );
		}

		$items = array();
		foreach ( get_comments( $args ) as $comment ) {
			$items[] = self::summarize_review( $comment );
		}

		return array(
			'items' => $items,
			'limit' => $limit,
			'count' => count( $items ),
		);
	}

	public static function moderate_review( int $id, string $status ): array {
		self::require_woo();
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'approve', 'hold', 'trash' ), true ) ) {
			throw new InvalidArgumentException( 'Review status must be approve, hold, or trash.' );
		}
		$comment = get_comment( $id );
		if ( ! $comment || 'review' !== $comment->comment_type ) {
			throw new InvalidArgumentException( 'Review not found.' );
		}
		$before = $comment->comment_approved;
		wp_set_comment_status( $id, $status );

		return array(
			'id'     => $id,
			'before' => (string) $before,
			'status' => $status,
		);
	}

	/**
	 * @param array<string, mixed> $fields Coupon fields.
	 */
	public static function create_coupon( array $fields ): array {
		self::require_woo();
		$code = self::sanitize_coupon_code( (string) ( $fields['code'] ?? '' ) );
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		self::apply_coupon_fields( $coupon, $fields, true );
		$id = $coupon->save();
		if ( ! $id ) {
			throw new RuntimeException( 'Could not create coupon.' );
		}

		return self::summarize_saved_coupon( (int) $id );
	}

	/**
	 * @param array<string, mixed> $fields Coupon fields.
	 */
	public static function update_coupon( int $id, array $fields ): array {
		self::require_woo();
		$coupon = new WC_Coupon( $id );
		if ( ! $coupon->get_id() ) {
			throw new InvalidArgumentException( 'Coupon not found.' );
		}
		self::apply_coupon_fields( $coupon, $fields, false );
		$coupon->save();

		return self::summarize_saved_coupon( $id );
	}

	public static function list_variations( int $product_id ): array {
		self::require_woo();
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			throw new InvalidArgumentException( 'Product not found.' );
		}
		if ( ! $product->is_type( 'variable' ) ) {
			throw new InvalidArgumentException( 'Product is not variable.' );
		}

		$items = array();
		foreach ( $product->get_children() as $vid ) {
			$variation = wc_get_product( (int) $vid );
			if ( $variation instanceof WC_Product ) {
				$items[] = self::summarize_variation( $variation );
			}
		}

		return array(
			'product_id' => $product_id,
			'items'      => $items,
			'count'      => count( $items ),
		);
	}

	/**
	 * @param array<string, mixed> $fields Stock/price fields.
	 */
	public static function update_variation( int $id, array $fields ): array {
		self::require_woo();
		$product = wc_get_product( $id );
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'variation' ) ) {
			throw new InvalidArgumentException( 'Variation not found.' );
		}

		$changed = array();
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
			$product->set_stock_quantity( '' === $qty || null === $qty ? null : (int) $qty );
			$changed[] = 'stock_quantity';
		}
		if ( array_key_exists( 'regular_price', $fields ) ) {
			$product->set_regular_price( WPAgent_Woo::sanitize_price( $fields['regular_price'] ) );
			$changed[] = 'regular_price';
		}
		if ( array_key_exists( 'sale_price', $fields ) ) {
			$sale = $fields['sale_price'];
			$product->set_sale_price( ( '' === $sale || null === $sale ) ? '' : WPAgent_Woo::sanitize_price( $sale ) );
			$changed[] = 'sale_price';
		}
		if ( array_key_exists( 'status', $fields ) ) {
			$status = sanitize_key( (string) $fields['status'] );
			if ( ! in_array( $status, array( 'publish', 'private', 'draft' ), true ) ) {
				throw new InvalidArgumentException( 'Variation status must be publish, private, or draft.' );
			}
			$product->set_status( $status );
			$changed[] = 'status';
		}
		if ( ! $changed ) {
			throw new InvalidArgumentException( 'No allowed variation fields were provided.' );
		}
		$product->save();

		return array(
			'id'      => $id,
			'changed' => $changed,
			'variation' => self::summarize_variation( wc_get_product( $id ) ),
		);
	}

	public static function add_order_note( int $order_id, string $note ): array {
		self::require_woo();
		$note = trim( $note );
		if ( strlen( $note ) < 1 || strlen( $note ) > 1000 ) {
			throw new InvalidArgumentException( 'Note must be 1–1000 characters.' );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			throw new InvalidArgumentException( 'Order not found.' );
		}
		// Private staff note only — never emails the customer.
		$id = $order->add_order_note( $note, false, true );

		return array(
			'order_id' => $order_id,
			'note_id'  => (int) $id,
			'note'     => $note,
			'customer_notified' => false,
		);
	}

	public static function create_refund( int $order_id, string $amount, string $reason = '' ): array {
		self::require_woo();
		$amount = WPAgent_Woo::sanitize_price( $amount );
		if ( '' === $amount || (float) $amount <= 0 ) {
			throw new InvalidArgumentException( 'Refund amount must be greater than 0.' );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			throw new InvalidArgumentException( 'Order not found.' );
		}
		if ( (float) $amount > (float) $order->get_remaining_refund_amount() ) {
			throw new InvalidArgumentException( 'Amount exceeds the remaining refundable total.' );
		}

		$refund = wc_create_refund(
			array(
				'order_id'       => $order_id,
				'amount'         => $amount,
				'reason'         => substr( $reason, 0, 200 ),
				'refund_payment' => false,
			)
		);
		if ( is_wp_error( $refund ) ) {
			throw new RuntimeException( $refund->get_error_message() );
		}

		return array(
			'order_id'        => $order_id,
			'refund_id'       => $refund instanceof WC_Order_Refund ? $refund->get_id() : 0,
			'amount'          => $amount,
			'refund_payment'  => false,
			'note'            => 'Recorded in WooCommerce only. The payment gateway was not charged back.',
		);
	}

	public static function list_woo_emails(): array {
		self::require_woo();
		$items = array();
		foreach ( self::woo_email_ids() as $id => $option ) {
			$raw = function_exists( 'get_option' ) ? get_option( $option ) : null;
			$items[] = array(
				'id'      => $id,
				'enabled' => is_array( $raw ) && ( ( $raw['enabled'] ?? 'no' ) === 'yes' ),
				'subject' => is_array( $raw ) && is_string( $raw['subject'] ?? null ) ? substr( $raw['subject'], 0, 160 ) : '',
			);
		}

		return array( 'items' => $items );
	}

	public static function update_woo_email( string $id, bool $enabled ): array {
		self::require_woo();
		$map = self::woo_email_ids();
		if ( ! isset( $map[ $id ] ) ) {
			throw new InvalidArgumentException( 'Unknown Woo email id.' );
		}
		$option = $map[ $id ];
		$raw    = get_option( $option );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$raw['enabled'] = $enabled ? 'yes' : 'no';
		update_option( $option, $raw, false );

		return array(
			'id'      => $id,
			'enabled' => $enabled,
		);
	}

	public static function list_tax_rates( int $limit = 50 ): array {
		self::require_woo();
		global $wpdb;
		if ( ! $wpdb ) {
			return array( 'items' => array(), 'count' => 0 );
		}
		$limit = max( 1, min( $limit, 100 ) );
		$table = $wpdb->prefix . 'woocommerce_tax_rates';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( 'SELECT tax_rate_id, tax_rate_country, tax_rate_state, tax_rate, tax_rate_name, tax_rate_priority, tax_rate_class FROM `' . esc_sql( $table ) . '` ORDER BY tax_rate_priority ASC, tax_rate_id ASC LIMIT ' . (int) $limit, ARRAY_A );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'       => (int) ( $row['tax_rate_id'] ?? 0 ),
				'country'  => (string) ( $row['tax_rate_country'] ?? '' ),
				'state'    => (string) ( $row['tax_rate_state'] ?? '' ),
				'rate'     => (string) ( $row['tax_rate'] ?? '' ),
				'name'     => (string) ( $row['tax_rate_name'] ?? '' ),
				'priority' => (int) ( $row['tax_rate_priority'] ?? 0 ),
				'class'    => (string) ( $row['tax_rate_class'] ?? '' ),
			);
		}

		return array(
			'items' => $items,
			'count' => count( $items ),
		);
	}

	private static function require_woo(): void {
		if ( ! function_exists( 'wc_get_orders' ) || ! class_exists( 'WC_Order' ) ) {
			throw new RuntimeException( 'WooCommerce is not active.' );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function summarize_customer( int $id ): array {
		$out = array(
			'id'               => $id,
			'email'            => '',
			'first_name'       => '',
			'last_name'        => '',
			'display_name'     => '',
			'date_registered'  => '',
			'orders_count'     => 0,
			'total_spent'      => '0',
		);
		if ( class_exists( 'WC_Customer' ) ) {
			$customer = new WC_Customer( $id );
			$out['email']           = $customer->get_email();
			$out['first_name']      = $customer->get_first_name();
			$out['last_name']       = $customer->get_last_name();
			$out['display_name']    = $customer->get_display_name();
			$out['date_registered'] = $customer->get_date_created() ? $customer->get_date_created()->date( 'c' ) : '';
			$out['orders_count']    = (int) $customer->get_order_count();
			$out['total_spent']     = (string) $customer->get_total_spent();
			return $out;
		}
		$user = get_user_by( 'id', $id );
		if ( $user ) {
			$out['email']        = $user->user_email;
			$out['display_name'] = $user->display_name;
		}
		return $out;
	}

	/**
	 * @param WP_Comment $comment Review comment.
	 * @return array<string, mixed>
	 */
	private static function summarize_review( $comment ): array {
		$rating = get_comment_meta( (int) $comment->comment_ID, 'rating', true );
		$content = isset( $comment->comment_content ) ? wp_strip_all_tags( (string) $comment->comment_content ) : '';
		if ( function_exists( 'mb_substr' ) ) {
			$content = mb_substr( $content, 0, 280 );
		} else {
			$content = substr( $content, 0, 280 );
		}

		return array(
			'id'         => (int) $comment->comment_ID,
			'product_id' => (int) $comment->comment_post_ID,
			'author'     => (string) $comment->comment_author,
			'rating'     => is_numeric( $rating ) ? (int) $rating : null,
			'content'    => $content,
			'status'     => (string) $comment->comment_approved,
			'date'       => (string) $comment->comment_date_gmt,
		);
	}

	/**
	 * @param WC_Coupon            $coupon Coupon.
	 * @param array<string, mixed> $fields Incoming fields.
	 */
	private static function apply_coupon_fields( $coupon, array $fields, bool $creating ): void {
		if ( $creating && ! isset( $fields['amount'] ) ) {
			throw new InvalidArgumentException( 'amount is required.' );
		}
		if ( isset( $fields['amount'] ) ) {
			$coupon->set_amount( WPAgent_Woo::sanitize_price( $fields['amount'] ) );
		}
		if ( isset( $fields['discount_type'] ) ) {
			$type = sanitize_key( (string) $fields['discount_type'] );
			if ( ! in_array( $type, array( 'percent', 'fixed_cart', 'fixed_product' ), true ) ) {
				throw new InvalidArgumentException( 'discount_type must be percent, fixed_cart, or fixed_product.' );
			}
			$coupon->set_discount_type( $type );
		} elseif ( $creating ) {
			$coupon->set_discount_type( 'percent' );
		}
		if ( array_key_exists( 'usage_limit', $fields ) ) {
			$coupon->set_usage_limit( '' === $fields['usage_limit'] || null === $fields['usage_limit'] ? 0 : (int) $fields['usage_limit'] );
		}
		if ( array_key_exists( 'individual_use', $fields ) ) {
			$coupon->set_individual_use( ! empty( $fields['individual_use'] ) );
		}
		if ( array_key_exists( 'free_shipping', $fields ) ) {
			$coupon->set_free_shipping( ! empty( $fields['free_shipping'] ) );
		}
		if ( array_key_exists( 'minimum_amount', $fields ) ) {
			$coupon->set_minimum_amount( WPAgent_Woo::sanitize_price( $fields['minimum_amount'] ) );
		}
		if ( isset( $fields['date_expires'] ) && '' !== $fields['date_expires'] ) {
			$coupon->set_date_expires( self::sanitize_report_date( (string) $fields['date_expires'] ) );
		}
		if ( array_key_exists( 'enabled', $fields ) ) {
			$coupon->set_status( ! empty( $fields['enabled'] ) ? 'publish' : 'draft' );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function summarize_saved_coupon( int $id ): array {
		return WPAgent_Woo::get_coupon( $id );
	}

	/**
	 * @param WC_Product $product Variation.
	 * @return array<string, mixed>
	 */
	private static function summarize_variation( $product ): array {
		$attrs = array();
		foreach ( $product->get_attributes() as $key => $value ) {
			$attrs[ (string) $key ] = is_string( $value ) ? $value : '';
		}

		return array(
			'id'             => $product->get_id(),
			'parent_id'      => $product->get_parent_id(),
			'sku'            => $product->get_sku(),
			'status'         => $product->get_status(),
			'regular_price'  => $product->get_regular_price(),
			'sale_price'     => $product->get_sale_price(),
			'stock_status'   => $product->get_stock_status(),
			'manage_stock'   => $product->get_manage_stock(),
			'stock_quantity' => $product->get_stock_quantity(),
			'attributes'     => $attrs,
		);
	}
}
