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


	public function test() {
		$comment = MathObject::extractMathTagsFromWikiText( $this->HTMLComment );
		$this->assertEquals( 0, sizeof( $comment ) );
		$noWiki = MathObject::extractMathTagsFromWikiText( $this->noWiki );
		$this->assertEquals( 0, sizeof( $noWiki ) );
		$attributeTest = MathObject::extractMathTagsFromWikiText( $this->attributes );
		$this->assertEquals( 1, sizeof( $attributeTest ) );
		$expected = array( 'x' => 'x1', 'y' => 'y1' );
		$first = array_shift( $attributeTest );
		$this->assertEquals( $expected, $first[2] );
		$longMatch = MathObject::extractMathTagsFromWikiText( $this->longSample );
		$this->assertEquals( 2, sizeof( $longMatch ) );
		$first = array_shift( $longMatch );
		$this->assertEquals( ' x^2 ', $first[1] );
		$second = array_shift( $longMatch );
		$this->assertEquals( ' x^3 ', $second[1] );
	}
}