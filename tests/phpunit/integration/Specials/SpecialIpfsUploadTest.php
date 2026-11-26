<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration\Specials;

use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\MathSearch\Ipfs\Cid;
use MediaWiki\Extension\MathSearch\Ipfs\IpfsClient;
use MediaWiki\Extension\MathSearch\Keys\CidSignatures;
use MediaWiki\Extension\MathSearch\Keys\Ed25519Key;
use MediaWiki\Extension\MathSearch\Specials\SpecialIpfsUpload;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use SpecialPageTestBase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Specials\SpecialIpfsUpload
 * @covers \MediaWiki\Extension\MathSearch\Keys\CidSignatures
 * @group Database
 */
class SpecialIpfsUploadTest extends SpecialPageTestBase {

	private const string CID = 'bafkreibm6jg3ux5qumhcn2b3flc3tyu6dmlb4xa7u5bf44yegnrjhc4yeq';

	private int $uploads = 0;

	protected function newSpecialPage() {
		$ipfs = $this->createMock( IpfsClient::class );
		$ipfs->method( 'add' )->with( __FILE__, 'a.txt' )->willReturnCallback( function () {
			$this->uploads++;
			return self::CID;
		} );
		$services = $this->getServiceContainer();
		return new SpecialIpfsUpload( $ipfs,
			$services->getService( 'MathSearch.ManagedKeys' ), $services->getService( 'MathSearch.CidSignatures' ) );
	}

	private function upload( User $user, string $passphrase, bool $newKey = false ): string {
		$request = new FauxRequest( [ 'wppassphrase' => $passphrase, 'wpnewkey' => $newKey ? '1' : '' ], true );
		$request->setUpload( 'wpfile', [
			'name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => __FILE__, 'size' => 5, 'error' => 0,
		] );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $user );
		return $html;
	}

	private function newTrustedUser(): User {
		$this->setGroupPermissions( 'trusted-test', 'mathtrustedkeys', true );
		return $this->getMutableTestUser( [ 'trusted-test' ] )->getUser();
	}

	public function testRequiresTheRight() {
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( '', null, null, $this->getTestUser()->getAuthority() );
	}

	public function testStoresTheSignatureOfTheCid() {
		$user = $this->newTrustedUser();

		$html = $this->upload( $user, 'passphrase' );

		$key = (string)$this->getServiceContainer()->getService( 'MathSearch.ManagedKeys' )->getKey( $user );
		$this->assertStringContainsString( '(ipfsupload-done: ' . self::CID . ", $key)", $html );
		$cid = Cid::fromString( self::CID );
		$signatures = $this->getServiceContainer()->getService( 'MathSearch.CidSignatures' )->get( $cid );
		$this->assertCount( 1, $signatures );
		$this->assertSame( $key, $signatures[0]['key'] );
		$this->assertTrue( Ed25519Key::fromString( $key )
			->verify( CidSignatures::message( $cid ), $signatures[0]['signature'] ) );
	}

	public function testWrongPassphraseUploadsNothing() {
		$user = $this->newTrustedUser();
		$this->upload( $user, 'passphrase' );

		$html = $this->upload( $user, 'wrong' );

		$this->assertStringContainsString( '(ipfsupload-badpassphrase)', $html );
		$this->assertSame( 1, $this->uploads );
	}

	public function testNewKeyAfterAForgottenPassphrase() {
		$user = $this->newTrustedUser();
		$this->upload( $user, 'forgotten' );
		$managedKeys = $this->getServiceContainer()->getService( 'MathSearch.ManagedKeys' );
		$old = (string)$managedKeys->getKey( $user );

		$html = $this->upload( $user, 'new', true );

		$new = (string)$managedKeys->getKey( $user );
		$this->assertNotSame( $old, $new );
		$this->assertStringContainsString( "$new)", $html );
	}

	public function testResubmittingANewKeyKeepsIt() {
		$user = $this->newTrustedUser();
		$this->upload( $user, 'old' );
		$this->upload( $user, 'new', true );
		$managedKeys = $this->getServiceContainer()->getService( 'MathSearch.ManagedKeys' );
		$key = (string)$managedKeys->getKey( $user );

		$this->upload( $user, 'new', true );

		$this->assertSame( $key, (string)$managedKeys->getKey( $user ) );
	}

	public function testShowsThePublicKey() {
		$user = $this->newTrustedUser();
		[ $html ] = $this->executeSpecialPage( '', null, 'qqx', $user );
		$this->assertStringContainsString( '(ipfsupload-nokey)', $html );
		$this->upload( $user, 'passphrase' );
		$key = (string)$this->getServiceContainer()->getService( 'MathSearch.ManagedKeys' )->getKey( $user );

		[ $html ] = $this->executeSpecialPage( '', null, 'qqx', $user );

		$this->assertStringContainsString( $key, $html );
	}

	private function download( User $user, string $passphrase ): array {
		$request = new FauxRequest( [ 'wppassphrase' => $passphrase, 'wpdownload' => '1' ], true );
		return $this->executeSpecialPage( '', $request, 'qqx', $user );
	}

	public function testDownloadsThePrivateKey() {
		$user = $this->newTrustedUser();
		$this->upload( $user, 'passphrase' );
		$key = (string)$this->getServiceContainer()->getService( 'MathSearch.ManagedKeys' )->getKey( $user );

		[ $file, $response ] = $this->download( $user, 'passphrase' );

		$this->assertSame( "attachment; filename=\"$key.key\"", $response->getHeader( 'Content-Disposition' ) );
		$this->assertSame( "\x08\x01\x12\x40", substr( $file, 0, 4 ) );
		$this->assertSame( Ed25519Key::fromString( $key )->getBytes(), substr( $file, 36 ) );
	}

	public function testWrongPassphraseDownloadsNothing() {
		$user = $this->newTrustedUser();
		$this->upload( $user, 'passphrase' );

		[ $html, $response ] = $this->download( $user, 'wrong' );

		$this->assertNull( $response->getHeader( 'Content-Disposition' ) );
		$this->assertStringContainsString( '(ipfsupload-badpassphrase)', $html );
	}
}
