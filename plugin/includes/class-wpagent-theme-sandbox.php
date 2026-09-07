<?php
/**
 * Path and extension rules for theme file tools.
 *
 * @package WPAgent
 */

class WPAgent_Theme_Sandbox {

	public const MAX_FILES              = 400;
	public const MAX_BYTES              = 524288;
	public const SEARCH_HITS            = 40;
	public const SEARCH_FILE_SCAN       = 8000;
	public const DEFAULT_SEARCH_EXCLUDE = 'samples/**';

	/**
	 * Commercial parent themes: publishing overwrites the vendor directory,
	 * and the next theme update wipes those edits. Child themes are allowed.
	 *
	 * @return string[]
	 */
	public static function blocked_parents(): array {
		return array( 'divi', 'extra', 'avada', 'enfold', 'flatsome', 'betheme', 'bridge', 'salient', 'the7', 'x' );
	}

	public static function is_blocked_parent( string $slug ): bool {
		return in_array( strtolower( $slug ), self::blocked_parents(), true );
	}

	/**
	 * @return string[]
	 */
	public static function allowed_extensions(): array {
		return array( 'php', 'css', 'scss', 'sass', 'less', 'js', 'json', 'html', 'htm', 'txt', 'md', 'svg', 'xml', 'pot', 'po', 'mo' );
	}

	/**
	 * Normalize a relative theme path or throw.
	 *
	 * @throws InvalidArgumentException When the path escapes the theme root.
	 */
	public static function relative( string $path ): string {
		$path = str_replace( '\\', '/', $path );
		$path = ltrim( $path, '/' );
		if ( '' === $path || str_contains( $path, "\0" ) ) {
			throw new InvalidArgumentException( 'path is required.' );
		}
		$parts = array();
		foreach ( explode( '/', $path ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				throw new InvalidArgumentException( 'Path must stay inside the theme directory.' );
			}
			$parts[] = $part;
		}
		if ( ! $parts ) {
			throw new InvalidArgumentException( 'path is required.' );
		}
		return implode( '/', $parts );
	}

	public static function extension_allowed( string $relative ): bool {
		$ext = strtolower( (string) pathinfo( $relative, PATHINFO_EXTENSION ) );
		return in_array( $ext, self::allowed_extensions(), true );
	}

	public static function should_skip( string $relative ): bool {
		$normalized = strtolower( str_replace( '\\', '/', $relative ) );
		foreach ( array( 'node_modules/', 'vendor/', '.git/', '.svn/', '.wpagent-bak/' ) as $skip ) {
			if ( str_starts_with( $normalized, $skip ) || str_contains( $normalized, '/' . $skip ) ) {
				return true;
			}
		}
		return false;
	}

	public static function writes_locked(): bool {
		return ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
			|| ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT );
	}

	public static function matches_prefix( string $path, string $prefix ): bool {
		$prefix = str_replace( '\\', '/', ltrim( $prefix, '/' ) );
		$prefix = rtrim( $prefix, '/' );
		if ( '' === $prefix ) {
			return true;
		}
		return $path === $prefix || str_starts_with( $path, $prefix . '/' );
	}

	public static function match_glob( string $path, string $glob ): bool {
		$path = str_replace( '\\', '/', $path );
		$glob = str_replace( '\\', '/', ltrim( $glob, '/' ) );
		if ( '' === $glob ) {
			return true;
		}
		return (bool) preg_match( self::glob_to_regex( $glob ), $path );
	}

	/**
	 * Comma-separated globs. Empty string means no exclude. Null uses the default.
	 *
	 * @return string[]
	 */
	public static function exclude_globs( ?string $exclude_glob ): array {
		if ( null === $exclude_glob ) {
			return array( self::DEFAULT_SEARCH_EXCLUDE );
		}
		$exclude_glob = trim( $exclude_glob );
		if ( '' === $exclude_glob ) {
			return array();
		}
		$out = array();
		foreach ( explode( ',', $exclude_glob ) as $part ) {
			$part = trim( str_replace( '\\', '/', $part ) );
			if ( '' !== $part ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/**
	 * @param string[] $globs
	 */
	public static function is_excluded( string $path, array $globs ): bool {
		foreach ( $globs as $glob ) {
			if ( self::match_glob( $path, $glob ) ) {
				return true;
			}
		}
		return false;
	}

	public static function glob_to_regex( string $glob ): string {
		$regex = '';
		$len   = strlen( $glob );
		for ( $i = 0; $i < $len; $i++ ) {
			$char = $glob[ $i ];
			if ( '*' === $char ) {
				if ( $i + 1 < $len && '*' === $glob[ $i + 1 ] ) {
					$regex .= '.*';
					++$i;
					continue;
				}
				$regex .= '[^/]*';
				continue;
			}
			if ( '?' === $char ) {
				$regex .= '[^/]';
				continue;
			}
			$regex .= preg_quote( $char, '#' );
		}
		return '#^' . $regex . '$#i';
	}
}
