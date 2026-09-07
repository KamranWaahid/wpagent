<?php
/**
 * AES-256-GCM crypto tests.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class CryptoTest extends TestCase {

	public function test_round_trip(): void {
		$key       = WPAgent_Crypto::derived_key( 'site-salt', 'wp-salt' );
		$encrypted = WPAgent_Crypto::encrypt( 'application-secret', $key, 'https://example.com/' );
		$plain     = WPAgent_Crypto::decrypt( $encrypted, $key, 'https://example.com/' );
		$this->assertSame( 'application-secret', $plain );
	}

	public function test_wrong_aad_fails(): void {
		$key       = WPAgent_Crypto::derived_key( 'site-salt', 'wp-salt' );
		$encrypted = WPAgent_Crypto::encrypt( 'secret', $key, 'https://a.example/' );
		$this->expectException( RuntimeException::class );
		WPAgent_Crypto::decrypt( $encrypted, $key, 'https://b.example/' );
	}

	public function test_wrong_key_fails(): void {
		$key_a     = WPAgent_Crypto::derived_key( 'salt-a', 'wp' );
		$key_b     = WPAgent_Crypto::derived_key( 'salt-b', 'wp' );
		$encrypted = WPAgent_Crypto::encrypt( 'secret', $key_a );
		$this->expectException( RuntimeException::class );
		WPAgent_Crypto::decrypt( $encrypted, $key_b );
	}

	public function test_ciphertext_is_not_plaintext(): void {
		$key       = WPAgent_Crypto::derived_key( 'site-salt', 'wp-salt' );
		$encrypted = WPAgent_Crypto::encrypt( 'visible-secret', $key );
		$this->assertStringNotContainsString( 'visible-secret', $encrypted );
	}
}
