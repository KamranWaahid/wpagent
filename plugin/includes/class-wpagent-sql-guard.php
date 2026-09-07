<?php
/**
 * Read-only SQL guard. Default-deny: only a single SELECT, no mutating keywords.
 *
 * @package WPAgent
 */

class WPAgent_SQL_Guard {

	public const DEFAULT_ROW_LIMIT = 100;
	public const MAX_ROW_LIMIT     = 500;

	/**
	 * Keywords that must never appear as SQL words (string literals are stripped first).
	 *
	 * @var string[]
	 */
	private const BLOCKED = array(
		'insert',
		'update',
		'delete',
		'drop',
		'alter',
		'create',
		'truncate',
		'replace',
		'grant',
		'revoke',
		'sleep',
		'benchmark',
		'get_lock',
		'release_lock',
		'extractvalue',
		'updatexml',
		'load_file',
		'outfile',
		'dumpfile',
		'into',
		'handler',
		'lock',
		'unlock',
		'call',
		'do',
		'set',
		'prepare',
		'execute',
		'deallocate',
		'rename',
		'comment',
		'analyze',
		'repair',
		'optimize',
		'flush',
		'shutdown',
		'kill',
		'change',
		'purge',
		'reset',
		'install',
		'uninstall',
		'import',
		'export',
		'backup',
		'restore',
		'commit',
		'rollback',
		'start',
		'begin',
		'savepoint',
		'release',
		'use',
		'attach',
		'detach',
		'pragma',
		'vacuum',
		'merge',
		'upsert',
		'copy',
		'load',
		'infile',
	);

	/**
	 * Validate a SQL string for read-only SELECT execution.
	 *
	 * @param string $sql   Candidate SQL.
	 * @param int    $limit Requested row limit.
	 * @return array{sql:string,limit:int}
	 *
	 * @throws InvalidArgumentException If the statement is not a safe SELECT.
	 */
	public static function assert_read_only( string $sql, int $limit = self::DEFAULT_ROW_LIMIT ): array {
		$sql = str_replace( "\0", '', trim( $sql ) );
		if ( '' === $sql ) {
			throw new InvalidArgumentException( 'SQL must not be empty.' );
		}

		if ( strlen( $sql ) > 5000 ) {
			throw new InvalidArgumentException( 'SQL exceeds the maximum length of 5000 characters.' );
		}

		$sql      = self::expand_version_comments( $sql );
		$stripped = self::strip_comments_and_literals( $sql );
		$compact  = strtolower( preg_replace( '/\s+/', ' ', trim( $stripped ) ) ?? '' );

		if ( str_contains( $compact, ';' ) ) {
			throw new InvalidArgumentException( 'Multiple statements are not allowed.' );
		}

		if ( ! preg_match( '/^select\b/', $compact ) ) {
			throw new InvalidArgumentException( 'Only SELECT statements are allowed.' );
		}

		foreach ( self::BLOCKED as $keyword ) {
			if ( preg_match( '/\b' . preg_quote( $keyword, '/' ) . '\b/', $compact ) ) {
				throw new InvalidArgumentException( sprintf( 'Blocked SQL keyword: %s', $keyword ) );
			}
		}

		$limit = max( 1, min( $limit, self::MAX_ROW_LIMIT ) );

		return array(
			'sql'   => $sql,
			'limit' => $limit,
		);
	}

	/**
	 * Expand MySQL version comments so /*!50000 DELETE ... * / is visible to keyword checks.
	 */
	public static function expand_version_comments( string $sql ): string {
		$expanded = preg_replace( '/\/\*!\d*\s*(.*?)\*\//s', ' $1 ', $sql );
		return is_string( $expanded ) ? $expanded : $sql;
	}

	/**
	 * Strip comments and quoted string literals so keyword checks ignore user data.
	 */
	public static function strip_comments_and_literals( string $sql ): string {
		$out      = '';
		$length   = strlen( $sql );
		$i        = 0;
		$in_s     = false;
		$in_d     = false;
		$in_line  = false;
		$in_block = false;

		while ( $i < $length ) {
			$ch   = $sql[ $i ];
			$next = $i + 1 < $length ? $sql[ $i + 1 ] : '';

			if ( $in_line ) {
				if ( "\n" === $ch ) {
					$in_line = false;
					$out    .= ' ';
				}
				++$i;
				continue;
			}

			if ( $in_block ) {
				if ( '*' === $ch && '/' === $next ) {
					$in_block = false;
					$i       += 2;
					$out     .= ' ';
					continue;
				}
				++$i;
				continue;
			}

			if ( $in_s ) {
				if ( "'" === $ch ) {
					if ( "'" === $next ) {
						$i += 2;
						continue;
					}
					$in_s = false;
				}
				++$i;
				continue;
			}

			if ( $in_d ) {
				if ( '"' === $ch ) {
					$in_d = false;
				}
				++$i;
				continue;
			}

			if ( '-' === $ch && '-' === $next ) {
				$in_line = true;
				$i      += 2;
				continue;
			}

			if ( '/' === $ch && '*' === $next ) {
				$in_block = true;
				$i       += 2;
				continue;
			}

			if ( '#' === $ch ) {
				$in_line = true;
				++$i;
				continue;
			}

			if ( "'" === $ch ) {
				$in_s = true;
				++$i;
				continue;
			}

			if ( '"' === $ch ) {
				$in_d = true;
				++$i;
				continue;
			}

			$out .= $ch;
			++$i;
		}

		return $out;
	}

	/**
	 * Wrap validated SQL with a hard LIMIT if one is not already present.
	 */
	public static function apply_limit( string $sql, int $limit ): string {
		$stripped = strtolower( self::strip_comments_and_literals( $sql ) );
		if ( preg_match( '/\blimit\s+\d+/', $stripped ) ) {
			return rtrim( $sql, "; \t\n\r" );
		}

		return rtrim( $sql, "; \t\n\r" ) . ' LIMIT ' . (int) $limit;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return array{read_only:bool,limit:int,row_count:int,truncated:bool,rows:array<int, array<string, mixed>>}
	 */
	public static function format_result( array $rows, int $limit ): array {
		$limit = max( 1, $limit );
		return array(
			'read_only' => true,
			'limit'     => $limit,
			'row_count' => count( $rows ),
			'truncated' => count( $rows ) >= $limit,
			'rows'      => self::redact_rows( $rows ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function redact_rows( array $rows ): array {
		foreach ( $rows as &$row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$option_name = (string) ( $row['option_name'] ?? $row['meta_key'] ?? '' );
			foreach ( $row as $col => $value ) {
				$col = (string) $col;
				if ( self::is_secret_column( $col ) ) {
					$row[ $col ] = self::redact_value( $value );
					continue;
				}
				if ( in_array( $col, array( 'option_value', 'meta_value' ), true ) ) {
					$row[ $col ] = self::redact_option_value( $option_name, $value );
				}
			}
		}
		unset( $row );
		return $rows;
	}

	private static function is_secret_column( string $col ): bool {
		return (bool) preg_match( '/(secret|password|api_key|auth_key)$/i', $col );
	}

	/**
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private static function redact_option_value( string $name, $value ) {
		if ( WPAgent_Options_Allowlist::is_blocked_key( $name ) ) {
			return '[redacted]';
		}
		if ( 'wp_rocket_settings' === $name ) {
			return self::redact_rocket_settings( $value );
		}
		if ( preg_match( '/(secret|api_key|auth_key|password|encrypted)/i', $name ) ) {
			return '[redacted]';
		}
		return $value;
	}

	/**
	 * @param mixed $value Serialized or array settings.
	 * @return mixed
	 */
	private static function redact_rocket_settings( $value ) {
		$data = $value;
		if ( is_string( $value ) ) {
			$un = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			if ( false === $un || ! is_array( $un ) ) {
				return '[redacted]';
			}
			$data = $un;
		}
		if ( ! is_array( $data ) ) {
			return '[redacted]';
		}
		foreach ( array( 'cloudflare_api_key', 'cloudflare_email', 'consumer_key', 'secret_key', 'secret_cache_key' ) as $key ) {
			if ( isset( $data[ $key ] ) && '' !== $data[ $key ] && array() !== $data[ $key ] ) {
				$data[ $key ] = '[redacted]';
			}
		}
		return $data;
	}

	/**
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function redact_value( $value ): string {
		return ( is_string( $value ) && '' === $value ) ? '' : '[redacted]';
	}
}
