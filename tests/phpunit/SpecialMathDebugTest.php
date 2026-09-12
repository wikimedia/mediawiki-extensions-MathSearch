<?php

namespace MediaWiki\Tests\Specials;

use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathReferenceData;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Request\FauxRequest;
use SpecialMathDebug;

/**
 * @covers \SpecialMathDebug
 */
class SpecialMathDebugTest extends SpecialPageTestBase {
	use \MockHttpTrait;

	private ?RendererFactory $rendererFactory = null;

	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		return new SpecialMathDebug(
			$services->getHttpRequestFactory(),
			$this->rendererFactory ?? $services->getService( 'Math.RendererFactory' ),
		);
	}

	/**
	 * Record which modes the page renders in, without reaching Mathoid.
	 *
	 * @param string[] &$modes
	 */
	private function stubRendererFactory( array &$modes ): void {
		$factory = $this->createMock( RendererFactory::class );
		$factory->method( 'getRenderer' )->willReturnCallback(
			function ( string $tex, array $params, string $mode ) use ( &$modes ) {
				$modes[] = $mode;
				$renderer = $this->createMock( MathRenderer::class );
				$renderer->method( 'getHtmlOutput' )
					->willReturn( '<span class="stub">' . $mode . '</span>' );
				return $renderer;
			}
		);
		$this->rendererFactory = $factory;
	}

	public function testVisualDiffReportsHashDifferenceForLegacyData() {
		// These cover the comparison itself; stub the renderer so the
		// columns beside it do not reach out to Mathoid.
		$renderedModes = [];
		$this->stubRendererFactory( $renderedModes );
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

		// Columns are named after the arguments, not the revisions they hold.
		$this->assertStringContainsString( '<div class="math-diff-master"><h4>base</h4>2', $html );
		$this->assertStringContainsString( '<div class="math-diff-ref"><h4>ref</h4>X', $html );

		// Accept either escaped JSON blobs or the raw math-diff rendering.
		$hasEscapedA = strpos( $html, '&quot;output&quot;: &quot;2&quot;' ) !== false;
		$hasEscapedB = strpos( $html, '&quot;output&quot;: &quot;X&quot;' ) !== false;
		$hasRawA = strpos( $html, '<div class="math-diff-master"><h4>base</h4>2' ) !== false;
		$hasRawB = strpos( $html, '<div class="math-diff-ref"><h4>ref</h4>X' ) !== false;

		$this->assertTrue(
			( $hasEscapedA && $hasEscapedB ) || ( $hasRawA && $hasRawB ),
			'Expected either escaped JSON blobs or raw math-diff outputs'
		);
	}

	public function testVisualDiffAlignsAddedAndRemovedTestsByInputHash() {
		// These cover the comparison itself; stub the renderer so the
		// columns beside it do not reach out to Mathoid.
		$renderedModes = [];
		$this->stubRendererFactory( $renderedModes );
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
		// These cover the comparison itself; stub the renderer so the
		// columns beside it do not reach out to Mathoid.
		$renderedModes = [];
		$this->stubRendererFactory( $renderedModes );
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

	/**
	 * The comparison is keyed by hash directly, so the legacy grouping path
	 * stays out of the way of what this asserts.
	 *
	 * @return array[]
	 */
	private static function renderingCases(): array {
		$hash = md5( 'a+b' );
		return [
			[ $hash => [ 'input' => 'a+b', 'output' => '<math>master</math>' ] ],
			[ $hash => [ 'input' => 'a+b', 'output' => '<math>ref</math>' ] ],
		];
	}

	public function testVisualDiffRendersServerAndClientSideBySide() {
		[ $master, $ref ] = self::renderingCases();
		$modes = [];
		$this->stubRendererFactory( $modes );
		$this->installMockHttp( [
			$this->makeFakeHttpRequest( base64_encode( json_encode( $ref ) ) ),
			$this->makeFakeHttpRequest( base64_encode( json_encode( $master ) ) ),
		] );

		[ $html, ] = $this->executeSpecialPage( '',
			new FauxRequest( [ 'action' => 'visualDiff', 'ref' => 'deadbeef' ] ) );

		$this->assertStringContainsString( 'server SVG', $html );
		$this->assertStringContainsString( 'client MathJax', $html );
		$this->assertSame(
			[ MathConfig::MODE_MATHML, MathConfig::MODE_NATIVE_JAX ],
			$modes,
			'the two columns must come from the server and the client pipeline'
		);
	}

	public function testVisualDiffOmitsRenderingWhenSvgIsNone() {
		[ $master, $ref ] = self::renderingCases();
		$modes = [];
		$this->stubRendererFactory( $modes );
		$this->installMockHttp( [
			$this->makeFakeHttpRequest( base64_encode( json_encode( $ref ) ) ),
			$this->makeFakeHttpRequest( base64_encode( json_encode( $master ) ) ),
		] );

		[ $html, ] = $this->executeSpecialPage( '',
			new FauxRequest( [ 'action' => 'visualDiff', 'ref' => 'deadbeef', 'svg' => 'none' ] ) );

		$this->assertStringNotContainsString( 'server SVG', $html );
		$this->assertStringNotContainsString( 'client MathJax', $html );
		$this->assertSame( [], $modes, 'nothing should be rendered when svg=none' );
		// The MathML comparison this page already did is unaffected.
		$this->assertStringContainsString( 'Difference at position 1', $html );
	}

	public function testStoredReferencesAreHiddenFromClientSideMathJax() {
		[ $master, $ref ] = self::renderingCases();
		$modes = [];
		$this->stubRendererFactory( $modes );
		$this->installMockHttp( [
			$this->makeFakeHttpRequest( base64_encode( json_encode( $ref ) ) ),
			$this->makeFakeHttpRequest( base64_encode( json_encode( $master ) ) ),
		] );

		[ $html, ] = $this->executeSpecialPage( '',
			new FauxRequest( [ 'action' => 'visualDiff', 'ref' => 'deadbeef' ] ) );

		// Without this MathJax would typeset the references themselves, and the
		// page would compare two MathJax renderings instead of the stored markup.
		$this->assertStringContainsString( '<math class="mathjax_ignore">master</math>', $html );
		$this->assertStringContainsString( '<math class="mathjax_ignore">ref</math>', $html );
	}

	public function testLinksTheTaskAndShortensRevisionLabels() {
		$hash = md5( 'a+b' );
		$master = [ $hash => [ 'input' => 'a+b', 'output' => '<math>master</math>' ] ];
		$ref = [ $hash => [
			'input' => 'a+b',
			'output' => '<math>ref</math>',
			'bug' => 'T434428',
		] ];
		$modes = [];
		$this->stubRendererFactory( $modes );
		$this->installMockHttp( [
			$this->makeFakeHttpRequest( base64_encode( json_encode( $ref ) ) ),
			$this->makeFakeHttpRequest( base64_encode( json_encode( $master ) ) ),
		] );

		$sha = str_repeat( 'a1b2c3d4e5', 4 );
		[ $html, ] = $this->executeSpecialPage( '',
			new FauxRequest( [ 'action' => 'visualDiff', 'ref' => $sha ] ) );

		$this->assertStringContainsString(
			'<a href="https://phabricator.wikimedia.org/T434428">T434428</a>', $html );
		// Columns are named after the arguments; the revisions are printed once.
		$this->assertStringContainsString( '<h4>base</h4>', $html );
		$this->assertStringContainsString( '<h4>ref</h4>', $html );
		$this->assertSame( 1, substr_count( $html, $sha ) );
	}

	public function testOmitsTheTaskWhenNoneIsSpecified() {
		[ $master, $ref ] = self::renderingCases();
		$modes = [];
		$this->stubRendererFactory( $modes );
		$this->installMockHttp( [
			$this->makeFakeHttpRequest( base64_encode( json_encode( $ref ) ) ),
			$this->makeFakeHttpRequest( base64_encode( json_encode( $master ) ) ),
		] );

		[ $html, ] = $this->executeSpecialPage( '',
			new FauxRequest( [ 'action' => 'visualDiff', 'ref' => 'deadbeef' ] ) );

		$this->assertStringNotContainsString( 'phabricator.wikimedia.org', $html );
	}
}
