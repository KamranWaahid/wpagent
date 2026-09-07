<?php
/**
 * Options allowlist tests.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class OptionsAllowlistTest extends TestCase {

	public function test_arbitrary_keys_are_rejected(): void {
		$this->assertFalse( WPAgent_Options_Allowlist::can_read( 'active_plugins' ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'siteurl' ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'admin_email' ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_read( 'cron' ) );
	}

	public function test_safe_keys_are_readable(): void {
		$this->assertTrue( WPAgent_Options_Allowlist::can_read( 'blogname' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'blogname' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_read( 'siteurl' ) );
	}

	public function test_custom_extras_are_allowed(): void {
		$extras = array(
			array(
				'key'         => 'my_custom_flag',
				'writable'    => true,
				'description' => 'A safe extra',
			),
		);
		$this->assertTrue( WPAgent_Options_Allowlist::can_read( 'my_custom_flag', $extras ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'my_custom_flag', $extras ) );
	}

	public function test_forbidden_extras_are_rejected(): void {
		$extras = array(
			array( 'key' => 'active_plugins', 'writable' => true ),
			array( 'key' => 'cron', 'writable' => true ),
			array( 'key' => 'siteurl', 'writable' => true ),
			array( 'key' => 'wpagent_encrypted_secrets', 'writable' => true ),
		);
		$this->assertFalse( WPAgent_Options_Allowlist::can_read( 'active_plugins', $extras ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_read( 'cron', $extras ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'siteurl', $extras ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'wpagent_encrypted_secrets', $extras ) );
		$this->assertSame( array(), WPAgent_Options_Allowlist::normalize_extras( $extras ) );
	}

	public function test_cannot_make_builtin_read_only_keys_writable(): void {
		$extras = array(
			array( 'key' => 'siteurl', 'writable' => true ),
			array( 'key' => 'home', 'writable' => true ),
			array( 'key' => 'admin_email', 'writable' => true ),
		);
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'siteurl', $extras ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'home', $extras ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'admin_email', $extras ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_read( 'siteurl', $extras ) );
	}

	public function test_hsts_plugin_settings_are_allowlisted(): void {
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'hsts_max_age' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'hsts_mode_strict_transport_security' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'hsts_disable_x_frame_options' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'hsts_show_migration_notice_v3' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_read( 'hsts_detected_server' ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'hsts_detected_server' ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'hsts_htaccess_written_headers' ) );
	}

	public function test_hsts_probe_token_is_forbidden(): void {
		$this->assertFalse( WPAgent_Options_Allowlist::can_read( 'hsts_probe_token' ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'hsts_probe_token' ) );
		$this->assertSame(
			array(),
			WPAgent_Options_Allowlist::normalize_extras(
				array( array( 'key' => 'hsts_probe_token', 'writable' => true ) )
			)
		);
	}

	public function test_woocommerce_store_keys_are_writable(): void {
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'woocommerce_shipping_hide_rates_when_free' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'woocommerce_allowed_countries' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'woocommerce_specific_allowed_countries' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'woocommerce_flat_rate_6_settings' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'woocommerce_free_shipping_4_settings' ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'woocommerce_currency' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::can_read( 'woocommerce_currency' ) );
	}

	public function test_payment_and_stripe_settings_stay_blocked(): void {
		$this->assertFalse( WPAgent_Options_Allowlist::can_read( 'woocommerce_stripe_settings' ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_write( 'woocommerce_stripe_settings' ) );
		$this->assertTrue( WPAgent_Options_Allowlist::is_blocked_key( 'woocommerce_stripe_settings' ) );
		$this->assertFalse( WPAgent_Options_Allowlist::can_read( 'not_a_real_option' ) );
		$denied = WPAgent_Options_Allowlist::reject_read( 'not_a_real_option' );
		$this->assertSame( 'not_on_allowlist', $denied->details['reason'] );
		$this->assertSame( 'Run option list', $denied->details['hint'] );
	}

	public function test_keyed_storage_roundtrip(): void {
		$stored = WPAgent_Options_Allowlist::normalize_extras(
			array(
				array( 'key' => 'extra_one', 'writable' => true, 'description' => 'One' ),
			)
		);
		$this->assertArrayHasKey( 'extra_one', $stored );
		$again = WPAgent_Options_Allowlist::normalize_extras( $stored );
		$this->assertTrue( WPAgent_Options_Allowlist::can_write( 'extra_one', $again ) );
	}
}
