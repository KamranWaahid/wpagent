<?php
/**
 * WooCommerce orders, payment flags, and mail probes.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_REST_Woo extends WPAgent_REST_Controller {

	public function register_routes(): void {
		$ns = WPAGENT_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/mail/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'mail_status' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_mail_status' ),
			)
		);

		register_rest_route(
			$ns,
			'/mail/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'send_test_mail' ),
				'permission_callback' => WPAgent_Permissions::callback( 'send_test_mail' ),
			)
		);

		register_rest_route(
			$ns,
			'/payments/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'payment_status' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_payment_status' ),
			)
		);

		register_rest_route(
			$ns,
			'/orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_orders' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_orders' ),
			)
		);

		register_rest_route(
			$ns,
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_products' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_products' ),
			)
		);

		register_rest_route(
			$ns,
			'/products/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_product' ),
					'permission_callback' => WPAgent_Permissions::callback( 'get_product' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_product' ),
					'permission_callback' => WPAgent_Permissions::callback( 'update_product' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/coupons',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_coupons' ),
					'permission_callback' => WPAgent_Permissions::callback( 'list_coupons' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_coupon' ),
					'permission_callback' => WPAgent_Permissions::callback( 'create_coupon' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/coupons/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_coupon' ),
					'permission_callback' => WPAgent_Permissions::callback( 'get_coupon' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_coupon' ),
					'permission_callback' => WPAgent_Permissions::callback( 'update_coupon' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/shipping-zones',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_shipping_zones' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_shipping_zones' ),
			)
		);

		register_rest_route(
			$ns,
			'/orders/(?P<id>\d+)/note',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_order_note' ),
				'permission_callback' => WPAgent_Permissions::callback( 'add_order_note' ),
			)
		);

		register_rest_route(
			$ns,
			'/orders/(?P<id>\d+)/refund',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_refund' ),
				'permission_callback' => WPAgent_Permissions::callback( 'create_refund' ),
			)
		);

		register_rest_route(
			$ns,
			'/orders/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_order' ),
					'permission_callback' => WPAgent_Permissions::callback( 'get_order' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_order' ),
					'permission_callback' => WPAgent_Permissions::callback( 'update_order' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_customers' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_customers' ),
			)
		);

		register_rest_route(
			$ns,
			'/customers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customer' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_customer' ),
			)
		);

		register_rest_route(
			$ns,
			'/reports/store',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'store_report' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_store_report' ),
			)
		);

		register_rest_route(
			$ns,
			'/products/low-stock',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_low_stock' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_low_stock' ),
			)
		);

		register_rest_route(
			$ns,
			'/products/(?P<id>\d+)/variations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_variations' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_variations' ),
			)
		);

		register_rest_route(
			$ns,
			'/variations/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_variation' ),
				'permission_callback' => WPAgent_Permissions::callback( 'update_variation' ),
			)
		);

		register_rest_route(
			$ns,
			'/reviews',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_reviews' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_reviews' ),
			)
		);

		register_rest_route(
			$ns,
			'/reviews/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'moderate_review' ),
				'permission_callback' => WPAgent_Permissions::callback( 'moderate_review' ),
			)
		);

		register_rest_route(
			$ns,
			'/woo-emails',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_woo_emails' ),
				'permission_callback' => WPAgent_Permissions::callback( 'get_woo_emails' ),
			)
		);

		register_rest_route(
			$ns,
			'/woo-emails/(?P<id>[a-z0-9_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_woo_email' ),
				'permission_callback' => WPAgent_Permissions::callback( 'update_woo_email' ),
			)
		);

		register_rest_route(
			$ns,
			'/tax-rates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_tax_rates' ),
				'permission_callback' => WPAgent_Permissions::callback( 'list_tax_rates' ),
			)
		);
	}

	public function mail_status( WP_REST_Request $request ) {
		return $this->execute(
			'get_mail_status',
			$request,
			static fn() => WPAgent_Woo::mail_status()
		);
	}

	public function send_test_mail( WP_REST_Request $request ) {
		return $this->execute(
			'send_test_mail',
			$request,
			static function () use ( $request ) {
				$to = $request->get_param( 'to' );
				return WPAgent_Woo::send_test_mail( is_string( $to ) && '' !== $to ? $to : null );
			},
			array( 'object_type' => 'mail' )
		);
	}

	public function payment_status( WP_REST_Request $request ) {
		return $this->execute(
			'get_payment_status',
			$request,
			static fn() => WPAgent_Woo::payment_status()
		);
	}

	public function list_orders( WP_REST_Request $request ) {
		return $this->execute(
			'list_orders',
			$request,
			static function () use ( $request ) {
				return WPAgent_Woo::list_orders(
					(int) ( $request->get_param( 'limit' ) ?: 20 ),
					(string) ( $request->get_param( 'status' ) ?: '' ),
					(int) ( $request->get_param( 'offset' ) ?: 0 )
				);
			}
		);
	}

	public function get_order( WP_REST_Request $request ) {
		return $this->execute(
			'get_order',
			$request,
			static fn() => WPAgent_Woo::get_order( (int) $request['id'] )
		);
	}

	public function update_order( WP_REST_Request $request ) {
		return $this->execute(
			'update_order',
			$request,
			static fn() => WPAgent_Woo::update_order(
				(int) $request['id'],
				(string) $request->get_param( 'status' )
			),
			array(
				'object_type' => 'order',
				'object_id'   => (int) $request['id'],
			)
		);
	}

	public function list_products( WP_REST_Request $request ) {
		return $this->execute(
			'list_products',
			$request,
			static function () use ( $request ) {
				return WPAgent_Woo::list_products(
					(int) ( $request->get_param( 'limit' ) ?: 20 ),
					(string) ( $request->get_param( 'status' ) ?: '' ),
					(string) ( $request->get_param( 'search' ) ?: '' ),
					(int) ( $request->get_param( 'offset' ) ?: 0 )
				);
			}
		);
	}

	public function get_product( WP_REST_Request $request ) {
		return $this->execute(
			'get_product',
			$request,
			static fn() => WPAgent_Woo::get_product( (int) $request['id'] )
		);
	}

	public function update_product( WP_REST_Request $request ) {
		return $this->execute(
			'update_product',
			$request,
			static function () use ( $request ) {
				$fields = array();
				foreach ( array( 'status', 'catalog_visibility', 'featured', 'stock_status', 'manage_stock', 'stock_quantity', 'regular_price', 'sale_price', 'name', 'sku', 'short_description', 'category_ids' ) as $key ) {
					if ( null !== $request->get_param( $key ) ) {
						$fields[ $key ] = $request->get_param( $key );
					}
				}
				return WPAgent_Woo::update_product( (int) $request['id'], $fields );
			},
			array(
				'object_type' => 'product',
				'object_id'   => (int) $request['id'],
			)
		);
	}

	public function list_coupons( WP_REST_Request $request ) {
		return $this->execute(
			'list_coupons',
			$request,
			static function () use ( $request ) {
				return WPAgent_Woo::list_coupons(
					(int) ( $request->get_param( 'limit' ) ?: 20 ),
					(int) ( $request->get_param( 'offset' ) ?: 0 )
				);
			}
		);
	}

	public function get_coupon( WP_REST_Request $request ) {
		return $this->execute(
			'get_coupon',
			$request,
			static fn() => WPAgent_Woo::get_coupon( (int) $request['id'] )
		);
	}

	public function list_shipping_zones( WP_REST_Request $request ) {
		return $this->execute(
			'list_shipping_zones',
			$request,
			static fn() => WPAgent_Woo::list_shipping_zones()
		);
	}

	public function add_order_note( WP_REST_Request $request ) {
		return $this->execute(
			'add_order_note',
			$request,
			static fn() => WPAgent_Store::add_order_note( (int) $request['id'], (string) $request->get_param( 'note' ) ),
			array(
				'object_type' => 'order',
				'object_id'   => (int) $request['id'],
			)
		);
	}

	public function create_refund( WP_REST_Request $request ) {
		return $this->execute(
			'create_refund',
			$request,
			static fn() => WPAgent_Store::create_refund(
				(int) $request['id'],
				(string) $request->get_param( 'amount' ),
				(string) ( $request->get_param( 'reason' ) ?: '' )
			),
			array(
				'object_type' => 'order',
				'object_id'   => (int) $request['id'],
			)
		);
	}

	public function list_customers( WP_REST_Request $request ) {
		return $this->execute(
			'list_customers',
			$request,
			static function () use ( $request ) {
				return WPAgent_Store::list_customers(
					(int) ( $request->get_param( 'limit' ) ?: 20 ),
					(int) ( $request->get_param( 'offset' ) ?: 0 ),
					(string) ( $request->get_param( 'search' ) ?: '' )
				);
			}
		);
	}

	public function get_customer( WP_REST_Request $request ) {
		return $this->execute(
			'get_customer',
			$request,
			static fn() => WPAgent_Store::get_customer( (int) $request['id'] )
		);
	}

	public function store_report( WP_REST_Request $request ) {
		return $this->execute(
			'get_store_report',
			$request,
			static fn() => WPAgent_Store::store_report(
				(string) $request->get_param( 'after' ),
				(string) $request->get_param( 'before' )
			)
		);
	}

	public function list_low_stock( WP_REST_Request $request ) {
		return $this->execute(
			'list_low_stock',
			$request,
			static fn() => WPAgent_Store::list_low_stock(
				(int) ( $request->get_param( 'threshold' ) ?: 2 ),
				(int) ( $request->get_param( 'limit' ) ?: 20 )
			)
		);
	}

	public function list_variations( WP_REST_Request $request ) {
		return $this->execute(
			'list_variations',
			$request,
			static fn() => WPAgent_Store::list_variations( (int) $request['id'] )
		);
	}

	public function update_variation( WP_REST_Request $request ) {
		return $this->execute(
			'update_variation',
			$request,
			static function () use ( $request ) {
				$fields = array();
				foreach ( array( 'status', 'stock_status', 'manage_stock', 'stock_quantity', 'regular_price', 'sale_price' ) as $key ) {
					if ( null !== $request->get_param( $key ) ) {
						$fields[ $key ] = $request->get_param( $key );
					}
				}
				return WPAgent_Store::update_variation( (int) $request['id'], $fields );
			},
			array(
				'object_type' => 'product',
				'object_id'   => (int) $request['id'],
			)
		);
	}

	public function list_reviews( WP_REST_Request $request ) {
		return $this->execute(
			'list_reviews',
			$request,
			static function () use ( $request ) {
				return WPAgent_Store::list_reviews(
					(int) ( $request->get_param( 'limit' ) ?: 20 ),
					(int) ( $request->get_param( 'offset' ) ?: 0 ),
					(string) ( $request->get_param( 'status' ) ?: '' )
				);
			}
		);
	}

	public function moderate_review( WP_REST_Request $request ) {
		return $this->execute(
			'moderate_review',
			$request,
			static fn() => WPAgent_Store::moderate_review( (int) $request['id'], (string) $request->get_param( 'status' ) ),
			array(
				'object_type' => 'review',
				'object_id'   => (int) $request['id'],
			)
		);
	}

	public function create_coupon( WP_REST_Request $request ) {
		return $this->execute(
			'create_coupon',
			$request,
			static fn() => WPAgent_Store::create_coupon( $request->get_params() ),
			array( 'object_type' => 'coupon' )
		);
	}

	public function update_coupon( WP_REST_Request $request ) {
		return $this->execute(
			'update_coupon',
			$request,
			static fn() => WPAgent_Store::update_coupon( (int) $request['id'], $request->get_params() ),
			array(
				'object_type' => 'coupon',
				'object_id'   => (int) $request['id'],
			)
		);
	}

	public function list_woo_emails( WP_REST_Request $request ) {
		return $this->execute(
			'get_woo_emails',
			$request,
			static fn() => WPAgent_Store::list_woo_emails()
		);
	}

	public function update_woo_email( WP_REST_Request $request ) {
		return $this->execute(
			'update_woo_email',
			$request,
			static fn() => WPAgent_Store::update_woo_email(
				(string) $request['id'],
				(bool) rest_sanitize_boolean( $request->get_param( 'enabled' ) )
			),
			array(
				'object_type' => 'woo_email',
				'object_id'   => 0,
			)
		);
	}

	public function list_tax_rates( WP_REST_Request $request ) {
		return $this->execute(
			'list_tax_rates',
			$request,
			static fn() => WPAgent_Store::list_tax_rates( (int) ( $request->get_param( 'limit' ) ?: 50 ) )
		);
	}
}
