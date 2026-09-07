<?php
/**
 * PDF path sandbox and text extraction (no shell, uploads only).
 *
 * @package WPAgent
 */

class WPAgent_Pdf {

	public const MAX_BYTES     = 10485760;
	public const MAX_TEXT      = 50000;
	public const DEFAULT_PAGES = 20;
	public const MAX_PAGES     = 40;

	/**
	 * Keep a relative uploads path inside the uploads root.
	 *
	 * @throws InvalidArgumentException When the path escapes or is not a PDF.
	 */
	public static function resolve_uploads_path( string $path, string $uploads_root ): string {
		$relative = self::relative( $path );
		if ( ! str_ends_with( strtolower( $relative ), '.pdf' ) ) {
			throw new InvalidArgumentException( 'Only .pdf files can be read.' );
		}

		$root = rtrim( str_replace( '\\', '/', $uploads_root ), '/' );
		$full = $root . '/' . $relative;

		$real_root = realpath( $uploads_root );
		if ( ! $real_root || ! is_dir( $real_root ) ) {
			throw new InvalidArgumentException( 'Uploads directory is not available.' );
		}

		$real_file = realpath( $full );
		if ( ! $real_file || ! is_file( $real_file ) ) {
			throw new InvalidArgumentException( sprintf( 'PDF not found: %s', $relative ) );
		}

		$root_prefix = rtrim( $real_root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		if ( ! str_starts_with( $real_file, $root_prefix ) ) {
			throw new InvalidArgumentException( 'Path must stay inside the uploads directory.' );
		}

		return $real_file;
	}

	/**
	 * Map a media URL to an uploads-relative path, or throw.
	 */
	public static function relative_from_url( string $url, string $home_url, string $uploads_url ): string {
		$got  = self::parse_url( $url );
		$home = self::parse_url( $home_url );
		if ( empty( $got['host'] ) || empty( $home['host'] ) || strtolower( (string) $got['host'] ) !== strtolower( (string) $home['host'] ) ) {
			throw new InvalidArgumentException( 'URL must be on this WordPress site (same origin).' );
		}

		$path = isset( $got['path'] ) ? (string) $got['path'] : '';
		$base = self::parse_url( $uploads_url );
		$up   = isset( $base['path'] ) ? rtrim( (string) $base['path'], '/' ) : '/wp-content/uploads';
		if ( ! str_starts_with( $path, $up . '/' ) && $path !== $up ) {
			throw new InvalidArgumentException( 'URL must point at a file in wp-content/uploads.' );
		}

		$relative = ltrim( substr( $path, strlen( $up ) ), '/' );
		return self::relative( $relative );
	}

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
				throw new InvalidArgumentException( 'Path must stay inside the uploads directory.' );
			}
			$parts[] = $part;
		}
		if ( ! $parts ) {
			throw new InvalidArgumentException( 'path is required.' );
		}
		return implode( '/', $parts );
	}

	public static function assert_pdf_bytes( string $bytes ): void {
		if ( strlen( $bytes ) > self::MAX_BYTES ) {
			throw new InvalidArgumentException( 'PDF exceeds the 10 MiB read limit.' );
		}
		if ( ! str_starts_with( $bytes, '%PDF-' ) ) {
			throw new InvalidArgumentException( 'File is not a PDF (missing %PDF- header).' );
		}
	}

	/**
	 * @return array{text:string,page_count:int,truncated:bool,bytes:int,empty_reason:?string}
	 */
	public static function extract_text( string $bytes, int $max_pages = self::DEFAULT_PAGES ): array {
		self::assert_pdf_bytes( $bytes );
		$max_pages = max( 1, min( self::MAX_PAGES, $max_pages ) );

		$page_count = preg_match_all( '/\/Type\s*\/Page(?!s)/', $bytes );
		if ( $page_count < 1 ) {
			$page_count = 1;
		}

		$pieces = array();
		if ( preg_match_all( '/(<<[^>]*>>)\s*stream\r?\n(.*?)endstream/s', $bytes, $matches, PREG_SET_ORDER ) ) {
			$seen = 0;
			foreach ( $matches as $match ) {
				$decoded = self::decode_stream( $match[1], $match[2] );
				$text    = self::strings_from_content( $decoded );
				if ( '' === $text ) {
					continue;
				}
				$pieces[] = $text;
				++$seen;
				if ( $seen >= $max_pages ) {
					break;
				}
			}
		}

		if ( array() === $pieces ) {
			$fallback = self::strings_from_content( $bytes );
			if ( '' !== $fallback ) {
				$pieces[] = $fallback;
			}
		}

		$text = trim( preg_replace( "/[ \t]+/u", ' ', implode( "\n", $pieces ) ) ?? implode( "\n", $pieces ) );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text ) ?? $text;
		$truncated = strlen( $text ) > self::MAX_TEXT;
		$text      = substr( $text, 0, self::MAX_TEXT );

		return array(
			'text'         => $text,
			'page_count'   => (int) $page_count,
			'truncated'    => $truncated,
			'bytes'        => strlen( $bytes ),
			'empty_reason' => '' === $text ? 'No extractable text (scanned image or unsupported encoding).' : null,
		);
	}

	private static function decode_stream( string $dict, string $data ): string {
		$data = ltrim( $data, "\r\n" );
		if ( ! preg_match( '/\/FlateDecode/', $dict ) ) {
			return $data;
		}
		if ( ! function_exists( 'gzuncompress' ) ) {
			return $data;
		}
		$try = @gzuncompress( $data );
		if ( false === $try ) {
			$try = @gzinflate( $data );
		}
		return is_string( $try ) ? $try : $data;
	}

	private static function strings_from_content( string $content ): string {
		$out = array();
		if ( preg_match_all( '/\((?:\\\\.|[^\\\\)])*\)\s*Tj/', $content, $matches ) ) {
			foreach ( $matches[0] as $op ) {
				if ( preg_match( '/^\((.*)\)\s*Tj$/s', $op, $cap ) ) {
					$out[] = self::unescape_pdf_string( $cap[1] );
				}
			}
		}
		if ( preg_match_all( '/\[(.*?)\]\s*TJ/s', $content, $matches ) ) {
			foreach ( $matches[1] as $array ) {
				$piece = '';
				if ( preg_match_all( '/\((?:\\\\.|[^\\\\)])*\)/', $array, $parts ) ) {
					foreach ( $parts[0] as $part ) {
						$piece .= self::unescape_pdf_string( substr( $part, 1, -1 ) );
					}
				}
				if ( '' !== $piece ) {
					$out[] = $piece;
				}
			}
		}
		return implode( "\n", array_filter( $out, static fn( $s ) => '' !== $s ) );
	}

	public static function unescape_pdf_string( string $raw ): string {
		$out  = '';
		$len  = strlen( $raw );
		for ( $i = 0; $i < $len; $i++ ) {
			if ( '\\' !== $raw[ $i ] ) {
				$out .= $raw[ $i ];
				continue;
			}
			$next = $raw[ $i + 1 ] ?? '';
			$map  = array(
				'n'  => "\n",
				'r'  => "\r",
				't'  => "\t",
				'b'  => "\x08",
				'f'  => "\x0c",
				'('  => '(',
				')'  => ')',
				'\\' => '\\',
			);
			if ( isset( $map[ $next ] ) ) {
				$out .= $map[ $next ];
				++$i;
				continue;
			}
			if ( preg_match( '/^[0-7]{1,3}/', substr( $raw, $i + 1, 3 ), $oct ) ) {
				$out .= chr( octdec( $oct[0] ) );
				$i   += strlen( $oct[0] );
				continue;
			}
			$out .= $next;
			++$i;
		}

		if ( str_starts_with( $out, "\xfe\xff" ) && function_exists( 'mb_convert_encoding' ) ) {
			$converted = mb_convert_encoding( substr( $out, 2 ), 'UTF-8', 'UTF-16BE' );
			if ( is_string( $converted ) ) {
				return $converted;
			}
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>|false
	 */
	private static function parse_url( string $url ) {
		if ( function_exists( 'wp_parse_url' ) ) {
			return wp_parse_url( $url );
		}
		return parse_url( $url );
	}
}
