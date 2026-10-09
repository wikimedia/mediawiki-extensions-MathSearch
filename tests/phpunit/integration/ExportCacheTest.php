<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

require_once dirname( __DIR__, 3 ) . '/maintenance/ExportCache.php';

use ExportMathCache;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \ExportMathCache
 * @group Database
 */
class ExportCacheTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return ExportMathCache::class;
	}

	public function testExportsTheStoredHash() {
		$hash = md5( 'E=mc^2', true );
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'mathlog' )
			->row( [ 'math_inputhash' => $hash, 'math_input' => 'E=mc^2' ] )
			->caller( __METHOD__ )
			->execute();

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->assertSame( [ [ 'inputhash' => bin2hex( $hash ), 'input' => 'E=mc^2' ] ],
			json_decode( $this->getActualOutputForAssertion(), true ) );
	}
}
