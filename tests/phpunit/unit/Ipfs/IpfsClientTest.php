<?php

namespace MediaWiki\Extension\MathSearch\Tests\Unit\Ipfs;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MediaWiki\Extension\MathSearch\Ipfs\IpfsClient;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Ipfs\IpfsClient
 */
class IpfsClientTest extends MediaWikiUnitTestCase {

	public function testAddsWithTheImportProfile() {
		$requests = [];
		$stack = HandlerStack::create( new MockHandler( [
			new Response( 200, [], '{"Name":"a.txt","Hash":"bafkreiexample","Size":"5"}' ),
		] ) );
		$stack->push( Middleware::history( $requests ) );
		$client = new IpfsClient( new Client( [ 'handler' => $stack ] ), 'http://ipfs.example:5001' );

		$this->assertSame( 'bafkreiexample', $client->add( __FILE__, 'a.txt' ) );

		$request = $requests[0]['request'];
		$this->assertSame( 'http://ipfs.example:5001/api/v0/add', (string)$request->getUri()->withQuery( '' ) );
		parse_str( $request->getUri()->getQuery(), $query );
		$this->assertSame( [
			'cid-version' => '1',
			'raw-leaves' => 'true',
			'hash' => 'sha2-256',
			'chunker' => 'size-1048576',
			'max-file-links' => '1024',
			'pin' => 'true',
		], $query );
		$this->assertStringContainsString( 'filename="a.txt"', (string)$request->getBody() );
	}
}
