<?php

class MathDebugTest extends MediaWikiTestCase {

	/** @var string */
	private $someLog = "Test log.";

	/** @var string */
	private $someTex = "\\sin(x)+1";

	/**
	 * @covers MathObject::getLog
	 */
	public function testSetLog() {
		$mo = new MathObject( $this->someTex );
		$mo->setLog( $this->someLog );
		$this->assertEquals( $this->someLog, $mo->getLog(), "Simple getter test" );
		$mo->writeToDatabase();
		$mo2 = new MathObject( $this->someTex );
		$mo2->readFromDatabase();
		$this->assertEquals( $this->someLog, $mo2->getLog(), "Read from DB." );
	}
}
