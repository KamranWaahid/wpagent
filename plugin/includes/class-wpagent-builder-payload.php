<?php
/**
 * Builder layout shape checks that do not need WordPress or a builder runtime.
 *
 * @package WPAgent
 */

class WPAgent_Builder_Payload {

	/**
	 * Elementor root list: each item is an element array with elType or widgetType.
	 *
	 * @param mixed $data Request data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function elementor_elements( $data ): array {
		if ( ! is_array( $data ) || array() === $data ) {
			throw new InvalidArgumentException( 'Elementor save needs a non-empty `elements` array of root widgets/sections.' );
		}
		if ( self::is_assoc( $data ) ) {
			throw new InvalidArgumentException( 'Elementor `elements` must be a list, not a keyed object.' );
		}
		foreach ( $data as $index => $node ) {
			if ( ! is_array( $node ) ) {
				throw new InvalidArgumentException( sprintf( 'Elementor element at index %s must be an object.', (string) $index ) );
			}
			if ( empty( $node['elType'] ) && empty( $node['widgetType'] ) ) {
				throw new InvalidArgumentException( sprintf( 'Elementor element at index %s needs elType or widgetType.', (string) $index ) );
			}
		}
		return array_values( $data );
	}

	/**
	 * Merge settings onto one Elementor node by id. Rejects if the widget is missing.
	 *
	 * @param array<int, array<string, mixed>> $elements Root elements.
	 * @param array<string, mixed>             $settings Settings to merge.
	 * @return array<int, array<string, mixed>>
	 */
	public static function patch_elementor_widget( array $elements, string $widget_id, array $settings ): array {
		$widget_id = trim( $widget_id );
		if ( '' === $widget_id ) {
			throw new InvalidArgumentException( 'widget_id is required to patch an Elementor widget.' );
		}
		$found = false;
		$walk  = static function ( array &$nodes ) use ( &$walk, $widget_id, $settings, &$found ): void {
			foreach ( $nodes as &$node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				if ( isset( $node['id'] ) && (string) $node['id'] === $widget_id ) {
					$current           = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
					$node['settings']  = array_merge( $current, $settings );
					$found             = true;
					return;
				}
				if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
					$walk( $node['elements'] );
					if ( $found ) {
						return;
					}
				}
			}
			unset( $node );
		};
		$walk( $elements );
		if ( ! $found ) {
			throw new InvalidArgumentException( sprintf( 'Widget "%s" was not found in this document.', $widget_id ) );
		}
		return $elements;
	}

	/**
	 * Bricks flat element list: id, name, parent, children, settings.
	 *
	 * @param mixed $elements Request elements.
	 * @return array<int, array<string, mixed>>
	 */
	public static function bricks_elements( $elements ): array {
		if ( ! is_array( $elements ) || array() === $elements ) {
			throw new InvalidArgumentException( 'Bricks save needs a non-empty `elements` list of {id, name, parent, children, settings}.' );
		}
		$ids = array();
		$out = array();
		foreach ( $elements as $index => $node ) {
			if ( ! is_array( $node ) ) {
				throw new InvalidArgumentException( sprintf( 'Bricks element at index %s must be an object.', (string) $index ) );
			}
			$id   = isset( $node['id'] ) ? (string) $node['id'] : '';
			$name = isset( $node['name'] ) ? (string) $node['name'] : '';
			if ( '' === $id || '' === $name ) {
				throw new InvalidArgumentException( sprintf( 'Bricks element at index %s needs string id and name.', (string) $index ) );
			}
			if ( isset( $ids[ $id ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Bricks element id "%s" is duplicated.', $id ) );
			}
			$ids[ $id ] = true;
			if ( ! isset( $node['children'] ) || ! is_array( $node['children'] ) ) {
				$node['children'] = array();
			}
			if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) {
				$node['settings'] = array();
			}
			if ( ! array_key_exists( 'parent', $node ) ) {
				$node['parent'] = 0;
			}
			$out[] = $node;
		}
		$set = array_fill_keys( array_keys( $ids ), true );
		foreach ( $out as $node ) {
			$parent = $node['parent'];
			if ( 0 === $parent || '0' === $parent || '' === $parent || null === $parent ) {
				continue;
			}
			if ( ! isset( $set[ (string) $parent ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Bricks element "%s" points at missing parent "%s".', (string) $node['id'], (string) $parent ) );
			}
		}
		return $out;
	}

	/**
	 * Beaver node map: node and settings become objects; nested field arrays stay arrays.
	 *
	 * @param mixed $data Request data.
	 * @return array<string, object>
	 */
	public static function beaver_nodes( $data ): array {
		if ( ! is_array( $data ) || array() === $data ) {
			throw new InvalidArgumentException( 'Beaver Builder save needs a non-empty `data` node map.' );
		}
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data );
		$assoc = json_decode( (string) $encoded, true );
		if ( ! is_array( $assoc ) ) {
			throw new InvalidArgumentException( 'Beaver Builder `data` is not valid JSON.' );
		}
		$out = array();
		foreach ( $assoc as $key => $node ) {
			if ( ! is_array( $node ) ) {
				throw new InvalidArgumentException( sprintf( 'Beaver node "%s" must be an object.', (string) $key ) );
			}
			$obj           = (object) $node;
			$obj->settings = (object) ( is_array( $node['settings'] ?? null ) ? $node['settings'] : array() );
			$out[ (string) $key ] = $obj;
		}
		return $out;
	}

	/**
	 * Breakdance document tree (object or JSON string).
	 *
	 * @param mixed $tree             Decoded tree.
	 * @param mixed $tree_json_string Encoded tree.
	 * @return array<string, mixed>
	 */
	public static function breakdance_tree( $tree, $tree_json_string = null ): array {
		if ( null === $tree && is_string( $tree_json_string ) && '' !== $tree_json_string ) {
			$decoded = json_decode( $tree_json_string, true );
			if ( ! is_array( $decoded ) ) {
				throw new InvalidArgumentException( '`tree_json_string` is not valid JSON.' );
			}
			$tree = $decoded;
		}
		if ( ! is_array( $tree ) || empty( $tree['root'] ) || ! is_array( $tree['root'] ) ) {
			throw new InvalidArgumentException( 'Breakdance save needs `tree` (or `tree_json_string`) with a root node {id, data, children}.' );
		}
		$root = $tree['root'];
		foreach ( array( 'id', 'data', 'children' ) as $key ) {
			if ( ! array_key_exists( $key, $root ) ) {
				throw new InvalidArgumentException( 'Breakdance `root` must include id, data, and children.' );
			}
		}
		self::repair_breakdance_node( $tree['root'] );
		$next = isset( $tree['_nextNodeId'] ) && is_numeric( $tree['_nextNodeId'] ) ? (int) $tree['_nextNodeId'] : 0;
		$tree['_nextNodeId'] = max( $next, self::max_breakdance_id( $tree['root'] ) + 1 );
		$tree['status']      = 'exported';
		return $tree;
	}

	/**
	 * Divi Visual Builder shortcode document.
	 *
	 * @param mixed $content Post content.
	 */
	public static function divi_content( $content ): string {
		$content = is_string( $content ) ? $content : '';
		if ( ! str_contains( $content, '[et_pb_section' ) ) {
			throw new InvalidArgumentException( 'Divi save needs `content` that starts from an [et_pb_section] shortcode tree.' );
		}
		return $content;
	}

	/**
	 * @param mixed $value Value.
	 */
	public static function is_assoc( $value ): bool {
		if ( ! is_array( $value ) || array() === $value ) {
			return false;
		}
		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}

	/**
	 * @param array<string, mixed> $node Node.
	 */
	private static function repair_breakdance_node( array &$node ): void {
		if ( ! isset( $node['data'] ) || ! is_array( $node['data'] ) ) {
			$node['data'] = array();
		}
		if ( ! isset( $node['data']['properties'] ) || ! is_array( $node['data']['properties'] ) ) {
			$node['data']['properties'] = array();
		}
		if ( ! isset( $node['children'] ) || ! is_array( $node['children'] ) ) {
			$node['children'] = array();
		}
		foreach ( $node['children'] as &$child ) {
			if ( is_array( $child ) ) {
				self::repair_breakdance_node( $child );
			}
		}
		unset( $child );
	}

	/**
	 * @param array<string, mixed> $node Node.
	 */
	private static function max_breakdance_id( array $node ): int {
		$max = isset( $node['id'] ) && is_numeric( $node['id'] ) ? (int) $node['id'] : 0;
		$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
		foreach ( $children as $child ) {
			if ( is_array( $child ) ) {
				$max = max( $max, self::max_breakdance_id( $child ) );
			}
		}
		return $max;
	}
}
