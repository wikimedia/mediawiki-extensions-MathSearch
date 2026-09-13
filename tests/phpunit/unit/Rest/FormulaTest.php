<?php

namespace MediaWiki\Extension\MathSearch\Tests\Rest;

use MediaWiki\Extension\MathSearch\Rest\Formula;
use MediaWiki\Extension\MathSearch\Rest\FormulaHash;
use MediaWiki\Extension\MathSearch\Rest\FormulaStore;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\Formula
 */
class FormulaTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	private const HASH = '4c0004393a88f350a93bcef62106d556c7fc827b';

	/** @param array{q:string,type:string}|null $formula */
	private function newHandler( ?array $formula ): Formula {
		$store = $this->createMock( FormulaStore::class );
		$store->method( 'resolve' )->willReturn( $formula );
		return new Formula( $store );
	}

	private function request( string $hash ): RequestData {
		return new RequestData( [ 'pathParams' => [ 'hash' => $hash ] ] );
	}

	public function testReturnsTheInputTheAddressStandsFor() {
		$response = $this->executeHandler(
			$this->newHandler( [ 'q' => 'E=mc^{2}', 'type' => 'tex' ] ),
			$this->request( self::HASH )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		// The same body RESTBase answers with.
		$this->assertSame(
			[ 'q' => 'E=mc^{2}', 'type' => 'tex' ],
			json_decode( (string)$response->getBody(), true )
		);
		$this->assertSame( self::HASH, $response->getHeaderLine( 'x-resource-location' ) );
		$this->assertSame( 's-maxage=864000, max-age=86400', $response->getHeaderLine( 'Cache-Control' ) );
	}

	public function testIsNotFoundForAnUnknownAddress() {
		try {
			$this->executeHandler( $this->newHandler( null ), $this->request( self::HASH ) );
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 404, $e->getCode() );
		}
	}

	/** The address in the response is derived from the input, not echoed back. */
	public function testAdvertisesTheAddressOfWhatItReturns() {
		$response = $this->executeHandler(
			$this->newHandler( [ 'q' => 'a/b', 'type' => 'tex' ] ),
			$this->request( self::HASH )
		);
		$this->assertSame(
			FormulaHash::fromTex( 'a/b' ),
			$response->getHeaderLine( 'x-resource-location' )
		);
	}
}
