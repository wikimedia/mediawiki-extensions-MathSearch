<?php

/**
 * @group MathSearch
 */
class MathSearchUtilsTest extends MediaWikiIntegrationTestCase {

	private const EXPECTED_OUTPUT = '{| class="wikitable sortable"
|-
! s !! i
|-
| a
| 1
|-
| b
| 2
|}
';

	/**
	 * @covers MathSearchUtils::dbRowToWikiTable
	 */
	public function test() {
		$this->markTestSkipped( __METHOD__ . " temporary deactivated." );
		$dbw = $this->getServiceContainer()
			->getConnectionProvider()
			->getPrimaryDatabase();
		$dbw->query( 'CREATE TEMPORARY TABLE IF NOT EXISTS tmp_math_util_test (s TEXT, i INT)', __METHOD__ );
		$dbw->insert(
			"tmp_math_util_test", [ [ 's' => 'a', 'i' => 1 ], [ 's' => 'b', 'i' => 2 ] ], __METHOD__
		);
		$cols = [ 's', 'i' ];
		$res = $dbw->select( 'tmp_math_util_test', $cols, [], __METHOD__ );
		$this->assertEquals( self::EXPECTED_OUTPUT, MathSearchUtils::dbRowToWikiTable( $res, $cols ) );
	}
}
