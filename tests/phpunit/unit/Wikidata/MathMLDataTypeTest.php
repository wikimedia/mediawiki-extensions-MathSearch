<?php

namespace MediaWiki\Extension\MathSearch\Tests\Unit\Wikidata;

use DataValues\StringValue;
use MediaWiki\Extension\MathSearch\MathMLInput;
use MediaWiki\Extension\MathSearch\Wikidata\MathML\MathMLFormatter;
use MediaWiki\Extension\MathSearch\Wikidata\MathML\MathMLValidator;
use MediaWikiUnitTestCase;
use Wikibase\Lib\Formatters\SnakFormatter;

/**
 * @covers \MediaWiki\Extension\MathSearch\Wikidata\MathML\MathMLFormatter
 * @covers \MediaWiki\Extension\MathSearch\Wikidata\MathML\MathMLValidator
 */
class MathMLDataTypeTest extends MediaWikiUnitTestCase {
	protected function setUp(): void {
		parent::setUp();
		// Only integration tests can check whether an extension is loaded.
		if ( !interface_exists( SnakFormatter::class ) ) {
			$this->markTestSkipped( 'Wikibase is required for this test' );
		}
	}

	public function testWikitextUsesTheMathMLTag() {
		$formatter = new MathMLFormatter( SnakFormatter::FORMAT_WIKI );
		$this->assertSame( '<mathml><math><mi>x</mi></math></mathml>',
			$formatter->format( new StringValue( '<math><mi>x</mi></math>' ) ) );
	}

	private function newValidator(): MathMLValidator {
		return new MathMLValidator( new MathMLInput( dirname( __DIR__, 4 ) . '/schema/mathml-mardi.rng' ) );
	}

	public function testValidatorAcceptsMathML() {
		$this->assertTrue( $this->newValidator()
			->validate( new StringValue( '<math><mi>x</mi></math>' ) )->isValid() );
	}

	public function testValidatorRejectsEventHandlers() {
		$result = $this->newValidator()
			->validate( new StringValue( '<math><mi onclick="alert(1)">x</mi></math>' ) );
		$this->assertFalse( $result->isValid() );
		$this->assertSame( 'malformed-value', $result->getErrors()[0]->getCode() );
	}
}
