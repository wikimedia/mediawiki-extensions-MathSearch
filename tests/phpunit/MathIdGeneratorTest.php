<?php

class MathIdGeneratorTest extends MediaWikiTestCase {

	private const WIKITEXT1 = <<<wikiText
	This is a test <math>E=mc^2</math>, and <math>a+b</math>
	and further down another <math id=CustomId>E=mc^2</math>
wikiText;

	/**
	 * @covers MathIdGenerator::getIdList
	 * @covers MathIdGenerator::getIdsFromContent
	 * @outputBuffering disabled
	 */
	public function testSimple() {
		$idGen = new MathIdGenerator( self::WIKITEXT1, 42 );
		$output = $idGen->getIdList();
		$this->assertCount( 3, $output );
		$ids = $idGen->getIdsFromContent( 'E=mc^2' );
		$this->assertCount( 2, $ids );
		$id1 = $idGen->guessIdFromContent( 'E=mc^2' );
		$id2 = $idGen->guessIdFromContent( 'E=mc^2' );
		$id3 = $idGen->guessIdFromContent( 'E=mc^2' );
		$this->assertEquals( $id1, $id3 );
		$this->assertNotEquals( $id1, $id2, "1 and 2 should not be equal" );
		$this->assertEquals( "math.42.2", $id2 );
	}

	/**
	 * @covers MathIdGenerator::setUseCustomIds
	 * @covers MathIdGenerator::getIdList
	 * @covers MathIdGenerator::getIdsFromContent
	 * @outputBuffering disabled
	 */
	public function testCustomId() {
		$idGen = new MathIdGenerator( self::WIKITEXT1, 42 );
		$idGen->setUseCustomIds( true );
		$output = $idGen->getIdList();
		$this->assertCount( 3, $output );
		$ids = $idGen->getIdsFromContent( 'E=mc^2' );
		$this->assertCount( 2, $ids );
		$id1 = $idGen->guessIdFromContent( 'E=mc^2' );
		$id2 = $idGen->guessIdFromContent( 'E=mc^2' );
		$id3 = $idGen->guessIdFromContent( 'E=mc^2' );
		$this->assertEquals( $id1, $id3 );
		$this->assertNotEquals( $id1, $id2, "1 and 2 should not be equal" );
		$this->assertEquals( "CustomId", $id2 );
	}
}
