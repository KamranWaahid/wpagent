<?php
/**
 * Read/write/search files inside the theme work root (draft preferred).
 *
 * @package WPAgent
 */

class WPAgent_Theme_Files {

	/**
	 * @return array{root:string,slug:string,draft:bool,files:string[],truncated:bool}
	 */
	public static function list_files( ?string $glob = null, ?string $prefix = null ): array {
		$out       = array();
		$truncated = false;
		foreach ( self::iterate_relative_files() as $relative ) {
			if ( $prefix && ! WPAgent_Theme_Sandbox::matches_prefix( $relative, $prefix ) ) {
				continue;
			}
			if ( $glob && ! WPAgent_Theme_Sandbox::match_glob( $relative, $glob ) ) {
				continue;
			}
			$out[] = $relative;
			if ( count( $out ) >= WPAgent_Theme_Sandbox::MAX_FILES ) {
				$truncated = true;
				break;
			}
		}
		sort( $out );
		$payload = self::list_payload( $out, $truncated );
		if ( $truncated && ! $glob && ! $prefix ) {
			$payload['note'] .= ' Pass glob (e.g. inc/*.php) or prefix (e.g. inc/) to list a subset.';
		}
		if ( $glob ) {
			$payload['glob'] = $glob;
		}
		if ( $prefix ) {
			$payload['prefix'] = $prefix;
		}
		return $payload;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function read( string $path ): array {
		$relative = WPAgent_Theme_Sandbox::relative( $path );
		$full     = self::absolute( $relative );
		if ( ! is_readable( $full ) ) {
			throw new InvalidArgumentException( sprintf( 'File not found: %s', $relative ) );
		}
		$bytes = filesize( $full );
		if ( $bytes > WPAgent_Theme_Sandbox::MAX_BYTES ) {
			throw new InvalidArgumentException( 'File exceeds the 512 KiB read limit.' );
		}
		return array(
			'path'    => $relative,
			'slug'    => WPAgent_Draft_Theme::work_slug(),
			'draft'   => WPAgent_Draft_Theme::has_draft_record(),
			'content' => (string) file_get_contents( $full ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function write( string $path, string $content ): array {
		if ( WPAgent_Theme_Sandbox::writes_locked() ) {
			throw new RuntimeException( 'Theme file writes are locked on this host.' );
		}
		if ( ! WPAgent_Draft_Theme::draft_root() ) {
			throw new RuntimeException( 'Create a draft theme before writing files. The live theme is not writable through WPAgent.' );
		}
		$relative = WPAgent_Theme_Sandbox::relative( $path );
		if ( ! WPAgent_Theme_Sandbox::extension_allowed( $relative ) ) {
			throw new InvalidArgumentException( 'That file extension is not allowed.' );
		}
		if ( strlen( $content ) > WPAgent_Theme_Sandbox::MAX_BYTES ) {
			throw new InvalidArgumentException( 'File exceeds the 512 KiB write limit.' );
		}
		if ( str_ends_with( strtolower( $relative ), '.php' ) ) {
			WPAgent_Php_Guard::assert_valid( $content, $relative );
		}
		$full = self::absolute( $relative );
		$dir  = dirname( $full );
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new RuntimeException( 'Could not create directories for ' . $relative );
		}
		if ( false === file_put_contents( $full, $content ) ) {
			throw new RuntimeException( 'Could not write ' . $relative );
		}
		return array(
			'path'  => $relative,
			'slug'  => WPAgent_Draft_Theme::work_slug(),
			'draft' => true,
			'bytes' => strlen( $content ),
		);
	}

	/**
	 * @return array{query:string,matches:array<int, array{path:string,line:int,text:string}>,truncated:bool}
	 */
	public static function search( string $query, ?string $path = null, ?string $exclude_glob = null ): array {
		if ( '' === $query ) {
			throw new InvalidArgumentException( 'query is required.' );
		}
		$exclude   = WPAgent_Theme_Sandbox::exclude_globs( $exclude_glob );
		$hits      = array();
		$scanned   = 0;
		$truncated = false;
		foreach ( self::iterate_relative_files() as $relative ) {
			if ( $path && ! WPAgent_Theme_Sandbox::matches_prefix( $relative, $path ) ) {
				continue;
			}
			if ( WPAgent_Theme_Sandbox::is_excluded( $relative, $exclude ) ) {
				continue;
			}
			++$scanned;
			if ( $scanned > WPAgent_Theme_Sandbox::SEARCH_FILE_SCAN ) {
				$truncated = true;
				break;
			}
			$full = self::absolute( $relative );
			$body = @file_get_contents( $full ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! is_string( $body ) || ! str_contains( $body, $query ) ) {
				continue;
			}
			$lines = preg_split( '/\R/', $body ) ?: array();
			foreach ( $lines as $i => $line ) {
				if ( ! str_contains( $line, $query ) ) {
					continue;
				}
				$hits[] = array(
					'path' => $relative,
					'line' => $i + 1,
					'text' => substr( $line, 0, 200 ),
				);
				if ( count( $hits ) >= WPAgent_Theme_Sandbox::SEARCH_HITS ) {
					return self::search_payload( $query, $path, $exclude, $hits, true, $scanned );
				}
			}
		}
		return self::search_payload( $query, $path, $exclude, $hits, $truncated, $scanned );
	}

	/**
	 * @param array<int, string>|null $exclude Exclude globs.
	 * @param array<int, array{path:string,line:int,text:string}> $hits Hits.
	 * @return array<string, mixed>
	 */
	private static function search_payload( string $query, ?string $path, $exclude, array $hits, bool $truncated, int $scanned ): array {
		return array(
			'query'         => $query,
			'path'          => $path,
			'exclude_glob'  => $exclude,
			'matches'       => $hits,
			'truncated'     => $truncated,
			'scanned_files' => $scanned,
			'scoped_to'     => 'active_or_draft_theme',
			'not_searched'  => array( 'mu-plugins', 'plugins' ),
		);
	}

	/**
	 * @return \Generator<int, string>
	 */
	private static function iterate_relative_files(): \Generator {
		$root     = WPAgent_Draft_Theme::work_root();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
				continue;
			}
			$relative = ltrim( str_replace( $root, '', $file->getPathname() ), '/\\' );
			$relative = str_replace( '\\', '/', $relative );
			if ( WPAgent_Theme_Sandbox::should_skip( $relative ) ) {
				continue;
			}
			if ( ! WPAgent_Theme_Sandbox::extension_allowed( $relative ) ) {
				continue;
			}
			yield $relative;
		}
	}

	private static function absolute( string $relative ): string {
		$root      = rtrim( WPAgent_Draft_Theme::work_root(), '/\\' );
		$full      = $root . '/' . $relative;
		$real_root = realpath( $root );
		$real_file = realpath( $full );
		if ( $real_file && $real_root && ! str_starts_with( $real_file, $real_root ) ) {
			throw new InvalidArgumentException( 'Path must stay inside the theme directory.' );
		}
		return $full;
	}

	/**
	 * @param string[] $files
	 * @return array{root:string,slug:string,draft:bool,files:string[],truncated:bool,note:string}
	 */
	private static function list_payload( array $files, bool $truncated ): array {
		return array(
			'root'      => WPAgent_Draft_Theme::work_slug(),
			'slug'      => WPAgent_Draft_Theme::work_slug(),
			'draft'     => WPAgent_Draft_Theme::has_draft_record(),
			'files'     => $files,
			'truncated' => $truncated,
			'note'      => WPAgent_Draft_Theme::has_draft_record()
				? 'Listing the draft sandbox.'
				: 'No draft yet — listing the live theme read-only. create_draft_theme before writing.',
		);
	}
}
