<?php
/**
 * Server-side capability + allowlist checks for every REST route.
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAgent_Permissions {

	/**
	 * REST permission_callback for a named allowlisted command.
	 *
	 * Re-checked on every request against the authenticated WordPress user
	 * (Application Passwords authenticate as that user).
	 */
	public static function callback( string $command ): callable {
		return static function () use ( $command ) {
			return self::current_user_can_command( $command );
		};
	}

	/**
	 * Whether the current user may run a command.
	 */
	public static function current_user_can_command( string $command ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$disabled = WPAgent_Allowlist::normalize_disabled( get_option( 'wpagent_disabled_commands', array() ) );
		if ( ! WPAgent_Allowlist::is_permitted( $command, $disabled ) ) {
			return false;
		}

		$cap = WPAgent_Allowlist::capability_for( $command );
		if ( ! $cap ) {
			return false;
		}

		return current_user_can( $cap );
	}

	/**
	 * WP_Error when the current user cannot run the command, or null on success.
	 */
	public static function deny_if_unauthorized( string $command ): ?WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'wpagent_auth',
				__( 'Authentication required. Use a WordPress Application Password.', 'wpagent' ),
				array(
					'status' => 401,
					'code'   => 'auth',
				)
			);
		}

		$disabled = WPAgent_Allowlist::normalize_disabled( get_option( 'wpagent_disabled_commands', array() ) );
		if ( ! WPAgent_Allowlist::is_permitted( $command, $disabled ) ) {
			$known = null !== WPAgent_Allowlist::get( $command );
			return new WP_Error(
				$known ? 'wpagent_disabled' : 'wpagent_denied',
				$known
					? sprintf( /* translators: %s command name */ __( 'Command "%s" is disabled on this site.', 'wpagent' ), $command )
					: sprintf( /* translators: %s command name */ __( 'Command "%s" is not on the allowlist.', 'wpagent' ), $command ),
				array(
					'status'  => 403,
					'code'    => 'safety',
					'command' => $command,
				)
			);
		}

		$cap = WPAgent_Allowlist::capability_for( $command );
		if ( ! $cap || ! current_user_can( $cap ) ) {
			WPAgent_Audit::log(
				array(
					'command'       => $command,
					'capability'    => $cap ?? '',
					'result'        => 'denied',
					'error_message' => 'missing_capability:' . ( $cap ?? 'unknown' ),
				)
			);

			return new WP_Error(
				'wpagent_capability',
				sprintf(
					/* translators: %s capability name */
					__( 'Missing capability: %s', 'wpagent' ),
					$cap ?? 'unknown'
				),
				array(
					'status'     => 403,
					'code'       => 'capability',
					'capability' => $cap,
					'command'    => $command,
				)
			);
		}

		return null;
	}

	/**
	 * Require confirm=true for destructive operations.
	 */
	public static function require_confirm( WP_REST_Request $request, string $message = '' ): ?WP_Error {
		$confirm = $request->get_param( 'confirm' );
		if ( true === $confirm || 'true' === $confirm || 1 === $confirm || '1' === $confirm ) {
			return null;
		}

		return new WP_Error(
			'wpagent_confirm_required',
			$message ?: __( 'This action requires confirm: true after a preview.', 'wpagent' ),
			array(
				'status' => 400,
				'code'   => 'safety',
			)
		);
	}
}
