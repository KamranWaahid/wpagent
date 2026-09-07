<?php
/**
 * In-process PHP syntax check. Does not execute the file.
 *
 * @package WPAgent
 */

class WPAgent_Php_Guard {

	/**
	 * @return true
	 * @throws InvalidArgumentException When the source does not parse.
	 */
	public static function assert_valid( string $source, string $label = 'file' ): bool {
		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@token_get_all( $source, TOKEN_PARSE );
		} catch ( ParseError $e ) {
			throw new InvalidArgumentException(
				sprintf( 'PHP syntax error in %s: %s', $label, $e->getMessage() )
			);
		} catch ( CompileError $e ) {
			throw new InvalidArgumentException(
				sprintf( 'PHP compile error in %s: %s', $label, $e->getMessage() )
			);
		}

		return true;
	}
}
