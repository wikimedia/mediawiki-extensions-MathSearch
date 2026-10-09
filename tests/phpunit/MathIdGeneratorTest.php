<?php

class MathIdGeneratorTest extends MediaWikiIntegrationTestCase {

	private const WIKITEXT1 = <<<wikiText
	This is a test <math>E=mc^2</math>, and <math>a+b</math>
	and further down another <math id=CustomId>E=mc^2</math>
wikiText;

	/**
	 * @covers MathIdGenerator::getIdList
	 * @covers MathIdGenerator::addAnchorsTo
	 */
	public function testSimple() {
		$idGen = new MathIdGenerator( self::WIKITEXT1, 42 );

		$this->assertSame( [ 'math.42.0', 'math.42.1', 'CustomId' ], $idGen->getIdList() );
		$this->assertSame( "\tThis is a test <math id=\"math.42.0\">E=mc^2</math>, " .
			"and <math id=\"math.42.1\">a+b</math>\n\tand further down another <math id=CustomId>E=mc^2</math>",
			$idGen->addAnchorsTo() );
	}

	/**
	 * @covers MathIdGenerator::getAnchor
	 */
	public function testRepeatedIdAttribute() {
		$idGen = new MathIdGenerator( '<math id="a">x</math> <math id="a">y</math> <math id="a">y</math>', 42 );

		$this->assertSame( [ 'a', 'math.42.1', 'math.42.2' ], $idGen->getIdList() );
	}

	/**
	 * @covers MathIdGenerator::getAnchor
	 */
	public function testIdAttributeLongerThanTheIndex() {
		$fifty = str_repeat( 'x', 50 );
		$idGen = new MathIdGenerator( "<math id=\"$fifty\">x</math> <math id=\"{$fifty}y\">y</math>", 42 );

		$this->assertSame( [ $fifty, 'math.42.1' ], $idGen->getIdList() );
	}

	/**
	 * @covers MathIdGenerator::getAnchor
	 */
	public function testNumericIdAttribute() {
		$idGen = new MathIdGenerator( '<math id="1">x</math> <math id="1">y</math>', 42 );

		$this->assertSame( [ '1', 'math.42.1' ], $idGen->getIdList() );
	}

	/**
	 * @covers MathIdGenerator::addAnchorsTo
	 */
	public function testAddAnchorsTo() {
		$wikiText = '<!-- <math>c</math> --> <nowiki><math>n</math></nowiki> <math>x</math> <chem>H2O</chem> ' .
			'<math display="block">y</math> <math id="own">z</math> <math/>';
		$idGen = new MathIdGenerator( $wikiText, 42 );

		$this->assertSame( '<!-- <math>c</math> --> <nowiki><math>n</math></nowiki> <math id="math.42.0">x</math> ' .
			'<chem id="math.42.1">H2O</chem> <math id="math.42.2" display="block">y</math> <math id="own">z</math> ' .
			'<math id="math.42.4"/>', $idGen->addAnchorsTo() );
	}

	/**
	 * @covers MathIdGenerator::addAnchorsTo
	 * @covers MathIdGenerator::getRendererInput
	 */
	public function testTagNamesInAnyCase() {
		$wikiText = '<MATH>x</MATH> <mAtH display="block">y</mAtH> <CHEM>H2O</CHEM>';
		$idGen = new MathIdGenerator( $wikiText, 42 );

		$this->assertSame( [ 'math.42.0', 'math.42.1', 'math.42.2' ], $idGen->getIdList() );
		$this->assertSame( '<MATH id="math.42.0">x</MATH> <mAtH id="math.42.1" display="block">y</mAtH> ' .
			'<CHEM id="math.42.2">H2O</CHEM>', $idGen->addAnchorsTo() );
		$this->assertSame( [ [ 'x', [] ], [ 'y', [ 'display' => 'block' ] ], [ '\\ce{H2O}', [ 'chem' => true ] ] ],
			array_map( $idGen->getRendererInput( ... ), array_values( $idGen->getMathTags() ) ) );
	}

	/**
	 * @covers MathIdGenerator::getIdList
	 * @covers MathIdGenerator::getRendererInput
	 */
	public function testChemAndVerbatimTags() {
		$idGen = new MathIdGenerator( '<nowiki><math>a</math></nowiki> <pre><math>b</math></pre> ' .
			'<source><math>c</math></source> <syntaxhighlight><math>d</math></syntaxhighlight> ' .
			'<code><math>x</math></code> <chem>H2O</chem> <ce>H2O</ce> <math display="block">x</math>', 42 );

		$this->assertSame( [ 'math.42.0', 'math.42.1', 'math.42.2', 'math.42.3' ], $idGen->getIdList() );
		$this->assertSame( [ [ 'x', [] ], [ '\\ce{H2O}', [ 'chem' => true ] ], [ '\\ce{H2O}', [ 'chem' => true ] ],
			[ 'x', [ 'display' => 'block' ] ] ],
			array_map( $idGen->getRendererInput( ... ), array_values( $idGen->getMathTags() ) ) );
	}

	/**
	 * @covers MathIdGenerator::isClosed
	 */
	public function testIsClosed() {
		$idGen = new MathIdGenerator( '<math>a</math> <math>b</math > <math/> <chem>H2O</chem> <math>c and the rest' );

		$closed = array_map( $idGen->isClosed( ... ), array_values( $idGen->getMathTags() ) );
		$this->assertSame( [ true, true, true, true, false ], $closed );
	}
}
