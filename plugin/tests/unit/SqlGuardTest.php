<?php
/**
 * SQL guard tests.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class SqlGuardTest extends TestCase {

	public function test_select_is_allowed(): void {
		$result = WPAgent_SQL_Guard::assert_read_only( 'SELECT ID, post_title FROM wp_posts LIMIT 5' );
		$this->assertSame( 100, $result['limit'] );
	}

	public function test_non_select_is_blocked(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_SQL_Guard::assert_read_only( 'DELETE FROM wp_posts' );
	}

	public function test_drop_in_comment_is_stripped(): void {
		WPAgent_SQL_Guard::assert_read_only( 'SELECT ID FROM wp_posts /* DROP TABLE wp_users */' );
		$this->addToAssertionCount( 1 );
	}

	public function test_mutation_keyword_in_string_is_ignored(): void {
		WPAgent_SQL_Guard::assert_read_only( "SELECT ID FROM wp_posts WHERE post_title = 'delete me'" );
		$this->addToAssertionCount( 1 );
	}

	public function test_stacked_queries_blocked(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_SQL_Guard::assert_read_only( 'SELECT 1; DROP TABLE wp_users' );
	}

	public function test_into_outfile_blocked(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_SQL_Guard::assert_read_only( 'SELECT * FROM wp_users INTO OUTFILE "/tmp/x"' );
	}

	public function test_limit_is_capped(): void {
		$result = WPAgent_SQL_Guard::assert_read_only( 'SELECT 1', 9999 );
		$this->assertSame( 500, $result['limit'] );
	}

	public function test_apply_limit_when_missing(): void {
		$sql = WPAgent_SQL_Guard::apply_limit( 'SELECT ID FROM wp_posts', 10 );
		$this->assertStringContainsString( 'LIMIT 10', $sql );
	}

	public function test_format_result_marks_truncated_and_redacts_secrets(): void {
		$out = WPAgent_SQL_Guard::format_result(
			array(
				array(
					'option_name'  => 'woocommerce_stripe_settings',
					'option_value' => 'sk_live_xxx',
				),
				array(
					'option_name'  => 'woocommerce_shipping_hide_rates_when_free',
					'option_value' => 'no',
				),
			),
			2
		);
		$this->assertTrue( $out['truncated'] );
		$this->assertSame( '[redacted]', $out['rows'][0]['option_value'] );
		$this->assertSame( 'no', $out['rows'][1]['option_value'] );
	}

	public function test_rocket_settings_redact_keys_only(): void {
		$raw = serialize(
			array(
				'delay_js'           => 1,
				'delay_js_exclusions' => array(),
				'cloudflare_api_key' => 'cfat_secret',
				'secret_key'         => 'abc',
			)
		);
		$out = WPAgent_SQL_Guard::redact_rows(
			array(
				array(
					'option_name'  => 'wp_rocket_settings',
					'option_value' => $raw,
				),
			)
		);
		$this->assertIsArray( $out[0]['option_value'] );
		$this->assertSame( 1, $out[0]['option_value']['delay_js'] );
		$this->assertSame( '[redacted]', $out[0]['option_value']['cloudflare_api_key'] );
		$this->assertSame( '[redacted]', $out[0]['option_value']['secret_key'] );
	}

	public function test_empty_sql_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_SQL_Guard::assert_read_only( '   ' );
	}

	public function test_show_tables_is_allowed(): void {
		$result = WPAgent_SQL_Guard::assert_read_only( 'SHOW TABLES' );
		$this->assertSame( 'SHOW TABLES', $result['sql'] );
	}

	public function test_show_tables_like_is_allowed(): void {
		$result = WPAgent_SQL_Guard::assert_read_only( "SHOW TABLES LIKE 'wp_%'" );
		$this->assertSame( "SHOW TABLES LIKE 'wp_%'", $result['sql'] );
	}

	public function test_show_create_is_blocked(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_SQL_Guard::assert_read_only( 'SHOW CREATE TABLE wp_posts' );
	}

	public function test_show_variables_is_blocked(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_SQL_Guard::assert_read_only( 'SHOW VARIABLES' );
	}

	public function test_apply_limit_skips_show_tables(): void {
		$sql = WPAgent_SQL_Guard::apply_limit( 'SHOW TABLES', 10 );
		$this->assertSame( 'SHOW TABLES', $sql );
		$this->assertStringNotContainsString( 'LIMIT', $sql );
	}
}
