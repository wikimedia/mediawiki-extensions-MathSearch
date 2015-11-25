<?php

class MathIdGeneratorTest extends MediaWikiTestCase {
	private $wikiText1 = <<<wikiText
	This is a test <math>E=mc^2</math>, and <math>a+b</math>
	and further down another <math id=CustomId>E=mc^2</math>
wikiText;

	/**
	 * @outputBuffering disabled
	 */
	public function testSimple() {
		$idGen = new MathIdGenerator( $this->wikiText1, 42 );
		$output = $idGen->getIdList();
		$this->assertEquals( 3, count( $output ) );
		$ids = $idGen->getIdsFromContent( 'E=mc^2' );
		$this->assertEquals( 2, count( $ids ) );
		$id1 = $idGen->guessIdFromContent( 'E=mc^2' );
		$id2 = $idGen->guessIdFromContent( 'E=mc^2' );
		$id3 = $idGen->guessIdFromContent( 'E=mc^2' );
		$this->assertEquals( $id1, $id3 );
		$this->assertNotEquals( $id1, $id2, "1 and 2 shoud not be equal" );
		$this->assertEquals( "math.42.3", $id2 );
	}
	/**
	 * @outputBuffering disabled
	 */
	public function testCustomId() {
		$idGen = new MathIdGenerator( $this->wikiText1, 42 );
		$idGen->setUseCustomIds( true );
		$output = $idGen->getIdList();
		$this->assertEquals( 3, count( $output ) );
		$ids = $idGen->getIdsFromContent( 'E=mc^2' );
		$this->assertEquals( 2, count( $ids ) );
		$id1 = $idGen->guessIdFromContent( 'E=mc^2' );
		$id2 = $idGen->guessIdFromContent( 'E=mc^2' );
		$id3 = $idGen->guessIdFromContent( 'E=mc^2' );
		$this->assertEquals( $id1, $id3 );
		$this->assertNotEquals( $id1, $id2, "1 and 2 shoud not be equal" );
		$this->assertEquals( "CustomId", $id2 );
	}
}
