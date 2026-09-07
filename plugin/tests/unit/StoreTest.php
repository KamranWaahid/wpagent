<?php
/**
 * Store helper tests (no WordPress / WooCommerce).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class StoreTest extends TestCase {

	public function test_report_dates(): void {
		$this->assertSame( '2026-09-01', WPAgent_Store::sanitize_report_date( '2026-09-01' ) );
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Store::sanitize_report_date( '01-09-2026' );
	}

	public function test_coupon_codes(): void {
		$this->assertSame( 'save10', WPAgent_Store::sanitize_coupon_code( 'SAVE10' ) );
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Store::sanitize_coupon_code( 'ab' );
	}

	public function test_woo_email_catalog(): void {
		$ids = WPAgent_Store::woo_email_ids();
		$this->assertArrayHasKey( 'new_order', $ids );
		$this->assertArrayHasKey( 'customer_processing_order', $ids );
		$this->assertSame( 'woocommerce_new_order_settings', $ids['new_order'] );
	}
}
