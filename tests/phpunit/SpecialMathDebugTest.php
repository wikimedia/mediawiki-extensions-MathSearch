<?php

namespace MediaWiki\Tests\Specials;

use MediaWiki\Extension\Math\MathReferenceData;
use MediaWiki\Request\FauxRequest;
use SpecialMathDebug;

/**
 * @covers \SpecialMathDebug
 */
class SpecialMathDebugTest extends SpecialPageTestBase {
	use \MockHttpTrait;

	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		return new SpecialMathDebug(
			$services->getHttpRequestFactory(),
			$services->getService( 'Math.RendererFactory' ),
		);
	}

	public function testVisualDiffReportsHashDifferenceForLegacyData() {
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

		$this->assertStringContainsString( 'Difference at position 2', $html );
		$this->assertStringContainsString( 'Input: <code>b</code>', $html );
		$this->assertStringContainsString(
			'Input hash: <code>' . hash( MathReferenceData::HASH_ALGORITHM, 'b' ) . '</code>',
			$html
		);

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

	public function testVisualDiffAlignsAddedAndRemovedTestsByInputHash() {
		$aHash = hash( MathReferenceData::HASH_ALGORITHM, 'a' );
		$bHash = hash( MathReferenceData::HASH_ALGORITHM, 'b' );
		$cHash = hash( MathReferenceData::HASH_ALGORITHM, 'c' );
		$xHash = hash( MathReferenceData::HASH_ALGORITHM, 'x' );
		$master = [
			$aHash => [ 'input' => 'a', 'output' => '1' ],
			$bHash => [ 'input' => 'b', 'output' => '2' ],
			$cHash => [ 'input' => 'c', 'output' => '3' ],
		];
		$ref = [
			$aHash => [ 'input' => 'a', 'output' => '1' ],
			$xHash => [ 'input' => 'x', 'output' => 'new' ],
			$bHash => [ 'input' => 'b', 'output' => '2' ],
		];

		$this->installMockHttp( [
			$this->makeFakeHttpRequest( base64_encode( json_encode( $ref ) ) ),
			$this->makeFakeHttpRequest( base64_encode( json_encode( $master ) ) ),
		] );

		$req = new FauxRequest( [ 'action' => 'visualDiff', 'ref' => 'deadbeef' ] );

		[ $html, ] = $this->executeSpecialPage( '', $req );

		$this->assertStringContainsString( 'New test at position 2', $html );
		$this->assertStringContainsString( 'Input: <code>x</code>', $html );
		$this->assertStringContainsString( 'Input hash: <code>' . $xHash . '</code>', $html );
		$this->assertStringContainsString( 'Removed test at position 3', $html );
		$this->assertStringContainsString( 'Input: <code>c</code>', $html );
		$this->assertStringContainsString( 'Input hash: <code>' . $cHash . '</code>', $html );
		$this->assertStringNotContainsString( 'Difference at', $html );
	}

	public function testVisualDiffDistinguishesTestParameters() {
		$xHash = hash( MathReferenceData::HASH_ALGORITHM, 'x' );
		$master = [
			$xHash => [
				'input' => 'x',
				'outputs' => [
					[ 'params' => [ 'display' => 'block' ], 'output' => 'block' ],
					[ 'output' => 'default' ],
				],
			],
		];
		$ref = [
			$xHash => [
				'input' => 'x',
				'outputs' => [
					[ 'params' => [ 'display' => 'block' ], 'output' => 'block' ],
					[ 'params' => [ 'display' => 'inline' ], 'output' => 'inline' ],
					[ 'output' => 'default' ],
				],
			],
		];

		$this->installMockHttp( [
			$this->makeFakeHttpRequest( base64_encode( json_encode( $ref ) ) ),
			$this->makeFakeHttpRequest( base64_encode( json_encode( $master ) ) ),
		] );

		$req = new FauxRequest( [ 'action' => 'visualDiff', 'ref' => 'deadbeef' ] );

		[ $html, ] = $this->executeSpecialPage( '', $req );

		$this->assertStringContainsString( 'New test at position 1.2', $html );
		$this->assertStringContainsString( 'Input: <code>x</code>', $html );
		$this->assertStringContainsString( 'Input hash: <code>' . $xHash . '</code>', $html );
		$this->assertStringNotContainsString( 'Difference at', $html );
	}
}
