<?php
/**
 * @group MathSearch
 */
class MathObjectTest extends MediaWikiTestCase {

	private const HTML_COMMENT = <<<'EOT'
<!-- HTML Comment <math>a</math> -->
EOT;

	private const NOWIKI = <<<'EOT'
<nowiki><math>b</math></nowiki>
EOT;

	private const ATTRIBUTES = <<<'EOT'
<math x=x1 y=y1>c</math>
EOT;

	private const LONG_SAMPLE = <<<'EOT'
== heading ==
<math> x^2 </math>
some more text
<math> x^3 </math>
EOT;

	protected static $hasRestbase;

	public static function setUpBeforeClass() : void {
		$rbi = new MathRestbaseInterface();
		self::$hasRestbase = $rbi->checkBackend( true );
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() : void {
		$this->markTestSkipped( "MathObject test temporary disabled" ); // T249428
		parent::setUp();
		if ( !self::$hasRestbase ) {
			$this->markTestSkipped( "Can not connect to Restbase Math interface." );
		}
	}

	/**
	 * @covers MathObject::extractMathTagsFromWikiText
	 */
	public function test() {
		$comment = MathObject::extractMathTagsFromWikiText( self::HTML_COMMENT );
		$this->assertCount( 0, $comment, 'Math tags in comments should be ignored.' );
		$noWiki = MathObject::extractMathTagsFromWikiText( self::NOWIKI );
		$this->assertCount( 0, $noWiki, 'Math tags in no-wiki tags should be ignored.' );
		$attributeTest = MathObject::extractMathTagsFromWikiText( self::ATTRIBUTES );
		$this->assertCount( 1, $attributeTest );
		$expected = [ 'x' => 'x1', 'y' => 'y1' ];
		$first = array_shift( $attributeTest );
		$this->assertEquals( $expected, $first[2] );
		$longMatch = MathObject::extractMathTagsFromWikiText( self::LONG_SAMPLE );
		$this->assertCount( 2, $longMatch );
		$first = array_shift( $longMatch );
		$this->assertEquals( ' x^2 ', $first[1] );
		$second = array_shift( $longMatch );
		$this->assertEquals( ' x^3 ', $second[1] );
	}

	/**
	 * @covers MathObject::cloneFromRenderer
	 */
	public function testAlttext() {
		$r = new MathMathML( 'E=mc^2' );
		$r->render();
		$mo = MathObject::cloneFromRenderer( $r );
		$this->assertEquals( 'upper E equals m c squared', $mo->getMathMlAltText() );
	}
}
