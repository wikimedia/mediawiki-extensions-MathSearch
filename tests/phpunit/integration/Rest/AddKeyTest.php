<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration\Rest;

use MediaWiki\Extension\MathSearch\Keys\Ed25519Key;
use MediaWiki\Extension\MathSearch\Rest\AddKey;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\AddKey
 * @covers \MediaWiki\Extension\MathSearch\Keys\KeyStore
 * @group Database
 */
class AddKeyTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const int NOW = 1_800_000_000;

	private string $secretKey;
	private string $key;

	protected function setUp(): void {
		parent::setUp();
		ConvertibleTimestamp::setFakeTime( self::NOW );
		$this->overrideConfigValue( MainConfigNames::CanonicalServer, 'https://wiki.example' );
		$this->setGroupPermissions( 'trusted-test', 'mathtrustedkeys', true );
		$pair = sodium_crypto_sign_keypair();
		$this->secretKey = sodium_crypto_sign_secretkey( $pair );
		$this->key = (string)Ed25519Key::fromBytes( sodium_crypto_sign_publickey( $pair ) );
	}

	private function sign( string $name, int $timestamp ): string {
		$message = Ed25519Key::SIGNED_PREFIX . "mathsearch-key-registration wiki.example $name $timestamp";
		return Ed25519Key::encodeSignature( sodium_crypto_sign_detached( $message, $this->secretKey ) );
	}

	private function newHandler(): AddKey {
		return new AddKey(
			$this->getServiceContainer()->getService( 'MathSearch.KeyStore' ),
			$this->getServiceContainer()->getMainConfig()
		);
	}

	private function add( Authority $performer, int $timestamp = self::NOW, ?string $signature = null ): int {
		$handler = $this->newHandler();
		$body = [
			'key' => $this->key,
			'timestamp' => $timestamp,
			'signature' => $signature ?? $this->sign( $performer->getUser()->getName(), $timestamp ),
		];
		$response = $this->executeHandler( $handler, new RequestData( [
			'method' => 'POST',
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( $body ),
		] ), [], [], [], $body, $performer );
		return $response->getStatusCode();
	}

	private function isTrusted(): bool {
		$store = $this->getServiceContainer()->getService( 'MathSearch.KeyStore' );
		return $store->isTrusted( Ed25519Key::fromString( $this->key ) );
	}

	public function testAddsKeyWithValidSignature() {
		$performer = $this->getMutableTestUser( [ 'trusted-test' ] )->getAuthority();
		$this->assertSame( 204, $this->add( $performer ) );
		$this->assertTrue( $this->isTrusted() );
	}

	public function testRejectsCookieSessions() {
		$handler = $this->newHandler();
		$this->initHandler( $handler, new RequestData(), [], [], null, $this->getSession( false ) );
		$this->expectExceptionCode( 400 );
		$handler->checkSession();
	}

	public function testRejectsUserWithoutTheRight() {
		$this->expectExceptionCode( 403 );
		$this->add( $this->getMutableTestUser()->getAuthority() );
	}

	public function testRejectsSignatureForAnotherUser() {
		$this->expectExceptionCode( 400 );
		$performer = $this->getMutableTestUser( [ 'trusted-test' ] )->getAuthority();
		$this->add( $performer, self::NOW, $this->sign( 'Someone', self::NOW ) );
	}

	public function testRejectsOldTimestamp() {
		$this->expectExceptionCode( 400 );
		$this->add( $this->getMutableTestUser( [ 'trusted-test' ] )->getAuthority(), self::NOW - 301 );
	}

	public function testRejectsKeyOfAnotherUser() {
		$this->add( $this->getMutableTestUser( [ 'trusted-test' ] )->getAuthority() );
		$this->expectExceptionCode( 409 );
		$this->add( $this->getMutableTestUser( [ 'trusted-test' ] )->getAuthority() );
	}
}
