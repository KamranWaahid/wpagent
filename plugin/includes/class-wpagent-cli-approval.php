<?php
/**
 * One-time tickets for destructive CLI verbs.
 *
 * @package WPAgent
 */

class WPAgent_Cli_Approval {

	public const TTL = 600;

	/**
	 * @param array<string, mixed> $preview
	 * @return array<string, mixed>
	 */
	public static function issue( string $canonical, array $preview ): array {
		$id  = 'wpa_' . wp_generate_password( 20, false );
		$key = self::key( $id );
		set_transient(
			$key,
			array(
				'canonical' => $canonical,
				'preview'   => $preview,
			),
			self::TTL
		);
		return array(
			'approval_required' => true,
			'approval_id'       => $id,
			'expires_in'        => self::TTL,
			'canonical'         => $canonical,
			'preview'           => $preview,
			'note'              => 'Re-run the same command with confirm=true and this approval_id. The ticket is single-use.',
		);
	}

	public static function consume( string $id, string $canonical ): void {
		$id = preg_replace( '/[^a-zA-Z0-9_]/', '', $id ) ?? '';
		if ( '' === $id ) {
			throw new InvalidArgumentException( 'approval_id is required for this command.' );
		}
		$key = self::key( $id );
		$row = get_transient( $key );
		if ( ! is_array( $row ) || ( $row['canonical'] ?? '' ) !== $canonical ) {
			throw new InvalidArgumentException( 'Approval ticket is missing, expired, or does not match this command.' );
		}
		delete_transient( $key );
	}

	private static function key( string $id ): string {
		return 'wpagent_cli_appr_' . $id;
	}
}
