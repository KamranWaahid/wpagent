<?php
/**
 * Page-builder payload + dispatch tests (no builder runtime).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class BuildersTest extends TestCase {

	public function test_normalize_rejects_unknown(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Builders::normalize( 'oxygen' );
	}

	public function test_normalize_known_slugs(): void {
		$this->assertSame( 'elementor', WPAgent_Builders::normalize( 'Elementor' ) );
		$this->assertSame( 'bricks', WPAgent_Builders::normalize( 'bricks' ) );
		$this->assertSame( 'divi', WPAgent_Builders::normalize( 'DIVI' ) );
	}

	public function test_inventory_reports_inactive_without_builders(): void {
		$out = WPAgent_Builders::inventory();
		$this->assertSame( array(), $out['active'] );
		$this->assertCount( 5, $out['items'] );
		foreach ( $out['items'] as $row ) {
			$this->assertFalse( $row['active'] );
		}
	}

	public function test_catalog_fails_when_inactive(): void {
		$this->expectException( RuntimeException::class );
		WPAgent_Builders::catalog( 'elementor' );
	}

	public function test_save_fails_when_inactive(): void {
		$this->expectException( RuntimeException::class );
		WPAgent_Builders::save(
			'bricks',
			array(
				'title'    => 'Home',
				'elements' => array(
					array(
						'id'       => 'abc123',
						'name'     => 'section',
						'parent'   => 0,
						'children' => array(),
						'settings' => array(),
					),
				),
			)
		);
	}

	public function test_elementor_elements_must_be_a_list(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Builder_Payload::elementor_elements( array( 'elType' => 'section' ) );
	}

	public function test_elementor_elements_need_type(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Builder_Payload::elementor_elements( array( array( 'id' => '1' ) ) );
	}

	public function test_elementor_elements_ok(): void {
		$out = WPAgent_Builder_Payload::elementor_elements(
			array(
				array(
					'id'     => 'a',
					'elType' => 'section',
				),
			)
		);
		$this->assertSame( 'section', $out[0]['elType'] );
	}

	public function test_bricks_rejects_duplicate_ids(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Builder_Payload::bricks_elements(
			array(
				array(
					'id'   => 'one',
					'name' => 'section',
				),
				array(
					'id'   => 'one',
					'name' => 'heading',
				),
			)
		);
	}

	public function test_bricks_rejects_missing_parent(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Builder_Payload::bricks_elements(
			array(
				array(
					'id'     => 'child',
					'name'   => 'heading',
					'parent' => 'missing',
				),
			)
		);
	}

	public function test_bricks_fills_defaults(): void {
		$out = WPAgent_Builder_Payload::bricks_elements(
			array(
				array(
					'id'   => 'sec',
					'name' => 'section',
				),
			)
		);
		$this->assertSame( array(), $out[0]['children'] );
		$this->assertSame( 0, $out[0]['parent'] );
	}

	public function test_beaver_objectifies_node_and_settings_only(): void {
		$out = WPAgent_Builder_Payload::beaver_nodes(
			array(
				'node1' => array(
					'type'     => 'row',
					'settings' => array(
						'width'      => 'full',
						'typography' => array( 'font' => 'sans' ),
					),
				),
			)
		);
		$this->assertIsObject( $out['node1'] );
		$this->assertIsObject( $out['node1']->settings );
		$this->assertIsArray( $out['node1']->settings->typography );
	}

	public function test_breakdance_repairs_tree(): void {
		$tree = WPAgent_Builder_Payload::breakdance_tree(
			array(
				'root' => array(
					'id'       => 1,
					'data'     => array( 'type' => 'root' ),
					'children' => array(
						array(
							'id'   => 4,
							'data' => array( 'type' => 'EssentialElements\\Heading' ),
						),
					),
				),
			)
		);
		$this->assertSame( 'exported', $tree['status'] );
		$this->assertSame( 5, $tree['_nextNodeId'] );
		$this->assertSame( array(), $tree['root']['children'][0]['data']['properties'] );
	}

	public function test_divi_requires_section_shortcode(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Builder_Payload::divi_content( '<p>Hello</p>' );
	}

	public function test_divi_accepts_section(): void {
		$this->assertStringContainsString(
			'et_pb_section',
			WPAgent_Builder_Payload::divi_content( '[et_pb_section][/et_pb_section]' )
		);
	}

	public function test_elementor_widget_patch_merges_settings(): void {
		$tree = array(
			array(
				'id'       => 'sec',
				'elType'   => 'section',
				'elements' => array(
					array(
						'id'         => 'chkout',
						'elType'     => 'widget',
						'widgetType' => 'woocommerce-checkout-page',
						'settings'   => array(
							'field_label' => 'First Name',
							'keep'        => 'yes',
						),
					),
				),
			),
		);
		$out = WPAgent_Builder_Payload::patch_elementor_widget(
			$tree,
			'chkout',
			array( 'field_label' => 'Prénom' )
		);
		$this->assertSame( 'Prénom', $out[0]['elements'][0]['settings']['field_label'] );
		$this->assertSame( 'yes', $out[0]['elements'][0]['settings']['keep'] );
	}

	public function test_elementor_widget_patch_rejects_missing_id(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Builder_Payload::patch_elementor_widget(
			array(
				array(
					'id'     => 'other',
					'elType' => 'widget',
				),
			),
			'chkout',
			array( 'field_label' => 'Prénom' )
		);
	}

	public function test_save_builder_page_is_draft_first(): void {
		$def = WPAgent_Allowlist::get( 'save_builder_page' );
		$this->assertTrue( ! empty( $def['draft_first'] ) );
		$this->assertSame( 'edit_posts', $def['capability'] );
	}
}
