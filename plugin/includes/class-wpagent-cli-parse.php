<?php
/**
 * Tokenize WP-CLI-style lines. No shell.
 *
 * @package WPAgent
 */

class WPAgent_Cli_Parse {

	/**
	 * @return array{tokens:string[],command:string,args:string[],flags:array<string,string|true>}
	 */
	public static function parse( string $line ): array {
		$line = trim( $line );
		if ( '' === $line ) {
			throw new InvalidArgumentException( 'command is required.' );
		}
		if ( preg_match( '/[;|`$]|\$\(/', $line ) ) {
			throw new InvalidArgumentException( 'Shell metacharacters are not allowed. This is not a shell.' );
		}

		$tokens = self::tokenize( $line );
		if ( ! $tokens ) {
			throw new InvalidArgumentException( 'command is required.' );
		}

		$args  = array();
		$flags = array();
		$i     = 0;
		while ( $i < count( $tokens ) && ! str_starts_with( $tokens[ $i ], '--' ) ) {
			++$i;
		}
		$head = array_slice( $tokens, 0, $i );
		$rest = array_slice( $tokens, $i );

		$j = 0;
		while ( $j < count( $rest ) ) {
			$token = $rest[ $j ];
			if ( ! str_starts_with( $token, '--' ) ) {
				$args[] = $token;
				++$j;
				continue;
			}
			$raw = substr( $token, 2 );
			if ( str_contains( $raw, '=' ) ) {
				[ $name, $value ] = explode( '=', $raw, 2 );
				$flags[ sanitize_key( $name ) ] = $value;
				++$j;
				continue;
			}
			$name = sanitize_key( $raw );
			if ( $j + 1 < count( $rest ) && ! str_starts_with( $rest[ $j + 1 ], '--' ) ) {
				$flags[ $name ] = $rest[ $j + 1 ];
				$j += 2;
				continue;
			}
			$flags[ $name ] = true;
			++$j;
		}

		return array(
			'tokens'  => $tokens,
			'head'    => $head,
			'args'    => $args,
			'flags'   => $flags,
			'command' => '',
		);
	}

	/**
	 * @param string[] $head
	 * @param array<string, mixed> $catalog
	 * @return array{0:string,1:array<string,mixed>,2:string[]}
	 */
	public static function resolve( array $head, array $catalog ): array {
		for ( $len = count( $head ); $len >= 1; $len-- ) {
			$key = strtolower( implode( ' ', array_slice( $head, 0, $len ) ) );
			if ( isset( $catalog[ $key ] ) ) {
				return array( $key, $catalog[ $key ], array_slice( $head, $len ) );
			}
		}
		throw new InvalidArgumentException(
			sprintf( 'Unknown command "%s". Call list_wp_cli_commands for the allowlist.', implode( ' ', $head ) )
		);
	}

	/**
	 * Stable string used to bind an approval ticket to one command.
	 *
	 * @param array<string, string|true> $flags
	 * @param string[]                   $args
	 */
	public static function canonical( string $command, array $args, array $flags ): string {
		ksort( $flags );
		$parts = array( $command );
		foreach ( $args as $arg ) {
			$parts[] = $arg;
		}
		foreach ( $flags as $name => $value ) {
			$parts[] = true === $value ? "--{$name}" : "--{$name}=" . (string) $value;
		}
		return implode( ' ', $parts );
	}

	/**
	 * @return string[]
	 */
	public static function tokenize( string $line ): array {
		$out   = array();
		$buf   = '';
		$quote = '';
		$len   = strlen( $line );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $line[ $i ];
			if ( $quote ) {
				if ( $ch === $quote ) {
					$quote = '';
					continue;
				}
				$buf .= $ch;
				continue;
			}
			if ( "'" === $ch || '"' === $ch ) {
				$quote = $ch;
				continue;
			}
			if ( ctype_space( $ch ) ) {
				if ( '' !== $buf ) {
					$out[] = $buf;
					$buf   = '';
				}
				continue;
			}
			$buf .= $ch;
		}
		if ( $quote ) {
			throw new InvalidArgumentException( 'Unclosed quote in command.' );
		}
		if ( '' !== $buf ) {
			$out[] = $buf;
		}
		return $out;
	}
}
