<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MediaWiki\Extension\MathSearch\Engine\BaseX;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;

/**
 * @covers \MediaWiki\Extension\MathSearch\Engine\BaseX
 */
class BaseXTest extends MediaWikiIntegrationTestCase {

	use MockHttpTrait;

	public function testUpdateStoresTheDocumentUnderTheHash() {
		$this->overrideConfigValues( [
			'MathSearchBaseXBackendUrl' => 'http://basex.test/rest/',
			'MathSearchBaseXDatabaseName' => 'math',
		] );
		$history = [];
		$stack = HandlerStack::create( new MockHandler( [ new Response( 201 ) ] ) );
		$stack->push( Middleware::history( $history ) );
		$this->installMockHttp( new Client( [ 'handler' => $stack ] ) );

		$this->assertTrue( ( new BaseX() )->update( '<math/>', 'abc' ) );
		$this->assertSame( 'http://basex.test/rest/math/abc', (string)$history[0]['request']->getUri() );
	}
}
