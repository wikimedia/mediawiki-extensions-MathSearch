<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration\Specials;

use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\MathSearch\Ipfs\IpfsClient;
use MediaWiki\Extension\MathSearch\Specials\SpecialIpfsUpload;
use MediaWiki\Request\FauxRequest;
use SpecialPageTestBase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Specials\SpecialIpfsUpload
 * @group Database
 */
class SpecialIpfsUploadTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		$ipfs = $this->createMock( IpfsClient::class );
		$ipfs->method( 'add' )->with( __FILE__, 'a.txt' )->willReturn( 'bafkreiexample' );
		return new SpecialIpfsUpload( $ipfs );
	}

	public function testRequiresTheRight() {
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( '', null, null, $this->getTestUser()->getAuthority() );
	}

	public function testShowsTheCid() {
		$this->setGroupPermissions( 'trusted-test', 'mathtrustedkeys', true );
		$request = new FauxRequest( [], true );
		$request->setUpload( 'wpfile', [
			'name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => __FILE__, 'size' => 5, 'error' => 0,
		] );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx',
			$this->getMutableTestUser( [ 'trusted-test' ] )->getUser() );

		$this->assertStringContainsString( '(ipfsupload-done: bafkreiexample)', $html );
	}
}
