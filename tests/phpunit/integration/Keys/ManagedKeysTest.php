<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration\Keys;

use MediaWiki\Extension\MathSearch\Keys\Ed25519Key;
use MediaWiki\Extension\MathSearch\Keys\ManagedKeys;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Keys\ManagedKeys
 * @covers \MediaWiki\Extension\MathSearch\Keys\SigningKey
 * @covers \MediaWiki\Extension\MathSearch\Keys\Ed25519Key
 * @group Database
 */
class ManagedKeysTest extends MediaWikiIntegrationTestCase {

	private function newManagedKeys(): ManagedKeys {
		return $this->getServiceContainer()->getService( 'MathSearch.ManagedKeys' );
	}

	public function testCreatesATrustedKeyThatOnlyThePassphraseUnlocks() {
		$this->setGroupPermissions( 'trusted-test', 'mathtrustedkeys', true );
		$user = $this->getMutableTestUser( [ 'trusted-test' ] )->getUserIdentity();
		$keys = $this->newManagedKeys();

		$key = $keys->create( $user, 'passphrase' )->getKey();

		$this->assertSame( $key->getBytes(), $keys->getKey( $user )->getBytes() );
		$this->assertTrue( $this->getServiceContainer()->getService( 'MathSearch.KeyStore' )->isTrusted( $key ) );
		$this->assertSame( $key->getBytes(), $keys->unlock( $user, 'passphrase' )->getKey()->getBytes() );
		$this->assertNull( $keys->unlock( $user, 'other' ) );
		$this->assertNull( $keys->unlock( $user, '' ) );
	}

	public function testTheSecretIsEncrypted() {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$key = $this->newManagedKeys()->create( $user, '' )->getKey();

		$secret = $this->getDb()->newSelectQueryBuilder()
			->select( 'mmk_secret' )
			->from( 'math_managed_key' )
			->where( [ 'mmk_key' => $key->getBytes() ] )
			->fetchField();
		$this->assertSame( SODIUM_CRYPTO_SIGN_SECRETKEYBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES, strlen( $secret ) );
		$this->assertStringNotContainsString( $key->getBytes(), $secret );
	}

	public function testEmptyPassphrase() {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$keys = $this->newManagedKeys();
		$keys->create( $user, '' );

		$this->assertNotNull( $keys->unlock( $user, '' ) );
		$this->assertNull( $keys->unlock( $user, 'other' ) );
	}

	public function testANewKeyReplacesTheOldOne() {
		$this->setGroupPermissions( 'trusted-test', 'mathtrustedkeys', true );
		$user = $this->getMutableTestUser( [ 'trusted-test' ] )->getUserIdentity();
		$keys = $this->newManagedKeys();
		$old = $keys->create( $user, 'forgotten' )->getKey();

		$new = $keys->create( $user, 'new' )->getKey();

		$this->assertSame( $new->getBytes(), $keys->getKey( $user )->getBytes() );
		$this->assertNull( $keys->unlock( $user, 'forgotten' ) );
		$this->assertNotNull( $keys->unlock( $user, 'new' ) );
		$this->assertTrue( $this->getServiceContainer()->getService( 'MathSearch.KeyStore' )->isTrusted( $old ) );
	}

	public function testSignaturesVerifyLikeThoseFromKubo() {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$signingKey = $this->newManagedKeys()->create( $user, '' );

		$signature = Ed25519Key::encodeSignature( $signingKey->sign( 'a message' ) );
		$this->assertTrue( $signingKey->getKey()->verify( 'a message', $signature ) );
	}

	public function testNoKeyNoUnlock() {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$this->assertNull( $this->newManagedKeys()->getKey( $user ) );
		$this->assertNull( $this->newManagedKeys()->unlock( $user, '' ) );
	}
}
