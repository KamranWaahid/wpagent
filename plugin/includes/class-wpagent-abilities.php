<?php
/**
 * WordPress Abilities API passthrough (6.9+). Executes on this site only.
 *
 * @package WPAgent
 */

class WPAgent_Abilities {

	public static function available(): bool {
		return function_exists( 'wp_get_abilities' ) && function_exists( 'wp_get_ability' );
	}

	public static function require_available(): void {
		if ( ! self::available() ) {
			throw new RuntimeException( 'The WordPress Abilities API is not available. This site needs WordPress 6.9 or later.' );
		}
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	public static function looks_readonly( string $name, array $meta ): bool {
		$annotations = array();
		if ( isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ) {
			$annotations = $meta['annotations'];
		}
		if ( array_key_exists( 'readonly', $annotations ) ) {
			return (bool) $annotations['readonly'];
		}
		return 1 !== preg_match( '/(create|update|delete|write|install|uninstall|send|destroy|set-)/i', $name );
	}

	public static function normalize_name( string $name ): string {
		$name = strtolower( trim( $name ) );
		if ( ! preg_match( '/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $name ) ) {
			throw new InvalidArgumentException( 'Ability name must look like namespace/ability-name.' );
		}
		return $name;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function list_all(): array {
		self::require_available();
		$items = array();
		foreach ( wp_get_abilities() as $ability ) {
			if ( ! is_object( $ability ) ) {
				continue;
			}
			$items[] = self::summarize( $ability );
		}
		return array(
			'available' => true,
			'count'     => count( $items ),
			'items'     => $items,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function info( string $name ): array {
		self::require_available();
		$ability = self::fetch( $name );
		$payload = self::summarize( $ability );
		if ( method_exists( $ability, 'get_input_schema' ) ) {
			$payload['input_schema'] = $ability->get_input_schema();
		}
		if ( method_exists( $ability, 'get_output_schema' ) ) {
			$payload['output_schema'] = $ability->get_output_schema();
		}
		if ( method_exists( $ability, 'get_meta' ) ) {
			$payload['meta'] = $ability->get_meta();
		}
		return $payload;
	}

	/**
	 * @param mixed $input
	 * @return array<string, mixed>
	 */
	public static function execute( string $name, $input, bool $confirm ): array {
		self::require_available();
		$ability = self::fetch( $name );
		$summary = self::summarize( $ability );
		if ( empty( $summary['readonly'] ) && ! $confirm ) {
			throw new InvalidArgumentException(
				sprintf( 'Ability "%s" is not marked read-only. Re-run with confirm=true after reviewing get_ability_info.', $summary['name'] )
			);
		}
		if ( empty( $summary['can_execute'] ) ) {
			throw new RuntimeException( sprintf( 'The current user cannot execute "%s".', $summary['name'] ) );
		}

		$result = $ability->execute( $input );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}

		return array(
			'name'    => $summary['name'],
			'ran'     => true,
			'result'  => $result,
		);
	}

	/**
	 * @return object
	 */
	private static function fetch( string $name ) {
		$name    = self::normalize_name( $name );
		$ability = wp_get_ability( $name );
		if ( ! is_object( $ability ) ) {
			throw new InvalidArgumentException( sprintf( 'Ability "%s" is not registered.', $name ) );
		}
		return $ability;
	}

	/**
	 * @param object $ability WP_Ability.
	 * @return array<string, mixed>
	 */
	private static function summarize( object $ability ): array {
		$name = method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : '';
		$meta = ( method_exists( $ability, 'get_meta' ) && is_array( $ability->get_meta() ) ) ? $ability->get_meta() : array();
		$can  = true;
		if ( method_exists( $ability, 'check_permissions' ) ) {
			$perm = $ability->check_permissions();
			$can  = true === $perm;
		}
		return array(
			'name'        => $name,
			'label'       => method_exists( $ability, 'get_label' ) ? (string) $ability->get_label() : $name,
			'description' => method_exists( $ability, 'get_description' ) ? (string) $ability->get_description() : '',
			'category'    => method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : '',
			'readonly'    => self::looks_readonly( $name, $meta ),
			'can_execute' => $can,
		);
	}
}
