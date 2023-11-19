<?php

class MathDebugTest extends MediaWikiIntegrationTestCase {

	private const SOME_LOG = "Test log.";
	private const SOME_TEX = "\\sin(x)+1";

	/**
	 * @covers MathObject::getLog
	 */
	public function testSetLog() {
		$mo = new MathObject( self::SOME_TEX );
		$mo->setLog( self::SOME_LOG );
		$this->assertEquals( self::SOME_LOG, $mo->getLog(), "Simple getter test" );
		$mo->writeToCache();
		$mo2 = new MathObject( self::SOME_TEX );
		$mo2->readFromCache();
		$this->assertEquals( self::SOME_LOG, $mo2->getLog(), "Read from DB." );
	}
}
