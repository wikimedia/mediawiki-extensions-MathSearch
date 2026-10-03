<?php

class MathIdGeneratorTest extends MediaWikiIntegrationTestCase {

	private const WIKITEXT1 = <<<wikiText
	This is a test <math>E=mc^2</math>, and <math>a+b</math>
	and further down another <math id=CustomId>E=mc^2</math>
wikiText;

	/**
	 * @covers MathIdGenerator::getIdList
	 * @covers MathIdGenerator::getIdsFromContent
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

	/**
	 * @covers MathIdGenerator::getContentIdMap
	 * @covers MathIdGenerator::getRendererInput
	 */
	public function testChemAndVerbatimTags() {
		$idGen = new MathIdGenerator( '<nowiki><math>a</math></nowiki> <pre><math>b</math></pre> ' .
			'<source><math>c</math></source> <syntaxhighlight><math>d</math></syntaxhighlight> ' .
			'<code><math>x</math></code> <chem>H2O</chem> <ce>H2O</ce> <math display="block">x</math>', 42 );

		$this->assertSame( [
			'x' => [ 'math.42.0' ],
			'\\ce{H2O}' => [ 'math.42.1', 'math.42.2' ],
			'{\\displaystyle x}' => [ 'math.42.3' ],
		], $idGen->getContentIdMap() );
	}
}
