<?php
/**
 * File manifest snapshots for updates.
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class FileManifestTest extends TestCase {

	public function test_snapshot_and_diff(): void {
		$dir = sys_get_temp_dir() . '/wpagent-manifest-' . uniqid();
		mkdir( $dir );
		file_put_contents( $dir . '/a.php', 'one' );
		file_put_contents( $dir . '/b.php', 'two' );

		$before = WPAgent_File_Manifest::snapshot( $dir );
		$this->assertSame( 2, $before['file_count'] );

		file_put_contents( $dir . '/b.php', 'changed' );
		file_put_contents( $dir . '/c.php', 'new' );
		unlink( $dir . '/a.php' );

		$after = WPAgent_File_Manifest::snapshot( $dir );
		$diff  = WPAgent_File_Manifest::diff( $before, $after );

		$this->assertContains( 'c.php', $diff['added'] );
		$this->assertContains( 'a.php', $diff['removed'] );
		$this->assertContains( 'b.php', $diff['changed'] );

		unlink( $dir . '/b.php' );
		unlink( $dir . '/c.php' );
		rmdir( $dir );
	}
}
