<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

require_once dirname( __DIR__, 3 ) . '/maintenance/NormalizeTexvc.php';

use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use NormalizeTexvc;

/**
 * @covers \NormalizeTexvc
 */
class NormalizeTexvcTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return NormalizeTexvc::class;
	}

	public function testWritesTheValidInputsAndTheirOutput() {
		$inputs = $this->getNewTempFile();
		$good = $this->getNewTempFile();
		$normalized = $this->getNewTempFile();
		file_put_contents( $inputs, json_encode( [
			'c' => '{\displaystyle b^2}',
			'b' => '\frac{1}{',
			'a' => 'a^2',
		] ) );

		$this->maintenance->loadWithArgv( [ $inputs, $good, $normalized ] );
		$this->maintenance->execute();

		$this->assertSame( [ 'a' => 'a^2', 'c' => '{\displaystyle b^2}' ],
			json_decode( file_get_contents( $good ), true ) );
		$this->assertSame( [ 'a' => 'a^{2}', 'c' => '{\displaystyle b^{2}}' ],
			json_decode( file_get_contents( $normalized ), true ) );
		$this->expectOutputString( "2 of 3 inputs are valid\n" );
	}
}
