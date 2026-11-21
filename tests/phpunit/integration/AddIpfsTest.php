<?php

namespace MediaWiki\Extension\MathSearch\Tests;

require_once dirname( __DIR__, 3 ) . '/maintenance/AddIpfs.php';

use AddIpfs;
use MediaWiki\Extension\MathSearch\Ipfs\IpfsClient;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MockHttpTrait;

/**
 * @covers \AddIpfs
 */
class AddIpfsTest extends MaintenanceBaseTestCase {

	use MockHttpTrait;

	protected function getMaintenanceClass() {
		return AddIpfs::class;
	}

	public function testAddsTheDownloadThroughTheIpfsClient() {
		$this->installMockHttp( 'file content' );
		$ipfs = $this->createMock( IpfsClient::class );
		$ipfs->expects( $this->once() )->method( 'add' )
			->willReturnCallback( function ( string $path, string $name ) {
				$this->assertSame( 'file content', file_get_contents( $path ) );
				$this->assertSame( 'paper.pdf', $name );
				return 'bafkreiexample';
			} );
		$this->setService( 'MathSearch.IpfsClient', $ipfs );

		$this->maintenance->loadWithArgv( [ 'https://example.org/files/paper.pdf' ] );
		$this->maintenance->execute();

		$this->expectOutputString( "bafkreiexample\n" );
	}
}
