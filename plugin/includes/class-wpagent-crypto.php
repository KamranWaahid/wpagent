<?php
/**
 * AES-256-GCM encryption for site-side secrets (API keys). Application Passwords
 * are never stored here — they live in WordPress's own application-password tables.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Crypto {

	private const CIPHER = 'aes-256-gcm';
	private const IV_LEN = 12;
	private const TAG_LEN = 16;

	/**
	 * Encrypt plaintext with a per-site derived key.
	 *
	 * @throws RuntimeException If OpenSSL cannot encrypt.
	 */
	public static function encrypt( string $plaintext, ?string $key = null, string $aad = '' ): string {
		$key = $key ?? self::derived_key();
		$iv  = random_bytes( self::IV_LEN );
		$tag = '';

		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			$aad,
			self::TAG_LEN
		);

		if ( false === $ciphertext || strlen( $tag ) !== self::TAG_LEN ) {
			throw new RuntimeException( 'Failed to encrypt payload with AES-256-GCM.' );
		}

		return base64_encode( $iv . $tag . $ciphertext );
	}

	/**
	 * Decrypt a payload produced by encrypt().
	 *
	 * @throws RuntimeException If decryption fails.
	 */
	public static function decrypt( string $payload, ?string $key = null, string $aad = '' ): string {
		$raw = base64_decode( $payload, true );
		if ( false === $raw || strlen( $raw ) < ( self::IV_LEN + self::TAG_LEN + 1 ) ) {
			throw new RuntimeException( 'Invalid ciphertext.' );
		}

		$iv         = substr( $raw, 0, self::IV_LEN );
		$tag        = substr( $raw, self::IV_LEN, self::TAG_LEN );
		$ciphertext = substr( $raw, self::IV_LEN + self::TAG_LEN );
		$key        = $key ?? self::derived_key();

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			$aad
		);

		if ( false === $plaintext ) {
			throw new RuntimeException( 'Failed to decrypt payload.' );
		}

		return $plaintext;
	}

	/**
	 * Store a named secret (encrypted at rest).
	 */
	public static function put_secret( string $name, string $value ): void {
		$secrets = get_option( 'wpagent_encrypted_secrets', array() );
		if ( ! is_array( $secrets ) ) {
			$secrets = array();
		}

		$secrets[ sanitize_key( $name ) ] = self::encrypt( $value, null, self::aad() );
		update_option( 'wpagent_encrypted_secrets', $secrets, false );
	}

	/**
	 * Read a named secret.
	 */
	public static function get_secret( string $name ): ?string {
		$secrets = get_option( 'wpagent_encrypted_secrets', array() );
		if ( ! is_array( $secrets ) ) {
			return null;
		}

		$key = sanitize_key( $name );
		if ( empty( $secrets[ $key ] ) || ! is_string( $secrets[ $key ] ) ) {
			return null;
		}

		try {
			return self::decrypt( $secrets[ $key ], null, self::aad() );
		} catch ( RuntimeException $e ) {
			return null;
		}
	}

	/**
	 * 32-byte key derived from WordPress salts + the per-site WPAgent salt.
	 */
	public static function derived_key( ?string $site_salt = null, ?string $wp_salt = null ): string {
		$site_salt = $site_salt ?? (string) get_option( 'wpagent_crypto_salt', '' );
		$wp_salt   = $wp_salt ?? ( function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'wpagent-test-salt' );

		return hash( 'sha256', $wp_salt . '|' . $site_salt, true );
	}

	/**
	 * Additional authenticated data binds ciphertext to this site URL.
	 */
	private static function aad(): string {
		return function_exists( 'home_url' ) ? home_url( '/' ) : 'wpagent';
	}
}
