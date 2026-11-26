<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration\Rest;

use MediaWiki\Extension\MathSearch\Ipfs\Cid;
use MediaWiki\Extension\MathSearch\Keys\CidSignatures;
use MediaWiki\Extension\MathSearch\Keys\Ed25519Key;
use MediaWiki\Extension\MathSearch\Rest\Signatures;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\Signatures
 * @covers \MediaWiki\Extension\MathSearch\Keys\CidSignatures
 * @group Database
 */
class SignaturesTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const string CID = 'bafkreibm6jg3ux5qumhcn2b3flc3tyu6dmlb4xa7u5bf44yegnrjhc4yeq';

	private function get( string $cid ): array {
		$handler = new Signatures( $this->getServiceContainer()->getService( 'MathSearch.CidSignatures' ) );
		return $this->executeHandlerAndGetBodyData( $handler,
			new RequestData( [ 'pathParams' => [ 'cid' => $cid ] ] ) );
	}

	public function testListsTheSignaturesOfAllUsers() {
		$managedKeys = $this->getServiceContainer()->getService( 'MathSearch.ManagedKeys' );
		$cid = Cid::fromString( self::CID );
		$expected = [];
		foreach ( [ $this->getMutableTestUser(), $this->getMutableTestUser() ] as $testUser ) {
			$user = $testUser->getUserIdentity();
			$signingKey = $managedKeys->create( $user, '' );
			$key = $signingKey->getKey();
			$signature = $signingKey->sign( CidSignatures::message( $cid ) );
			$this->getServiceContainer()->getService( 'MathSearch.CidSignatures' )->add( $cid, $key, $signature );
			$expected[] = [ 'key' => (string)$key, 'signature' => Ed25519Key::encodeSignature( $signature ) ];
		}

		$this->assertEqualsCanonicalizing( $expected, $this->get( self::CID ) );
	}

	public function testUnsignedCidHasNoSignatures() {
		$this->assertSame( [], $this->get( self::CID ) );
	}

	public function testMalformedCidIsRejected() {
		$this->expectExceptionCode( 400 );
		$this->get( 'QmNotACidV1' );
	}
}
