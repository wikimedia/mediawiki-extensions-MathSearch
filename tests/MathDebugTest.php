<?php

class MathDebugTest extends MediaWikiTestCase {
	private $someLog = "Test log.";

	private $someTex = "\\sin(x)+1";

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
