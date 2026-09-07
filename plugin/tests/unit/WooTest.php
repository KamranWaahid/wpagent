<?php
/**
 * WooCommerce / mail helper tests (no WordPress, no WooCommerce).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class WooTest extends TestCase {

	public function test_gateway_flags_extract_yesno_and_title_only(): void {
		$flags = WPAgent_Woo::gateway_flags(
			array(
				'enabled'        => 'yes',
				'test_mode'      => 'no',
				'title'          => 'WooPayments',
				'publishable_key' => 'pk_live_secret',
				'secret_key'     => 'sk_live_secret',
			),
			array(
				'enabled'   => 'yesno',
				'test_mode' => 'yesno',
				'title'     => 'title',
			)
		);
		$this->assertTrue( $flags['present'] );
		$this->assertTrue( $flags['enabled'] );
		$this->assertFalse( $flags['test_mode'] );
		$this->assertSame( 'WooPayments', $flags['title'] );
		$this->assertArrayNotHasKey( 'publishable_key', $flags );
		$this->assertArrayNotHasKey( 'secret_key', $flags );
	}

	public function test_gateway_flags_missing_blob(): void {
		$flags = WPAgent_Woo::gateway_flags( null, array( 'enabled' => 'yesno' ) );
		$this->assertFalse( $flags['present'] );
	}

	public function test_allowed_test_recipients(): void {
		$this->assertTrue( WPAgent_Woo::is_allowed_test_recipient( 'admin@example.com', 'admin@example.com', 'example.com' ) );
		$this->assertTrue( WPAgent_Woo::is_allowed_test_recipient( 'info@example.com', 'admin@other.test', 'www.example.com' ) );
		$this->assertTrue( WPAgent_Woo::is_allowed_test_recipient( 'shop@example.com', 'admin@other.test', 'example.com', array( 'shop@example.com' ) ) );
		$this->assertFalse( WPAgent_Woo::is_allowed_test_recipient( 'customer@gmail.com', 'admin@example.com', 'example.com' ) );
		$this->assertFalse( WPAgent_Woo::is_allowed_test_recipient( 'not-an-email', 'admin@example.com', 'example.com' ) );
	}

	public function test_smtp_settings_drop_secrets(): void {
		$out = WPAgent_Woo::summarize_smtp_settings(
			array(
				'connections' => array(
					array(
						'host'       => 'smtp.hostinger.com',
						'port'       => 465,
						'encryption' => 'ssl',
						'username'   => 'info@example.com',
						'password'   => 'super-secret',
						'api_key'    => 'sk_xxx',
						'token'      => 'tok',
						'provider'   => 'smtp',
					),
				),
			)
		);
		$this->assertTrue( $out['configured'] );
		$this->assertSame( 'smtp.hostinger.com', $out['host'] );
		$this->assertSame( 465, $out['port'] );
		$this->assertSame( 'info@example.com', $out['username'] );
		$this->assertSame( 'smtp', $out['provider'] );
		$this->assertArrayNotHasKey( 'password', $out );
		$this->assertArrayNotHasKey( 'api_key', $out );
		$this->assertArrayNotHasKey( 'token', $out );
	}

	public function test_sanitize_price_accepts_decimals(): void {
		$this->assertSame( '49.90', WPAgent_Woo::sanitize_price( '49.90' ) );
		$this->assertSame( '', WPAgent_Woo::sanitize_price( '' ) );
	}

	public function test_sanitize_price_rejects_junk(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Woo::sanitize_price( '-1' );
	}

	public function test_sanitize_mail_error_redacts_password(): void {
		$out = WPAgent_Woo::sanitize_mail_error( 'AUTH failed password=hunter2 host=smtp.example.com' );
		$this->assertStringContainsString( 'password=[redacted]', $out );
		$this->assertStringNotContainsString( 'hunter2', $out );
	}
}
