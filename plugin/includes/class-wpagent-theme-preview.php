<?php
/**
 * Swap stylesheet/template when a valid preview token is present.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Theme_Preview {

	public const OPTION = 'wpagent_preview_token';
	public const QUERY  = 'wpagent_preview';

	public static function hooks(): void {
		add_filter( 'stylesheet', array( self::class, 'stylesheet' ) );
		add_filter( 'template', array( self::class, 'template' ) );
		self::maybe_isolate();
	}

	public static function maybe_isolate(): void {
		if ( self::requested_slug() ) {
			WPAgent_Preview_Isolation::install();
		}
	}

	public static function rotate_token(): string {
		$token = wp_generate_password( 24, false );
		update_option( self::OPTION, $token, false );
		WPAgent_Preview_Isolation::clear();
		return $token;
	}

	public static function token(): string {
		$existing = (string) get_option( self::OPTION, '' );
		return '' !== $existing ? $existing : self::rotate_token();
	}

	/**
	 * @return array{url:string,token:string,slug:?string}
	 */
	public static function preview(): array {
		$record = WPAgent_Draft_Theme::record();
		if ( ! $record ) {
			throw new RuntimeException( 'Create a draft theme before requesting a preview URL.' );
		}
		$url = add_query_arg( self::QUERY, self::token(), home_url( '/' ) );
		return array(
			'url'        => $url,
			'token'      => self::token(),
			'slug'       => $record['slug'],
			'note'       => 'This URL renders the draft. The live theme is unchanged until publish_draft_theme. Content writes (trash, delete, post_status=trash) from draft PHP are blocked during preview.',
			'isolation'  => WPAgent_Preview_Isolation::summary(),
		);
	}

	public static function requested_slug(): ?string {
		$token = isset( $_GET[ self::QUERY ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return null;
		}
		$expected = (string) get_option( self::OPTION, '' );
		if ( ! $expected || ! hash_equals( $expected, $token ) ) {
			return null;
		}
		$record = WPAgent_Draft_Theme::record();
		if ( ! $record ) {
			return null;
		}
		return $record['slug'];
	}

	public static function stylesheet( string $stylesheet ): string {
		$slug = self::requested_slug();
		return $slug ?: $stylesheet;
	}

	public static function template( string $template ): string {
		$slug = self::requested_slug();
		if ( ! $slug ) {
			return $template;
		}
		$theme = wp_get_theme( $slug );
		if ( $theme->exists() && $theme->parent() ) {
			return $theme->get_template();
		}
		return $slug;
	}
}
