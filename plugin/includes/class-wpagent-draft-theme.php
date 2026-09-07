<?php
/**
 * Clone / publish / delete a sandboxed draft of the active theme.
 *
 * @package WPAgent
 */

class WPAgent_Draft_Theme {

	public const OPTION = 'wpagent_draft_theme';
	public const SUFFIX = '-wpagent-draft';

	/**
	 * @return array{slug:string,source:string,created:int}|null
	 */
	public static function record(): ?array {
		$raw = get_option( self::OPTION, null );
		if ( ! is_array( $raw ) || empty( $raw['slug'] ) ) {
			return null;
		}
		return array(
			'slug'    => (string) $raw['slug'],
			'source'  => (string) ( $raw['source'] ?? '' ),
			'created' => (int) ( $raw['created'] ?? 0 ),
		);
	}

	public static function has_draft_record(): bool {
		return null !== self::record();
	}

	public static function draft_root(): ?string {
		$record = self::record();
		if ( ! $record ) {
			return null;
		}
		$dir = trailingslashit( get_theme_root() ) . $record['slug'];
		if ( ! is_dir( $dir ) ) {
			clearstatcache( true, $dir );
		}
		return is_dir( $dir ) ? $dir : null;
	}

	/**
	 * Directory file tools may read (draft if present, else active).
	 *
	 * If a draft is recorded but the directory is not visible yet, throw instead
	 * of silently reading the live theme (that produced draft:false right after create).
	 */
	public static function work_root(): string {
		$record = self::record();
		if ( ! $record ) {
			return get_stylesheet_directory();
		}
		$dir = trailingslashit( get_theme_root() ) . $record['slug'];
		if ( ! is_dir( $dir ) ) {
			clearstatcache( true, $dir );
		}
		if ( is_dir( $dir ) ) {
			return $dir;
		}
		throw new RuntimeException(
			sprintf(
				'Draft theme "%s" is recorded but its directory is not readable yet. Retry read_theme_file; do not edit the live theme.',
				$record['slug']
			)
		);
	}

	public static function work_slug(): string {
		$record = self::record();
		return $record['slug'] ?? get_stylesheet();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function status(): array {
		$record = self::record();
		$active = wp_get_theme();
		$out    = array(
			'active' => array(
				'slug'   => $active->get_stylesheet(),
				'name'   => $active->get( 'Name' ),
				'parent' => $active->parent() ? $active->get_template() : null,
			),
			'draft'  => null,
		);
		if ( $record ) {
			$theme = wp_get_theme( $record['slug'] );
			$out['draft'] = array(
				'slug'    => $record['slug'],
				'source'  => $record['source'],
				'created' => $record['created'],
				'exists'  => is_dir( trailingslashit( get_theme_root() ) . $record['slug'] ),
				'name'    => $theme->exists() ? $theme->get( 'Name' ) : $record['slug'],
			);
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function create(): array {
		self::assert_writes_allowed();
		$existing = self::record();
		if ( $existing && is_dir( trailingslashit( get_theme_root() ) . $existing['slug'] ) ) {
			return array(
				'status' => 'exists',
				'draft'  => self::status()['draft'],
				'note'   => 'A draft already exists. Delete it first or keep editing it.',
			);
		}

		$source = get_stylesheet();
		if ( WPAgent_Theme_Sandbox::is_blocked_parent( $source ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Refused: "%s" is a commercial builder parent theme. Draft+publish would be wiped by the next vendor update. Use the vendor child theme, or edit via the builder — not theme files.',
					$source
				)
			);
		}

		$slug   = $source . self::SUFFIX;
		$root   = trailingslashit( get_theme_root() );
		$from   = $root . $source;
		$to     = $root . $slug;
		if ( ! is_dir( $from ) ) {
			throw new RuntimeException( 'Active theme directory was not found.' );
		}
		if ( is_dir( $to ) ) {
			self::rmdir( $to );
		}
		self::copy_dir( $from, $to );
		self::label_style( $to, $source );
		clearstatcache( true, $to );
		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache();
		}

		$record = array(
			'slug'    => $slug,
			'source'  => $source,
			'created' => time(),
		);
		update_option( self::OPTION, $record, false );
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( self::OPTION, $record, 'options' );
		}
		WPAgent_Theme_Preview::rotate_token();

		return array(
			'status'      => 'created',
			'draft'       => self::status()['draft'],
			'draft_ready' => true,
			'note'        => 'Subsequent read_theme_file / write_theme_file / search_theme_files target this draft.',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function delete(): array {
		self::assert_writes_allowed();
		$record = self::record();
		if ( ! $record ) {
			return array( 'deleted' => false, 'note' => 'No draft theme exists.' );
		}
		$dir = trailingslashit( get_theme_root() ) . $record['slug'];
		if ( is_dir( $dir ) ) {
			self::rmdir( $dir );
		}
		delete_option( self::OPTION );
		delete_option( WPAgent_Theme_Preview::OPTION );
		return array( 'deleted' => true, 'slug' => $record['slug'] );
	}

	/**
	 * Overwrite the live source theme with the draft. Keeps a sibling backup.
	 *
	 * @return array<string, mixed>
	 */
	public static function publish(): array {
		self::assert_writes_allowed();
		$record = self::record();
		$draft  = self::draft_root();
		if ( ! $record || ! $draft ) {
			throw new RuntimeException( 'No draft theme to publish.' );
		}
		if ( $record['source'] !== get_stylesheet() ) {
			throw new RuntimeException( 'The live theme changed since the draft was created. Switch back or delete the draft.' );
		}

		$root   = trailingslashit( get_theme_root() );
		$live   = $root . $record['source'];
		$backup = $root . $record['source'] . '-wpagent-bak';
		if ( is_dir( $backup ) ) {
			self::rmdir( $backup );
		}
		self::copy_dir( $live, $backup );
		try {
			self::copy_dir( $draft, $live );
		} catch ( Throwable $e ) {
			self::copy_dir( $backup, $live );
			throw $e;
		}

		$health = WPAgent_Health_Probe::probe();
		if ( empty( $health['ok'] ) ) {
			self::copy_dir( $backup, $live );
			throw new RuntimeException( 'Publish rolled back: the homepage or admin-ajax probe failed after copying the draft.' );
		}

		self::rmdir( $draft );
		delete_option( self::OPTION );
		delete_option( WPAgent_Theme_Preview::OPTION );

		return array(
			'published' => true,
			'source'    => $record['source'],
			'backup'    => $record['source'] . '-wpagent-bak',
			'health'    => $health,
			'note'      => 'Live theme replaced. A sibling backup directory remains until the next publish.',
		);
	}

	private static function assert_writes_allowed(): void {
		if ( WPAgent_Theme_Sandbox::writes_locked() ) {
			throw new RuntimeException( 'Theme file writes are locked (DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS). Reads still work.' );
		}
	}

	private static function label_style( string $dir, string $source ): void {
		$path = $dir . '/style.css';
		if ( ! is_readable( $path ) ) {
			return;
		}
		$style = file_get_contents( $path );
		if ( ! is_string( $style ) ) {
			return;
		}
		$name  = wp_get_theme( $source )->get( 'Name' );
		$name  = preg_replace( '/(\s*\((?:WPAgent )?Draft\))+$/', '', (string) $name );
		$style = preg_replace( '/^Theme Name:\s*.+$/m', 'Theme Name: ' . $name . ' (WPAgent Draft)', $style );
		file_put_contents( $path, $style );
	}

	public static function copy_dir( string $from, string $to ): void {
		$from = rtrim( $from, '/\\' );
		$to   = rtrim( $to, '/\\' );
		if ( ! is_dir( $from ) ) {
			throw new RuntimeException( 'Source directory missing.' );
		}
		if ( ! is_dir( $to ) && ! mkdir( $to, 0755, true ) && ! is_dir( $to ) ) {
			throw new RuntimeException( 'Could not create theme directory.' );
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo ) {
				continue;
			}
			$relative = ltrim( str_replace( $from, '', $file->getPathname() ), '/\\' );
			if ( WPAgent_Theme_Sandbox::should_skip( $relative ) ) {
				continue;
			}
			$dest = $to . '/' . $relative;
			if ( $file->isDir() ) {
				if ( ! is_dir( $dest ) && ! mkdir( $dest, 0755, true ) && ! is_dir( $dest ) ) {
					throw new RuntimeException( 'Could not create ' . $relative );
				}
				continue;
			}
			if ( ! is_dir( dirname( $dest ) ) ) {
				mkdir( dirname( $dest ), 0755, true );
			}
			if ( ! copy( $file->getPathname(), $dest ) ) {
				throw new RuntimeException( 'Could not copy ' . $relative );
			}
		}
	}

	public static function rmdir( string $dir ): void {
		$dir = rtrim( $dir, '/\\' );
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $file ) {
			if ( $file instanceof SplFileInfo && $file->isDir() ) {
				rmdir( $file->getPathname() );
			} elseif ( $file instanceof SplFileInfo ) {
				unlink( $file->getPathname() );
			}
		}
		rmdir( $dir );
	}
}
