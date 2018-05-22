<?php

/**
 * MediaWiki MathSearch extension
 *
 * (c)2015 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @group MathSearch
 * Class MathSearchHooksTest
 */
class MathosphereDriverTest extends MediaWikiTestCase {

	private static $hasMathosphere;

	public static function setUpBeforeClass() {
		$m = new MathosphereDriver();
		self::$hasMathosphere = $m->checkBackend();
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		parent::setUp();

		if ( !self::$hasMathosphere ) {
			$this->markTestSkipped( "No compatible Mathosphere server configured." );
		}
	}

	public function testEmpty() {
		$m = MathosphereDriver::newFromWikitext( 'This is a test without formulae.' );
		$this->assertTrue( $m->analyze() );
		$rel = $m->getRelations();
		$this->assertEquals( 0, count( $rel ) );
	}

	public function testEmc2() {
		$m = MathosphereDriver::newFromWikitext(
			'The mass energy equivalence <math>E=mc^{2}</math> describes how energy \'\'E\'\'' .
			' relates to the mass \'\'m\'\' via the [[speed of light]] \'\'c\'\'' );
		$this->assertTrue( $m->analyze() );
		$rel = $m->getRelations();
		$this->assertEquals( 5, count( $rel ) );
	}

}