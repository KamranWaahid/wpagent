<?php
/**
 * Abilities helper tests (no WordPress 6.9 runtime).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class AbilitiesTest extends TestCase {

	public function test_unavailable_without_core_api(): void {
		$this->assertFalse( WPAgent_Abilities::available() );
		$this->expectException( RuntimeException::class );
		WPAgent_Abilities::require_available();
	}

	public function test_name_must_be_namespaced(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Abilities::normalize_name( 'drop-database' );
	}

	public function test_normalizes_valid_name(): void {
		$this->assertSame( 'seedprod/get-template', WPAgent_Abilities::normalize_name( 'SeedProd/Get-Template' ) );
	}

	public function test_readonly_from_annotation(): void {
		$this->assertTrue( WPAgent_Abilities::looks_readonly( 'acme/delete-thing', array( 'annotations' => array( 'readonly' => true ) ) ) );
		$this->assertFalse( WPAgent_Abilities::looks_readonly( 'acme/list-thing', array( 'annotations' => array( 'readonly' => false ) ) ) );
	}

	public function test_readonly_inferred_from_name(): void {
		$this->assertTrue( WPAgent_Abilities::looks_readonly( 'acme/get-count', array() ) );
		$this->assertFalse( WPAgent_Abilities::looks_readonly( 'acme/delete-user', array() ) );
		$this->assertFalse( WPAgent_Abilities::looks_readonly( 'acme/create-post', array() ) );
	}
}
