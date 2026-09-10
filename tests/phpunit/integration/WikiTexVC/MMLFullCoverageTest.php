<?php

namespace MediaWiki\Extension\MathSearch\Tests\WikiTexVC;

use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLTestUtil;
use MediaWiki\Extension\Math\WikiTexVC\TexVC;
use MediaWikiIntegrationTestCase;

/**
 * Checks WikiTexVC (LaTeX → MathML) against Mathoid and LaTeXML reference output.
 * Uses the Full-Coverage definition from:
 * https://www.mediawiki.org/wiki/Extension:Math/CoverageTest
 *
 * Reference files can be refreshed with:
 *   maintenance/UpdateMath.php --mode mathml --exportmml /path/to/MathSearch
 *
 * Moved from the Math extension; see T121100.
 *
 * @covers \MediaWiki\Extension\Math\WikiTexVC\TexVC
 */
final class MMLFullCoverageTest extends MediaWikiIntegrationTestCase {

	/** @var string */
	private static $FILENAMELATEXML = __DIR__ . "/mmlRes-latexml-FullCoverage.json";
	/** @var string */
	private static $FILENAMEMATHOID = __DIR__ . "/mmlRes-mathml-FullCoverage.json";

	/**
	 * Only the merror assertion can fail: -0.0 >= 0 holds in PHP, and no similarity
	 * threshold is asserted. \mathit{ab} scores 0.0 and would fail a strict > 0 check.
	 *
	 * FIXME: loadXMLandDeleteAttrs() discards its result, so mml_latexml is unused.
	 *
	 * @dataProvider provideTestCases
	 */
	public function testTexVC( $title, $tc ) {
		$texVC = new TexVC();
		$resultT = $texVC->check( $tc->tex, [
			'debug' => false,
			'usemathrm' => $tc->usemathrm ?? false,
			'oldtexvc' => $tc->oldtexvc ?? false
		] );

		self::loadXMLandDeleteAttrs( $tc->mml_latexml );
		$mathMLtexVC = MMLTestUtil::getMMLwrapped( $resultT["input"] );
		$this->assertStringNotContainsString( 'merror', $mathMLtexVC,
			"tc $$tc->tex$: MathML $mathMLtexVC contains error" );
		$mmlComparator = new MMLComparator();
		$result = $mmlComparator->compareMathML( $tc->mml_mathoid, $mathMLtexVC );
		$this->assertGreaterThanOrEqual( 0, $result['similarityF'],
			"F-score must be non-negative for $$tc->tex$" );
	}

	/**
	 * Deletes some attributes from the mathml which are not necessary for comparisons.
	 * @param string $mml mathml as string
	 * @return bool|string false if problem, mathml as xml string without the specified attributes if ok
	 */
	public static function loadXMLandDeleteAttrs( $mml ) {
		$xml = simplexml_load_string( $mml );
		self::unsetAttrs( $xml );
		self::deleteAttributes( $xml );
		return $xml->asXML();
	}

	public static function deleteAttributes( &$xml ) {
		foreach ( $xml as $node ) {
			self::unsetAttrs( $node );
			self::deleteAttributes( $node );
		}
	}

	public static function unsetAttrs( $node ): void {
		$attrs = $node->attributes();
		unset( $attrs['id'], $attrs['xref'] );
	}

	public static function provideTestCases() {
		$resMathoid = MMLTestUtil::getJSON( self::$FILENAMEMATHOID );
		$resLaTeXML = MMLTestUtil::getJSON( self::$FILENAMELATEXML );
		if ( count( $resMathoid ) != count( $resLaTeXML ) ) {
			throw new InvalidArgumentException( "Test files dont have the same number of entries." );
		}
		$f = [];
		foreach ( $resMathoid as $index => $tcMathoid ) {
			$tcLaTeXML = $resLaTeXML[$index];
			$tc = [
				"ctr" => $index,
				"tex" => $tcMathoid->tex,
				"type" => $tcMathoid->type,
				"mml_mathoid" => $tcMathoid->mml,
				"mml_latexml" => $tcLaTeXML->mml,
			];
			array_push( $f, [ "title N/A", (object)$tc ] );
		}
		return $f;
	}
}
