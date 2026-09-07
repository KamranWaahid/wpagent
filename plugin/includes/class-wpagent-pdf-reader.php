<?php
/**
 * Resolve a media-library PDF and extract text on this site.
 *
 * @package WPAgent
 */

class WPAgent_Pdf_Reader {

	/**
	 * @param array<string, mixed> $args id|path|url and optional max_pages.
	 * @return array<string, mixed>
	 */
	public static function read( array $args ): array {
		$max_pages = isset( $args['max_pages'] ) ? (int) $args['max_pages'] : WPAgent_Pdf::DEFAULT_PAGES;
		$source    = self::resolve_file( $args );
		$bytes     = (string) file_get_contents( $source['file'] );
		WPAgent_Pdf::assert_pdf_bytes( $bytes );
		$extracted = WPAgent_Pdf::extract_text( $bytes, $max_pages );

		return array_merge(
			$extracted,
			array(
				'id'       => $source['id'],
				'title'    => $source['title'],
				'path'     => $source['path'],
				'url'      => $source['url'],
				'mime'     => $source['mime'],
			)
		);
	}

	/**
	 * @param array<string, mixed> $args Request args.
	 * @return array{file:string,id:int,title:string,path:string,url:string,mime:string}
	 */
	private static function resolve_file( array $args ): array {
		$id   = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
		$path = isset( $args['path'] ) ? (string) $args['path'] : '';
		$url  = isset( $args['url'] ) ? (string) $args['url'] : '';

		$uploads = wp_upload_dir();
		$root    = (string) ( $uploads['basedir'] ?? '' );
		$baseurl = (string) ( $uploads['baseurl'] ?? '' );

		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( ! $post || 'attachment' !== $post->post_type ) {
				throw new InvalidArgumentException( sprintf( 'Attachment #%d was not found.', $id ) );
			}
			$file = get_attached_file( $id );
			if ( ! is_string( $file ) || '' === $file ) {
				throw new InvalidArgumentException( 'Attachment has no file on disk.' );
			}
			$relative = self::uploads_relative( $file, $root );
			$resolved = WPAgent_Pdf::resolve_uploads_path( $relative, $root );
			$mime     = (string) get_post_mime_type( $id );
			if ( $mime && 'application/pdf' !== $mime ) {
				throw new InvalidArgumentException( sprintf( 'Attachment #%d is not a PDF (%s).', $id, $mime ) );
			}
			return array(
				'file'  => $resolved,
				'id'    => $id,
				'title' => (string) $post->post_title,
				'path'  => $relative,
				'url'   => (string) wp_get_attachment_url( $id ),
				'mime'  => $mime ?: 'application/pdf',
			);
		}

		if ( '' !== $url ) {
			if ( function_exists( 'attachment_url_to_postid' ) ) {
				$from_url = attachment_url_to_postid( esc_url_raw( $url ) );
				if ( $from_url > 0 ) {
					return self::resolve_file( array( 'id' => $from_url ) );
				}
			}
			$path = WPAgent_Pdf::relative_from_url( $url, home_url( '/' ), $baseurl );
		}

		if ( '' === $path ) {
			throw new InvalidArgumentException( 'Provide id, path (uploads-relative), or a same-origin uploads URL.' );
		}

		$relative = WPAgent_Pdf::relative( $path );
		$resolved = WPAgent_Pdf::resolve_uploads_path( $relative, $root );
		return array(
			'file'  => $resolved,
			'id'    => 0,
			'title' => basename( $relative ),
			'path'  => $relative,
			'url'   => rtrim( $baseurl, '/' ) . '/' . $relative,
			'mime'  => 'application/pdf',
		);
	}

	private static function uploads_relative( string $file, string $root ): string {
		$real_root = realpath( $root );
		$real_file = realpath( $file );
		if ( ! $real_root || ! $real_file ) {
			throw new InvalidArgumentException( 'Attachment file is outside the uploads directory.' );
		}
		$prefix = rtrim( $real_root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		if ( ! str_starts_with( $real_file, $prefix ) ) {
			throw new InvalidArgumentException( 'Attachment file is outside the uploads directory.' );
		}
		return str_replace( '\\', '/', substr( $real_file, strlen( $prefix ) ) );
	}
}
