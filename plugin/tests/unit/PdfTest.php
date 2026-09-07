<?php
/**
 * PDF sandbox + text extraction (no WordPress, no shell).
 *
 * @package WPAgent
 */

use PHPUnit\Framework\TestCase;

class PdfTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/wpagent-pdf-' . random_int( 1, 100000000 );
		mkdir( $this->root . '/2026/09', 0777, true );
		file_put_contents( $this->root . '/2026/09/hello.pdf', self::hello_pdf() );
		file_put_contents( $this->root . '/2026/09/notes.txt', 'not a pdf' );
		file_put_contents( $this->root . '/secret.pdf', '%PDF-1.4 outside year folder' );
	}

	protected function tearDown(): void {
		$this->rmdir_tree( $this->root );
	}

	public function test_relative_rejects_traversal(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Pdf::relative( '../wp-config.php' );
	}

	public function test_resolve_rejects_non_pdf(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Pdf::resolve_uploads_path( '2026/09/notes.txt', $this->root );
	}

	public function test_resolve_accepts_uploads_pdf(): void {
		$full = WPAgent_Pdf::resolve_uploads_path( '2026/09/hello.pdf', $this->root );
		$this->assertSame( realpath( $this->root . '/2026/09/hello.pdf' ), $full );
	}

	public function test_resolve_rejects_missing(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Pdf::resolve_uploads_path( '2026/09/missing.pdf', $this->root );
	}

	public function test_url_must_be_same_origin_uploads(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Pdf::relative_from_url(
			'https://evil.example/wp-content/uploads/2026/09/hello.pdf',
			'https://site.example/',
			'https://site.example/wp-content/uploads'
		);
	}

	public function test_url_must_be_in_uploads(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Pdf::relative_from_url(
			'https://site.example/wp-content/themes/x/secret.pdf',
			'https://site.example/',
			'https://site.example/wp-content/uploads'
		);
	}

	public function test_url_maps_to_relative_path(): void {
		$this->assertSame(
			'2026/09/hello.pdf',
			WPAgent_Pdf::relative_from_url(
				'https://site.example/wp-content/uploads/2026/09/hello.pdf',
				'https://site.example/',
				'https://site.example/wp-content/uploads'
			)
		);
	}

	public function test_rejects_non_pdf_magic(): void {
		$this->expectException( InvalidArgumentException::class );
		WPAgent_Pdf::assert_pdf_bytes( '%not-a-pdf' );
	}

	public function test_extracts_tj_text(): void {
		$out = WPAgent_Pdf::extract_text( self::hello_pdf() );
		$this->assertStringContainsString( 'Hello WPAgent', $out['text'] );
		$this->assertSame( 1, $out['page_count'] );
		$this->assertFalse( $out['truncated'] );
		$this->assertNull( $out['empty_reason'] );
	}

	public function test_extracts_flate_stream(): void {
		$raw  = 'BT /F1 12 Tf 72 720 Td (Flate Hello) Tj ET';
		$comp = gzcompress( $raw );
		$this->assertNotFalse( $comp );
		$pdf  = "%PDF-1.4\n"
			. "1 0 obj\n<< /Length " . strlen( $comp ) . " /Filter /FlateDecode >>\nstream\n"
			. $comp
			. "\nendstream\nendobj\n%%EOF\n";
		$out = WPAgent_Pdf::extract_text( $pdf );
		$this->assertStringContainsString( 'Flate Hello', $out['text'] );
	}

	public function test_extracts_tj_array(): void {
		$stream = 'BT [(Hel) -10 (lo)] TJ ET';
		$len    = strlen( $stream );
		$pdf    = "%PDF-1.4\n1 0 obj\n<< /Length {$len} >>\nstream\n{$stream}\nendstream\nendobj\n%%EOF\n";
		$out    = WPAgent_Pdf::extract_text( $pdf );
		$this->assertStringContainsString( 'Hello', $out['text'] );
	}

	public function test_empty_reason_when_no_text(): void {
		$out = WPAgent_Pdf::extract_text( "%PDF-1.4\n%%EOF\n" );
		$this->assertSame( '', $out['text'] );
		$this->assertNotNull( $out['empty_reason'] );
	}

	public function test_unescape_octal_and_parens(): void {
		$this->assertSame( "A(B)", WPAgent_Pdf::unescape_pdf_string( 'A\\(B\\)' ) );
		$this->assertSame( 'A', WPAgent_Pdf::unescape_pdf_string( '\\101' ) );
	}

	public function test_read_pdf_is_readonly(): void {
		$def = WPAgent_Allowlist::get( 'read_pdf' );
		$this->assertFalse( $def['write'] );
		$this->assertSame( 'upload_files', $def['capability'] );
	}

	private static function hello_pdf(): string {
		$stream = 'BT /F1 12 Tf 72 720 Td (Hello WPAgent) Tj ET';
		$len    = strlen( $stream );
		return "%PDF-1.4\n"
			. "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
			. "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
			. "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
			. "4 0 obj\n<< /Length {$len} >>\nstream\n{$stream}\nendstream\nendobj\n"
			. "%%EOF\n";
	}

	private function rmdir_tree( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->rmdir_tree( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}
}
