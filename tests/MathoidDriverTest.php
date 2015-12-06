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
class MathoidDriverTest extends MediaWikiTestCase {
	private static $hasMathoid;


	public static function setUpBeforeClass() {
		$m = new MathoidDriver();
		self::$hasMathoid = $m->checkBackend();
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		parent::setUp();

		if ( !self::$hasMathoid ) {
			$this->markTestSkipped( "No compatible Mathoid server configured." );
		}
	}

	public function testSuccess() {
		$m = new MathoidDriver( '\\sin(x^2)' );
		$this->assertTrue( $m->texvcInfo() );
		$this->assertTrue( $m->getSuccess() );
		$this->assertEquals( '\\sin(x^{2})', $m->getChecked() );
		$this->assertEquals( array( 'x' ), $m->getIdentifiers() );
		$this->assertEquals( array(), $m->getRequiredPackages() );
		$this->assertEquals( 'sine left-parenthesis x squared right-parenthesis', $m->getSpeech() );
	}

	public function testFail() {
		$m = new MathoidDriver( '\\sin(\\invalid)' );
		$this->assertTrue( $m->texvcInfo() );
		$this->assertFalse( $m->getSuccess() );
		$this->assertObjectHasAttribute( 'message', $m->getError() );
	}
}
