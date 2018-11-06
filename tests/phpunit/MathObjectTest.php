<?php
/**
 * Test the mathObject script.
 *
 * @group MathSearch
 */
class MathObjectTest extends MediaWikiTestCase {

	private $HTMLComment = <<<'EOT'
<!-- HTML Comment <math>a</math> -->
EOT;

	private $noWiki = <<<'EOT'
<nowiki><math>b</math></nowiki>
EOT;

	private $attributes = <<<'EOT'
<math x=x1 y=y1>c</math>
EOT;
	private $longSample = <<<'EOT'
== heading ==
<math> x^2 </math>
some more text
<math> x^3 </math>
EOT;
	protected static $hasRestbase;

	public static function setUpBeforeClass() {
		$rbi = new MathRestbaseInterface();
		self::$hasRestbase = $rbi->checkBackend( true );
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		parent::setUp();
		if ( !self::$hasRestbase ) {
			$this->markTestSkipped( "Can not connect to Restbase Math interface." );
		}
	}

	/**
	 * @covers MathObject::extractMathTagsFromWikiText
	 */
	public function test() {
		$comment = MathObject::extractMathTagsFromWikiText( $this->HTMLComment );
		$this->assertEquals( 0, count( $comment ), 'Math tags in comments should be ignored.' );
		$noWiki = MathObject::extractMathTagsFromWikiText( $this->noWiki );
		$this->assertEquals( 0, count( $noWiki ) );
		$attributeTest = MathObject::extractMathTagsFromWikiText( $this->attributes );
		$this->assertEquals( 1, count( $attributeTest ) );
		$expected = [ 'x' => 'x1', 'y' => 'y1' ];
		$first = array_shift( $attributeTest );
		$this->assertEquals( $expected, $first[2] );
		$longMatch = MathObject::extractMathTagsFromWikiText( $this->longSample );
		$this->assertEquals( 2, count( $longMatch ) );
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
