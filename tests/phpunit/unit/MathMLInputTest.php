<?php

namespace MediaWiki\Extension\MathSearch\Tests\Unit;

use MediaWiki\Extension\MathSearch\MathMLInput;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\MathMLInput
 */
class MathMLInputTest extends MediaWikiUnitTestCase {

	private const NS = 'xmlns="http://www.w3.org/1998/Math/MathML"';

	private function newInput(): MathMLInput {
		return new MathMLInput( dirname( __DIR__, 3 ) . '/schema/mathml-mardi.rng' );
	}

	public static function provideAccepted(): array {
		return [
			'LaTeXML without namespace' => [
				'<math alttext="x"><mi>x</mi></math>',
				'<math ' . self::NS . ' alttext="x"><mi>x</mi></math>',
			],
			'parallel markup' => [
				'<math><semantics><mi xref="c1">x</mi>' .
					'<annotation-xml encoding="MathML-Content"><ci id="c1">x</ci></annotation-xml>' .
					'</semantics></math>',
				'<math ' . self::NS . '><semantics><mi xref="c1">x</mi>' .
					'<annotation-xml encoding="MathML-Content"><ci id="c1">x</ci></annotation-xml>' .
					'</semantics></math>',
			],
			'HTML entity' => [
				'<math><mtext>a&nbsp;b</mtext></math>',
				'<math ' . self::NS . '><mtext>a' . "\u{00A0}" . 'b</mtext></math>',
			],
			'menclose and table spacing' => [
				'<math><menclose notation="circle"><mtable rowspacing="4pt" columnspacing="1em">' .
					'<mtr><mtd><mn>1</mn></mtd></mtr></mtable></menclose></math>',
				'<math ' . self::NS . '><menclose notation="circle"><mtable rowspacing="4pt" columnspacing="1em">' .
					'<mtr><mtd><mn>1</mn></mtd></mtr></mtable></menclose></math>',
			],
		];
	}

	/**
	 * @dataProvider provideAccepted
	 */
	public function testAccepted( string $input, string $expected ) {
		$status = $this->newInput()->check( $input );
		$this->assertStatusGood( $status );
		$this->assertSame( $expected, $status->getValue() );
	}

	public static function provideRejected(): array {
		return [
			'not XML' => [ '<math><mi>x</math>' ],
			'not math' => [ '<mi ' . self::NS . '>x</mi>' ],
			'other namespace' => [ '<math xmlns="http://www.w3.org/1999/xhtml"><mi>x</mi></math>' ],
			'unknown element' => [ '<math><mfoo>x</mfoo></math>' ],
			'event handler' => [ '<math><mi onclick="alert(1)">x</mi></math>' ],
			'foreign element in annotation' => [
				'<math><semantics><mi>x</mi><annotation-xml encoding="text/html">' .
					'<img xmlns="http://www.w3.org/1999/xhtml" src="x" onerror="alert(1)"/>' .
					'</annotation-xml></semantics></math>',
			],
			'unsafe style' => [ '<math><mi style="background:url(javascript:alert(1))">x</mi></math>' ],
			'entity definition' => [ '<!DOCTYPE math [<!ENTITY a "x">]><math><mi>&a;</mi></math>' ],
		];
	}

	/**
	 * @dataProvider provideRejected
	 */
	public function testRejected( string $input ) {
		$this->assertStatusError( 'mathsearch-mathml-invalid', $this->newInput()->check( $input ) );
	}
}
