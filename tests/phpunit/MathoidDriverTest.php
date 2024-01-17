<?php

/**
 * MediaWiki MathSearch extension
 *
 * (c)2015 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @group MathSearch
 */
class MathoidDriverTest extends MediaWikiIntegrationTestCase {

	private static $hasMathoid;

	public static function setUpBeforeClass(): void {
		$m = new MathoidDriver();
		self::$hasMathoid = $m->checkBackend();
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( !self::$hasMathoid ) {
			$this->markTestSkipped( "No compatible Mathoid server configured." );
		}
	}

	/**
	 * @covers MathoidDriver::texvcInfo
	 * @covers MathoidDriver::getSuccess
	 * @covers MathoidDriver::getChecked
	 * @covers MathoidDriver::getIdentifiers
	 * @covers MathoidDriver::getRequiredPackages
	 * @covers MathoidDriver::getSpeech
	 */
	public function testSuccess() {
		$m = new MathoidDriver( '\\sin(x^2)' );
		$this->assertTrue( $m->texvcInfo() );
		$this->assertTrue( $m->getSuccess() );
		$this->assertEquals( '\\sin(x^{2})', $m->getChecked() );
		$this->assertEquals( [ 'x' ], $m->getIdentifiers() );
		$this->assertEquals( [], $m->getRequiredPackages() );
		$this->assertEquals( 'sine left-parenthesis x squared right-parenthesis', $m->getSpeech() );
	}

	/**
	 * @covers MathoidDriver::texvcInfo
	 * @covers MathoidDriver::getSuccess
	 * @covers MathoidDriver::getError
	 */
	public function testFail() {
		$m = new MathoidDriver( '\\sin(\\invalid)' );
		$this->assertTrue( $m->texvcInfo() );
		$this->assertFalse( $m->getSuccess() );
		$this->assertObjectHasProperty( 'message', $m->getError() );
	}

	/**
	 * @covers MathoidDriver::getSvg
	 */
	public function testFormats() {
		$m = new MathoidDriver( '\\sin(x^2)' );
		$this->assertStringContainsString( '<svg', $m->getSvg() );
	}
}
