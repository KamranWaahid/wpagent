<?php
/**
 * File-level snapshot for plugin/theme updates.
 *
 * @package WPAgent
 */

class WPAgent_File_Manifest {

	public const MAX_FILES = 400;

	/**
	 * Hash files under a directory (relative paths).
	 *
	 * @return array{root:string,file_count:int,truncated:bool,files:array<string,string>}
	 */
	public static function snapshot( string $root, int $max_files = self::MAX_FILES ): array {
		$root  = rtrim( $root, '/\\' );
		$files = array();
		$truncated = false;

		if ( ! is_dir( $root ) ) {
			return array(
				'root'       => $root,
				'file_count' => 0,
				'truncated'  => false,
				'files'      => array(),
			);
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
				continue;
			}
			$relative = ltrim( str_replace( $root, '', $file->getPathname() ), '/\\' );
			if ( self::should_skip( $relative ) ) {
				continue;
			}
			if ( count( $files ) >= $max_files ) {
				$truncated = true;
				break;
			}
			$files[ $relative ] = md5_file( $file->getPathname() ) ?: '';
		}

		ksort( $files );

		return array(
			'root'       => $root,
			'file_count' => count( $files ),
			'truncated'  => $truncated,
			'files'      => $files,
		);
	}

	/**
	 * @param array{files:array<string,string>} $before Before snapshot.
	 * @param array{files:array<string,string>} $after  After snapshot.
	 * @return array{added:string[],removed:string[],changed:string[]}
	 */
	public static function diff( array $before, array $after ): array {
		$old = $before['files'] ?? array();
		$new = $after['files'] ?? array();
		$added   = array_values( array_diff( array_keys( $new ), array_keys( $old ) ) );
		$removed = array_values( array_diff( array_keys( $old ), array_keys( $new ) ) );
		$changed = array();
		foreach ( $old as $path => $hash ) {
			if ( isset( $new[ $path ] ) && $new[ $path ] !== $hash ) {
				$changed[] = $path;
			}
		}

		return array(
			'added'   => $added,
			'removed' => $removed,
			'changed' => $changed,
		);
	}

	private static function should_skip( string $relative ): bool {
		$normalized = strtolower( str_replace( '\\', '/', $relative ) );
		foreach ( array( 'node_modules/', 'vendor/', '.git/', '.svn/' ) as $skip ) {
			if ( str_starts_with( $normalized, $skip ) || str_contains( $normalized, '/' . $skip ) ) {
				return true;
			}
		}
		return false;
	}
}
