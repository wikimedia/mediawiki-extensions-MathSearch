<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration\Rest;

use MediaWiki\Extension\MathSearch\Rest\Check;
use MediaWiki\Extension\MathSearch\Rest\FormulaStore;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;

/**
 * Runs the real checker, so the answers here can be compared against the live
 * RESTBase API rather than against a mock of our own expectations.
 *
 * @covers \MediaWiki\Extension\MathSearch\Rest\Check
 * @group Math
 */
class CheckTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private function newHandler(): Check {
		return new Check(
			$this->getServiceContainer()->getService( 'Math.CheckerFactory' ),
			// The store is exercised by FormulaStoreTest; keep this off the database.
			$this->createMock( FormulaStore::class )
		);
	}

	private function check( string $type, string $q ): array {
		$response = $this->executeHandler( $this->newHandler(), new RequestData( [
			'pathParams' => [ 'type' => $type ],
			'method' => 'POST',
			'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
			'parsedBody' => [ 'q' => $q ],
		] ) );
		$this->assertSame( 200, $response->getStatusCode() );
		return json_decode( (string)$response->getBody(), true );
	}

	/** The values the live API returns for the same input. */
	public function testTexAnswersAsTheLiveApiDoes() {
		$this->assertSame( [
			'success' => true,
			'checked' => 'E=mc^{2}',
			'requiredPackages' => [],
			'identifiers' => [ 'E', 'm', 'c' ],
			'endsWithDot' => false,
		], $this->check( 'tex', 'E=mc^{2}' ) );

		$this->assertSame( [ 'x', '\\alpha' ], $this->check( 'tex', '\\sin x + \\alpha' )['identifiers'] );
	}

	public function testChemReportsMhchemAndNoIdentifiers() {
		$body = $this->check( 'chem', '\\ce{H2O}' );
		$this->assertTrue( $body['success'] );
		$this->assertSame( [ 'mhchem' ], $body['requiredPackages'] );
		// Mathoid answers {\ce {H2O}}. Math expands mhchem while checking since
		// T348975, so checked is the expanded form and the address follows it.
		$this->assertStringStartsWith( '{\\mathrm {H}', $body['checked'] );
		// Mathoid reports none for chemistry, and the local tree would otherwise
		// leak texified output such as \mathrm{H}.
		$this->assertSame( [], $body['identifiers'] );
	}

	/** Chemistry is only valid on the chem endpoint, as on the live API. */
	public function testChemIsRejectedAsTex() {
		$this->expectException( HttpException::class );
		$this->check( 'tex', '\\ce{H2O}' );
	}

	public function testEndsWithDotIsReported() {
		$this->assertTrue( $this->check( 'tex', 'a.' )['endsWithDot'] );
	}
}
