<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration\Rest;

use MediaWiki\Extension\MathSearch\Keys\Ed25519Key;
use MediaWiki\Extension\MathSearch\Rest\Key;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\Key
 * @covers \MediaWiki\Extension\MathSearch\Keys\KeyStore
 * @group Database
 */
class KeyTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const string KEY = '12D3KooWJsXtSD4VxENSTkRo3XFqmV7fzimqcMd47rw5ngxYN7qW';

	private function isTrusted( string $key ): bool {
		$handler = new Key( $this->getServiceContainer()->getService( 'MathSearch.KeyStore' ) );
		try {
			$response = $this->executeHandler( $handler, new RequestData( [ 'pathParams' => [ 'key' => $key ] ] ) );
		} catch ( HttpException $e ) {
			if ( $e->getCode() !== 404 ) {
				throw $e;
			}
			return false;
		}
		$this->assertSame( 204, $response->getStatusCode() );
		return true;
	}

	private function addKey( UserIdentity $user ): void {
		$store = $this->getServiceContainer()->getService( 'MathSearch.KeyStore' );
		$store->add( $user, Ed25519Key::fromString( self::KEY ) );
	}

	public function testKeyIsTrustedOnlyWhileOwnerHasTheRight() {
		$this->setGroupPermissions( 'trusted-test', 'mathtrustedkeys', true );
		$user = $this->getMutableTestUser( [ 'trusted-test' ] )->getUserIdentity();
		$this->addKey( $user );
		$this->assertTrue( $this->isTrusted( self::KEY ) );

		$this->getServiceContainer()->getUserGroupManager()->removeUserFromGroup( $user, 'trusted-test' );
		$this->assertFalse( $this->isTrusted( self::KEY ) );
	}

	public function testKeyOfSysopIsTrustedByDefault() {
		$this->addKey( $this->getMutableTestUser( [ 'sysop' ] )->getUserIdentity() );
		$this->assertTrue( $this->isTrusted( self::KEY ) );
	}

	public function testKeyOfUserWithoutTheRightIsNotTrusted() {
		$this->addKey( $this->getMutableTestUser()->getUserIdentity() );
		$this->assertFalse( $this->isTrusted( self::KEY ) );
	}

	public function testUnknownKeyIsNotTrusted() {
		$this->assertFalse( $this->isTrusted( self::KEY ) );
	}

	public function testMalformedKeyIsRejected() {
		$this->expectExceptionCode( 400 );
		$this->isTrusted( '12D3KooWEXAMPLE' );
	}
}
