<?php

namespace MediaWiki\Extension\MathSearch\Tests\Rest;

use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Extension\MathSearch\Rest\FormulaStore;
use MediaWiki\Extension\MathSearch\Rest\Render;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\Render
 */
class RenderTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	private const HASH = '4c0004393a88f350a93bcef62106d556c7fc827b';

	private array $modes = [];

	private function newHandler( ?array $formula, ?MathRenderer $renderer = null ): Render {
		$store = $this->createMock( FormulaStore::class );
		$store->method( 'resolve' )->willReturn( $formula );

		$factory = $this->createMock( RendererFactory::class );
		$factory->method( 'getRenderer' )->willReturnCallback(
			function ( string $tex, array $params, string $mode ) use ( $renderer ) {
				$this->modes[] = $mode;
				return $renderer ?? $this->createMock( MathRenderer::class );
			}
		);
		return new Render( $store, $factory );
	}

	private function newRenderer( string $svg = '<svg/>', string $mml = '<math/>' ): MathRenderer {
		$renderer = $this->createMock( MathRenderer::class );
		$renderer->method( 'render' )->willReturn( true );
		$renderer->method( 'getSvg' )->willReturn( $svg );
		$renderer->method( 'getMathml' )->willReturn( $mml );
		return $renderer;
	}

	private function request( string $format ): RequestData {
		return new RequestData( [ 'pathParams' => [ 'format' => $format, 'hash' => self::HASH ] ] );
	}

	public function testServesSvgWithItsProfileAndAddress() {
		$response = $this->executeHandler(
			$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $this->newRenderer() ),
			$this->request( 'svg' )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( '<svg/>', (string)$response->getBody() );
		$this->assertSame(
			'image/svg+xml; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/SVG/1.0.0"',
			$response->getHeaderLine( 'Content-Type' )
		);
		$this->assertSame( self::HASH, $response->getHeaderLine( 'x-resource-location' ) );
		// Raw SVG from an API path needs this.
		$this->assertSame( 'nosniff', $response->getHeaderLine( 'X-Content-Type-Options' ) );
		$this->assertSame( '"' . sha1( '<svg/>' ) . '"', $response->getHeaderLine( 'ETag' ) );
		// The reference rendering comes from the same service the wiki uses.
		$this->assertSame( [ MathConfig::MODE_MATHML ], $this->modes );
	}

	public function testServesMathmlWithItsOwnProfile() {
		$response = $this->executeHandler(
			$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $this->newRenderer() ),
			$this->request( 'mml' )
		);
		$this->assertSame( '<math/>', (string)$response->getBody() );
		$this->assertStringStartsWith( 'application/mathml+xml', $response->getHeaderLine( 'Content-Type' ) );
	}

	public function testIsNotFoundForAnUnknownAddress() {
		try {
			$this->executeHandler( $this->newHandler( null ), $this->request( 'svg' ) );
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 404, $e->getCode() );
		}
	}

	public function testReportsAServerRenderingFailure() {
		$renderer = $this->createMock( MathRenderer::class );
		$renderer->method( 'render' )->willReturn( false );
		$renderer->method( 'getLastError' )->willReturn( 'mathoid is down' );

		try {
			$this->executeHandler(
				$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $renderer ),
				$this->request( 'svg' )
			);
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 500, $e->getCode() );
			$this->assertStringContainsString( 'mathoid is down', $e->getMessage() );
		}
	}

	/** An empty rendering is a failure, not an empty 200. */
	public function testRejectsAnEmptyRendering() {
		try {
			$this->executeHandler(
				$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ], $this->newRenderer( '' ) ),
				$this->request( 'svg' )
			);
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 500, $e->getCode() );
		}
	}
}
