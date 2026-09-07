<?php
/**
 * SQL-guard bypass attempts. Each payload must be rejected.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class SqlGuardBypassTest extends TestCase {

	/**
	 * @dataProvider bypassPayloads
	 */
	public function test_bypass_is_rejected( string $sql ): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_SQL_Guard::assert_read_only( $sql );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function bypassPayloads(): array {
		return array(
			'stacked_delete'          => array( "SELECT 1; DELETE FROM wp_users" ),
			'stacked_newline'         => array( "SELECT 1;\nDROP TABLE wp_users" ),
			'comment_then_delete'     => array( "SELECT 1;--\nDELETE FROM wp_posts" ),
			'hash_comment_stack'      => array( "SELECT 1 # comment\n; UPDATE wp_options SET option_value=1" ),
			'mysql_version_delete'    => array( '/*!50000 DELETE FROM wp_users */' ),
			'mysql_version_after_sel' => array( 'SELECT 1 /*!50000 ; DROP TABLE wp_options */' ),
			'into_outfile'            => array( 'SELECT user_pass FROM wp_users INTO OUTFILE "/tmp/x"' ),
			'dumpfile'                => array( "SELECT 1 INTO DUMPFILE '/tmp/x'" ),
			'load_file'               => array( 'SELECT LOAD_FILE("/etc/passwd")' ),
			'update_keyword'          => array( 'UPDATE wp_options SET option_value=1' ),
			'insert_keyword'          => array( 'INSERT INTO wp_posts VALUES (1)' ),
			'prepare'                 => array( 'PREPARE stmt FROM "DELETE FROM wp_users"' ),
			'sleep'                   => array( 'SELECT SLEEP(10)' ),
			'benchmark'               => array( 'SELECT BENCHMARK(1000000,SHA1("a"))' ),
			'null_byte_stack'         => array( "SELECT 1;\x00DELETE FROM wp_users" ),
			'hex_looks_like_select'   => array( '0x53454c4543542031; DROP TABLE wp_users' ),
			'union_into'              => array( 'SELECT 1 UNION SELECT user_pass FROM wp_users INTO OUTFILE "/tmp/p"' ),
			'set_session'             => array( 'SET @a=1' ),
			'handler'                 => array( 'HANDLER wp_users OPEN' ),
		);
	}

	public function test_legitimate_select_still_allowed(): void {
		WPAgent_SQL_Guard::assert_read_only( "SELECT ID FROM wp_posts WHERE post_title = 'delete me' /* DROP */" );
		$this->addToAssertionCount( 1 );
	}

	public function test_version_comment_is_expanded_for_checks(): void {
		$expanded = WPAgent_SQL_Guard::expand_version_comments( 'SELECT 1 /*!50000 DELETE FROM wp_users */' );
		$this->assertStringContainsString( 'DELETE', $expanded );
	}
}
