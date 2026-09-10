<?php

namespace MediaWiki\Extension\MathSearch\Tests\WikiTexVC;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLTestUtil;
use MediaWiki\Extension\Math\WikiTexVC\TexVC;
use MediaWikiIntegrationTestCase;

/**
 * Scores WikiTexVC against the Mathoid and LaTeXML renderings kept in the
 * reference files that moved over from the Math extension (T121100).
 *
 * @covers \MediaWiki\Extension\Math\WikiTexVC\TexVC
 */
final class MMLRefFilesTest extends MediaWikiIntegrationTestCase {

	private const FILES = [ 'TexUtil-Ref.json', 'ParserTest-Ref.json' ];

	/**
	 * @dataProvider provideTestCases
	 */
	public function testTexVC( string $title, $tc ) {
		$resultT = ( new TexVC() )->check( $tc->tex, [
			'debug' => false,
			'usemhchem' => true,
			'usemhchemtexified' => true,
			'useintent' => true,
		] );
		if ( !isset( $resultT['input'] ) ) {
			// Rows scraped from the parser tests carry wikitext from unclosed math
			// tags; those have to be rejected with an error status, not crash.
			$this->assertNotSame( '+', $resultT['status'] ?? '+',
				"$title: no parse result, but no error status either" );
			return;
		}

		$mathML = MMLTestUtil::getMMLwrapped( $resultT['input'] );
		$this->assertStringContainsString( '<math', $mathML, "$title: no MathML for $tc->tex" );

		$comparator = new MMLComparator();
		foreach ( [ 'mmlMathoid', 'mmlLaTeXML' ] as $field ) {
			$reference = $tc->$field ?? null;
			if ( !$reference ) {
				continue;
			}
			$this->assertGreaterThanOrEqual( 0, $comparator->compareMathML( $reference, $mathML )['similarityF'],
				"$title: $field comparison failed for $tc->tex" );
		}
	}

	public static function provideTestCases() {
		foreach ( self::FILES as $file ) {
			foreach ( MMLTestUtil::getJSON( __DIR__ . '/' . $file ) as $index => $tc ) {
				$title = $file . '#' . $index;
				yield $title => [ $title, $tc ];
			}
		}
	}
}
