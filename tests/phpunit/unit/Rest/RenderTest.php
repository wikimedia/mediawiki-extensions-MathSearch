<?php

namespace MediaWiki\Extension\MathSearch\Tests\Rest;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Extension\MathSearch\Rest\FormulaStore;
use MediaWiki\Extension\MathSearch\Rest\Render;
use MediaWiki\Extension\MathSearch\Rest\SvgRenderer;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiUnitTestCase;
use RuntimeException;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\Render
 */
class RenderTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	private const HASH = '4c0004393a88f350a93bcef62106d556c7fc827b';

	private array $modes = [];

	/**
	 * @param array|null $formula
	 * @param MathRenderer|null $renderer
	 * @param bool $svgEnabled
	 * @param string|null $storedSvg what mathlog holds for the MathML
	 * @param string|null $browserSvg what the browser returns; it must not start if this is null
	 */
	private function newHandler(
		?array $formula, ?MathRenderer $renderer = null, bool $svgEnabled = false,
		?string $storedSvg = null, ?string $browserSvg = null
	): Render {
		$store = $this->createMock( FormulaStore::class );
		$store->method( 'resolve' )->willReturn( $formula );
		$store->method( 'getSvg' )->willReturn( $storedSvg );
		$store->expects( $browserSvg === null ? $this->never() : $this->once() )
			->method( 'saveSvg' )->with( $formula, '<math/>', $browserSvg );

		$factory = $this->createMock( RendererFactory::class );
		$factory->method( 'getRenderer' )->willReturnCallback(
			function ( string $tex, array $params, string $mode ) use ( $renderer ) {
				$this->modes[] = $mode;
				return $renderer ?? $this->createMock( MathRenderer::class );
			}
		);

		$svgRenderer = $this->createMock( SvgRenderer::class );
		$svgRenderer->expects( $browserSvg === null ? $this->never() : $this->once() )
			->method( 'render' )->with( '<math/>' )->willReturn( $browserSvg );

		return new Render( $store, $factory, $svgRenderer,
			new HashConfig( [ 'MathSearchRestSvg' => $svgEnabled ] ) );
	}

	private function newRenderer( string $mml = '<math/>' ): MathRenderer {
		$renderer = $this->createMock( MathRenderer::class );
		$renderer->method( 'render' )->willReturn( true );
		$renderer->method( 'getMathml' )->willReturn( $mml );
		return $renderer;
	}

	private function request( string $format ): RequestData {
		return new RequestData( [ 'pathParams' => [ 'format' => $format, 'hash' => self::HASH ] ] );
	}

	public function testServesMathmlWithItsProfileAndAddress() {
		$response = $this->executeHandler(
			$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $this->newRenderer() ),
			$this->request( 'mml' )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( '<math/>', (string)$response->getBody() );
		$this->assertSame(
			'application/mathml+xml; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/MathML/1.0.0"',
			$response->getHeaderLine( 'Content-Type' )
		);
		$this->assertSame( self::HASH, $response->getHeaderLine( 'x-resource-location' ) );
		$this->assertSame( 'nosniff', $response->getHeaderLine( 'X-Content-Type-Options' ) );
		$this->assertSame( '"' . sha1( '<math/>' ) . '"', $response->getHeaderLine( 'ETag' ) );
		$this->assertSame( [ MathConfig::MODE_NATIVE_MML ], $this->modes );
	}

	public function testSvgIsOffByDefault() {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );
		$this->executeHandler(
			$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $this->newRenderer() ),
			$this->request( 'svg' )
		);
	}

	public function testRendersSvgInTheBrowserAndStoresIt() {
		$response = $this->executeHandler(
			$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $this->newRenderer(), true, null, '<svg/>' ),
			$this->request( 'svg' )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( '<svg/>', (string)$response->getBody() );
		$this->assertSame(
			'image/svg+xml; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/SVG/1.0.0"',
			$response->getHeaderLine( 'Content-Type' )
		);
		$this->assertSame( "default-src 'none'; style-src 'unsafe-inline'",
			$response->getHeaderLine( 'Content-Security-Policy' ) );
	}

	public function testServesTheStoredSvgWithoutABrowser() {
		$response = $this->executeHandler(
			$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $this->newRenderer(), true, '<svg/>' ),
			$this->request( 'svg' )
		);
		$this->assertSame( '<svg/>', (string)$response->getBody() );
	}

	public function testReportsAFailedBrowserRun() {
		$store = $this->createMock( FormulaStore::class );
		$store->method( 'resolve' )->willReturn( [ 'q' => 'x', 'type' => 'tex' ] );
		$factory = $this->createMock( RendererFactory::class );
		$factory->method( 'getRenderer' )->willReturn( $this->newRenderer() );
		$svgRenderer = $this->createMock( SvgRenderer::class );
		$svgRenderer->method( 'render' )->willThrowException( new RuntimeException( 'Could not start npm' ) );
		$handler = new Render( $store, $factory, $svgRenderer,
			new HashConfig( [ 'MathSearchRestSvg' => true ] ) );

		try {
			$this->executeHandler( $handler, $this->request( 'svg' ) );
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 500, $e->getCode() );
			$this->assertStringContainsString( 'Could not start npm', $e->getMessage() );
		}
	}

	public function testIsNotFoundForAnUnknownAddress() {
		try {
			$this->executeHandler( $this->newHandler( null ), $this->request( 'mml' ) );
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 404, $e->getCode() );
		}
	}

	public function testReportsAServerRenderingFailure() {
		$renderer = $this->createMock( MathRenderer::class );
		$renderer->method( 'render' )->willReturn( false );
		$renderer->method( 'getLastError' )->willReturn( 'rendering failed' );

		try {
			$this->executeHandler(
				$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $renderer ),
				$this->request( 'mml' )
			);
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 500, $e->getCode() );
			$this->assertStringContainsString( 'rendering failed', $e->getMessage() );
		}
	}

	/** An empty rendering is a failure, not an empty 200. */
	public function testRejectsAnEmptyRendering() {
		try {
			$this->executeHandler(
				$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $this->newRenderer( '' ) ),
				$this->request( 'mml' )
			);
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 500, $e->getCode() );
		}
	}
}
