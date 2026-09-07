<?php
/**
 * CLI tokenizer, resolver, and default-deny catalog.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class CliParseTest extends TestCase {

	public function test_parses_flags_and_quotes(): void {
		$parsed = WPAgent_Cli_Parse::parse( 'db query "SELECT ID FROM wp_posts" --limit=5' );
		[ $name, , $positional ] = WPAgent_Cli_Parse::resolve( $parsed['head'], WPAgent_Cli::catalog() );
		$this->assertSame( 'db query', $name );
		$this->assertSame( array( 'SELECT ID FROM wp_posts' ), array_merge( $positional, $parsed['args'] ) );
		$this->assertSame( '5', $parsed['flags']['limit'] );
	}

	public function test_rejects_shell_metacharacters(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Cli_Parse::parse( 'plugin list; DROP TABLE wp_users' );
	}

	public function test_resolves_longest_command(): void {
		$catalog = WPAgent_Cli::catalog();
		$parsed  = WPAgent_Cli_Parse::parse( 'plugin deactivate akismet/akismet.php' );
		[ $name, $meta, $positional ] = WPAgent_Cli_Parse::resolve( $parsed['head'], $catalog );
		$this->assertSame( 'plugin deactivate', $name );
		$this->assertSame( 'write', $meta['tier'] );
		$this->assertSame( array( 'akismet/akismet.php' ), array_merge( $positional, $parsed['args'] ) );
	}

	public function test_unknown_command_is_denied(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Cli_Parse::resolve( array( 'eval-file', 'hack.php' ), WPAgent_Cli::catalog() );
	}

	public function test_resolves_plugin_install(): void {
		$parsed = WPAgent_Cli_Parse::parse( 'plugin install akismet' );
		[ $name, $meta, $positional ] = WPAgent_Cli_Parse::resolve( $parsed['head'], WPAgent_Cli::catalog() );
		$this->assertSame( 'plugin install', $name );
		$this->assertSame( 'destructive', $meta['tier'] );
		$this->assertSame( array( 'akismet' ), array_merge( $positional, $parsed['args'] ) );
	}

	public function test_plugin_delete_is_destructive(): void {
		$meta = WPAgent_Cli::catalog()['plugin delete'];
		$this->assertSame( 'destructive', $meta['tier'] );
		$this->assertSame( 'delete_plugins', $meta['cap'] );
	}

	public function test_canonical_is_stable(): void {
		$a = WPAgent_Cli_Parse::canonical( 'plugin delete', array( 'x/x.php' ), array( 'yes' => true ) );
		$b = WPAgent_Cli_Parse::canonical( 'plugin delete', array( 'x/x.php' ), array( 'yes' => true ) );
		$this->assertSame( $a, $b );
	}

	public function test_resolves_post_delete_and_update(): void {
		$catalog = WPAgent_Cli::catalog();
		$parsed  = WPAgent_Cli_Parse::parse( 'post delete 1686' );
		[ $name, $meta, $positional ] = WPAgent_Cli_Parse::resolve( $parsed['head'], $catalog );
		$this->assertSame( 'post delete', $name );
		$this->assertSame( 'write', $meta['tier'] );
		$this->assertSame( array( '1686' ), $positional );
		$update = WPAgent_Cli_Parse::parse( 'post update 1686 --post_status=trash' );
		[ $uname, $umeta ] = WPAgent_Cli_Parse::resolve( $update['head'], $catalog );
		$this->assertSame( 'post update', $uname );
		$this->assertSame( 'write', $umeta['tier'] );
		$this->assertSame( 'trash', $update['flags']['post_status'] );
	}

	public function test_mutating_sql_never_becomes_an_approval(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_SQL_Guard::assert_read_only( 'DELETE FROM wp_users' );
	}
}
