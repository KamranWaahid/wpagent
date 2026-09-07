<?php
/**
 * Famous-plugin public-ID extractors (no WordPress).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class IntegrationsTest extends TestCase {

	public function test_secret_keys_and_values(): void {
		$this->assertTrue( WPAgent_Integrations::looks_secret_key( 'access_token' ) );
		$this->assertTrue( WPAgent_Integrations::looks_secret_key( 'client_secret' ) );
		$this->assertFalse( WPAgent_Integrations::looks_secret_key( 'measurementID' ) );
		$this->assertTrue( WPAgent_Integrations::looks_secret_value( 'sk_live_abc' ) );
		$this->assertTrue( WPAgent_Integrations::looks_secret_value( 'ya29.oauth' ) );
		$this->assertFalse( WPAgent_Integrations::looks_secret_value( 'G-ABCDEF12' ) );
	}

	public function test_public_tracking_ids(): void {
		$this->assertTrue( WPAgent_Integrations::is_public_tracking_id( 'G-ABCDEF12' ) );
		$this->assertTrue( WPAgent_Integrations::is_public_tracking_id( 'GTM-XXXX' ) );
		$this->assertTrue( WPAgent_Integrations::is_public_tracking_id( '123456789012345' ) );
		$this->assertFalse( WPAgent_Integrations::is_public_tracking_id( 'sk_live_not_an_id' ) );
		$this->assertFalse( WPAgent_Integrations::is_public_tracking_id( 'EAAGFacebookToken' ) );
	}

	public function test_pick_public_drops_secrets(): void {
		$out = WPAgent_Integrations::pick_public(
			array(
				'measurementID' => 'G-ABCDEF12',
				'useSnippet'    => true,
				'access_token'  => 'secret',
				'api_key'       => 'AIzaSomethingLong',
				'oauth'         => 'ya29.token',
			),
			array( 'measurementID', 'useSnippet', 'access_token', 'api_key', 'oauth' )
		);
		$this->assertTrue( $out['present'] );
		$this->assertSame( 'G-ABCDEF12', $out['measurementID'] );
		$this->assertTrue( $out['useSnippet'] );
		$this->assertArrayNotHasKey( 'access_token', $out );
		$this->assertArrayNotHasKey( 'api_key', $out );
		$this->assertSame( array( 'set' => true ), $out['oauth'] );
	}

	public function test_keep_public_ids_redacts_non_ids(): void {
		$block = array(
			'present'       => true,
			'measurementID' => 'not-a-real-id',
			'containerID'   => 'GTM-ABC',
		);
		WPAgent_Integrations::keep_public_ids( $block, array( 'measurementID', 'containerID' ) );
		$this->assertSame( array( 'set' => true ), $block['measurementID'] );
		$this->assertSame( 'GTM-ABC', $block['containerID'] );
	}
}
