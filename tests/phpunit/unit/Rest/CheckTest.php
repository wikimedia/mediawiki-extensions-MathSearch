<?php

namespace MediaWiki\Extension\MathSearch\Tests\Rest;

use MediaWiki\Extension\Math\InputCheck\InputCheckFactory;
use MediaWiki\Extension\Math\InputCheck\LocalChecker;
use MediaWiki\Extension\MathSearch\Rest\Check;
use MediaWiki\Extension\MathSearch\Rest\FormulaStore;
use MediaWiki\Message\Message;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\Check
 */
class CheckTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	private function newHandler( LocalChecker $checker, ?FormulaStore $store = null ): Check {
		$factory = $this->createMock( InputCheckFactory::class );
		$factory->method( 'newLocalChecker' )->willReturn( $checker );
		return new Check( $factory, $store ?? $this->createMock( FormulaStore::class ) );
	}

	private function newRequest( string $type, string $q ): RequestData {
		return new RequestData( [
			'pathParams' => [ 'type' => $type ],
			'method' => 'POST',
			'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
			'parsedBody' => [ 'q' => $q ],
		] );
	}

	/** A rejected formula answers with the checker's own message, not a generic one. */
	public function testReportsTheCheckerMessageForInvalidInput() {
		$message = $this->createMock( Message::class );
		$message->method( 'inLanguage' )->willReturnSelf();
		$message->method( 'text' )->willReturn( 'Failed to parse' );

		$checker = $this->createMock( LocalChecker::class );
		$checker->method( 'isValid' )->willReturn( false );
		$checker->method( 'getError' )->willReturn( $message );

		$this->expectException( HttpException::class );
		$this->expectExceptionMessage( 'Failed to parse' );
		$this->executeHandler( $this->newHandler( $checker ), $this->newRequest( 'tex', '\\frac' ) );
	}

	/** A checker that reports no message must still produce a 400 rather than a 500. */
	public function testFallsBackWhenTheCheckerGivesNoMessage() {
		$checker = $this->createMock( LocalChecker::class );
		$checker->method( 'isValid' )->willReturn( false );
		$checker->method( 'getError' )->willReturn( null );

		try {
			$this->executeHandler( $this->newHandler( $checker ), $this->newRequest( 'tex', '\\frac' ) );
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 400, $e->getCode() );
			$this->assertSame( 'Invalid input', $e->getMessage() );
		}
	}

	public function testAnswersWithTheAddressOfAValidFormula() {
		$checker = $this->createMock( LocalChecker::class );
		$checker->method( 'isValid' )->willReturn( true );
		$checker->method( 'getValidTex' )->willReturn( 'E=mc^{2}' );

		$store = $this->createMock( FormulaStore::class );
		$store->expects( $this->once() )
			->method( 'remember' )
			->with( 'E=mc^{2}', 'tex' )
			->willReturn( '4c0004393a88f350a93bcef62106d556c7fc827b' );

		$response = $this->executeHandler(
			$this->newHandler( $checker, $store ),
			$this->newRequest( 'tex', 'E=mc^{2}' )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame(
			'4c0004393a88f350a93bcef62106d556c7fc827b',
			$response->getHeaderLine( 'x-resource-location' )
		);
		$this->assertSame( 'no-cache', $response->getHeaderLine( 'Cache-Control' ) );

		$body = json_decode( (string)$response->getBody(), true );
		$this->assertTrue( $body['success'] );
		$this->assertSame( 'E=mc^{2}', $body['checked'] );
		$this->assertSame( [], $body['requiredPackages'] );
		$this->assertFalse( $body['endsWithDot'] );
	}

	public function testReportsMhchemForAChemCheck() {
		$checker = $this->createMock( LocalChecker::class );
		$checker->method( 'isValid' )->willReturn( true );
		$checker->method( 'getValidTex' )->willReturn( '{\\ce {H2O}}' );

		$response = $this->executeHandler(
			$this->newHandler( $checker ),
			$this->newRequest( 'chem', '\\ce{H2O}' )
		);

		$body = json_decode( (string)$response->getBody(), true );
		$this->assertSame( [ 'mhchem' ], $body['requiredPackages'] );
		// Mathoid reports none for chemistry, so neither does this.
		$this->assertSame( [], $body['identifiers'] );
	}

	/** isValid() and getValidTex() are set together; if they ever disagree, say so. */
	public function testRefusesToPassOnAnUncheckedFormula() {
		$checker = $this->createMock( LocalChecker::class );
		$checker->method( 'isValid' )->willReturn( true );
		$checker->method( 'getValidTex' )->willReturn( null );

		try {
			$this->executeHandler( $this->newHandler( $checker ), $this->newRequest( 'tex', 'x' ) );
			$this->fail( 'expected an HttpException' );
		} catch ( HttpException $e ) {
			$this->assertSame( 500, $e->getCode() );
		}
	}
}
