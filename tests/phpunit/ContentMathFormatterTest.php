<?php

use DataValues\StringValue;
use DataValues\NumberValue;
use Wikibase\Lib\Formatters\SnakFormatter;

/**
 * Test the results of MathFormatter
 *
 * @covers MathFormatter
 *
 * @group MathSearch
 *
 * @license GPL-2.0-or-later
 */
class ContentMathFormatterTest extends MediaWikiTestCase {

	const SOME_TEX = 'a^2+b^2 < c^2';

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( 'wgMathDisableTexFilter', 'always' );
	}

	/**
	 * Checks the
	 * @covers MathFormatter::__construct()
	 */
	public function testBasics() {
		$formatter = new ContentMathFormatter( SnakFormatter::FORMAT_PLAIN );
		// check if the format input was corretly passed to the class
		$this->assertSame( SnakFormatter::FORMAT_PLAIN, $formatter->getFormat(), 'test getFormat' );
	}

	/**
	 * @expectedException ValueFormatters\Exceptions\MismatchingDataValueTypeException
	 */
	public function testNotStringValue() {
		$formatter = new ContentMathFormatter( SnakFormatter::FORMAT_PLAIN );
		$formatter->format( new NumberValue( 0 ) );
	}

	/**
	 * @expectedException ValueFormatters\Exceptions\MismatchingDataValueTypeException
	 */
	public function testNullValue() {
		$formatter = new ContentMathFormatter( SnakFormatter::FORMAT_PLAIN );
		$formatter->format( null );
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testUnknownFormat() {
		new ContentMathFormatter( 'unknown/unknown' );
	}

	public function testFormatPlain() {
		$formatter = new ContentMathFormatter( SnakFormatter::FORMAT_PLAIN );
		$value = new StringValue( self::SOME_TEX );
		$resultFormat = $formatter->format( $value );
		$this->assertSame( self::SOME_TEX, $resultFormat );
	}

	public function testFormatHtml() {
		$formatter = new ContentMathFormatter( SnakFormatter::FORMAT_HTML );
		$value = new StringValue( self::SOME_TEX );
		$resultFormat = $formatter->format( $value );
		$this->assertContains( '</math>', $resultFormat, 'Result must contain math-tag' );
	}

	public function testFormatDiffHtml() {
		$formatter = new ContentMathFormatter( SnakFormatter::FORMAT_HTML_DIFF );
		$value = new StringValue( self::SOME_TEX );
		$resultFormat = $formatter->format( $value );
		$this->assertContains( '</math>', $resultFormat, 'Result must contain math-tag' );
		$this->assertContains( '</h4>', $resultFormat, 'Result must contain a <h4> tag' );
		$this->assertContains( '</code>', $resultFormat, 'Result must contain a <code> tag' );
		$this->assertContains( 'wb-details', $resultFormat, 'Result must contain wb-details class' );
		$this->assertContains(
			htmlspecialchars( self::SOME_TEX ),
			$resultFormat,
			'Result must contain the TeX source'
		);
	}

	public function testFormatXWiki() {
		$tex = self::SOME_TEX;
		$formatter = new ContentMathFormatter( SnakFormatter::FORMAT_WIKI );
		$value = new StringValue( self::SOME_TEX );
		$resultFormat = $formatter->format( $value );
		$this->assertSame( "<math>$tex</math>", $resultFormat, 'Tex wasn\'t properly wrapped' );
	}

}
