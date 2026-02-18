<?php

namespace MediaWiki\Tests\Specials;

use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use SpecialMathDebug;

/**
 * @covers \SpecialMathDebug
 */
class SpecialMathDebugTest extends SpecialPageTestBase {
	use \MockHttpTrait;

	protected function newSpecialPage() {
		return new SpecialMathDebug( MediaWikiServices::getInstance()->getHttpRequestFactory() );
	}

	public function testVisualDiffReportsIndexDifference() {
		$master = [
			[ 'input' => 'a', 'output' => '1' ],
			[ 'input' => 'b', 'output' => '2' ],
		];
		$ref = [
			[ 'input' => 'a', 'output' => '1' ],
			[ 'input' => 'b', 'output' => 'X' ],
		];

		$masterEncoded = base64_encode( json_encode( $master ) );
		$refEncoded = base64_encode( json_encode( $ref ) );

		// Install mock HTTP responses for master and ref (in that order)
		$this->installMockHttp( [
			$this->makeFakeHttpRequest( $refEncoded ),
			$this->makeFakeHttpRequest( $masterEncoded ),
		] );

		// Build a faux request with action=visualDiff and a dummy ref value
		$req = new FauxRequest( [ 'action' => 'visualDiff', 'ref' => 'deadbeef' ] );

		[ $html, ] = $this->executeSpecialPage( '', $req );

		// Title contains index and first-20-char snippets: inputs are 'b' and 'b' here
		$this->assertStringContainsString( 'Difference at index 1: b', $html );

		// The page should render the raw outputs inside the math-diff blocks
		$this->assertStringContainsString( '<div class="math-diff-master"><h4>master</h4>2', $html );
		$this->assertStringContainsString( '<div class="math-diff-ref"><h4>deadbeef</h4>X', $html );

		// Accept either escaped JSON blobs or the raw math-diff rendering.
		$hasEscapedA = strpos( $html, '&quot;output&quot;: &quot;2&quot;' ) !== false;
		$hasEscapedB = strpos( $html, '&quot;output&quot;: &quot;X&quot;' ) !== false;
		$hasRawA = strpos( $html, '<div class="math-diff-master"><h4>master</h4>2' ) !== false;
		$hasRawB = strpos( $html, '<div class="math-diff-ref"><h4>deadbeef</h4>X' ) !== false;

		$this->assertTrue(
			( $hasEscapedA && $hasEscapedB ) || ( $hasRawA && $hasRawB ),
			'Expected either escaped JSON blobs or raw math-diff outputs'
		);
	}
}
