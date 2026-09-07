<?php
/**
 * Token-efficient before/after snippets for search-replace previews.
 *
 * @package WPAgent
 */

class WPAgent_Diff {

	public const MAX_ROWS    = 8;
	public const CONTEXT     = 60;

	/**
	 * Build a handful of human-readable diffs for one field.
	 *
	 * @return array<int, array{before:string,after:string,unified:string}>
	 */
	public static function field_snippets( string $original, string $search, string $replace, int $max = 3 ): array {
		if ( '' === $search || ! str_contains( $original, $search ) ) {
			return array();
		}

		$out  = array();
		$offset = 0;
		$len  = strlen( $search );

		while ( count( $out ) < $max ) {
			$pos = strpos( $original, $search, $offset );
			if ( false === $pos ) {
				break;
			}
			$start   = max( 0, $pos - self::CONTEXT );
			$end     = min( strlen( $original ), $pos + $len + self::CONTEXT );
			$before  = ( $start > 0 ? '…' : '' ) . substr( $original, $start, $end - $start ) . ( $end < strlen( $original ) ? '…' : '' );
			$after   = str_replace( $search, $replace, $before );
			$out[]   = array(
				'before'   => $before,
				'after'    => $after,
				'unified'  => "- {$before}\n+ {$after}",
			);
			$offset = $pos + max( 1, $len );
		}

		return $out;
	}
}
