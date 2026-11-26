<?php

namespace MediaWiki\Extension\MathSearch\Tests\Unit\Ipfs;

use MediaWiki\Extension\MathSearch\Ipfs\Cid;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Ipfs\Cid
 */
class CidTest extends MediaWikiUnitTestCase {

	public static function provideCids() {
		return [
			'raw leaf' => [ 'bafkreibm6jg3ux5qumhcn2b3flc3tyu6dmlb4xa7u5bf44yegnrjhc4yeq', "\x01\x55" ],
			'dag-pb node' => [ 'bafybeidavv7hmv4rsstwioalbhlvq66gyqsat4kukwg4imlqmgmxuaxthu', "\x01\x70" ],
		];
	}

	/** @dataProvider provideCids */
	public function testRoundTrip( string $cid, string $prefix ) {
		$bytes = Cid::fromString( $cid )->getBytes();
		$this->assertSame( 36, strlen( $bytes ) );
		$this->assertStringStartsWith( "$prefix\x12\x20", $bytes );
		$this->assertSame( $cid, (string)Cid::fromBytes( $bytes ) );
	}

	public static function provideInvalid() {
		return [
			'CIDv0' => [ 'QmPZ9gcCEpqKTo6aq61g2nXGUhM4iCL3ewB6LDXZCtioEB' ],
			'too short' => [ 'bafkreibm6jg3ux5qumhcn2b3flc3tyu6dmlb4xa7u5bf44yegnrjhc4ye' ],
			'not base32' => [ 'bafkreibm6jg3ux5qumhcn2b3flc3tyu6dmlb4xa7u5bf44yegnrjhc4ye1' ],
			'padding bits set' => [ 'bafkreibm6jg3ux5qumhcn2b3flc3tyu6dmlb4xa7u5bf44yegnrjhc4yer' ],
			'trailing newline' => [ "bafkreibm6jg3ux5qumhcn2b3flc3tyu6dmlb4xa7u5bf44yegnrjhc4yeq\n" ],
			'dag-cbor' => [ 'bafyreibigfeptj5dnlityuuvk5f5w63ik2w3oousnpi75o6bbkfx4mhas4' ],
		];
	}

	public function testRejectsTrailingBytes() {
		$bytes = Cid::fromString( 'bafkreibm6jg3ux5qumhcn2b3flc3tyu6dmlb4xa7u5bf44yegnrjhc4yeq' )->getBytes();
		$this->assertNull( Cid::fromBytes( "$bytes\n" ) );
	}

	/** @dataProvider provideInvalid */
	public function testRejects( string $cid ) {
		$this->assertNull( Cid::fromString( $cid ) );
	}
}
