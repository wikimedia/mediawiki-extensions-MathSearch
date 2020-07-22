<?php
/**
 * Test the ContentMathML RDF formatter
 *
 * @author Moritz Schubotz (physikerwelt)
 */

use DataValues\StringValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikimedia\Purtle\NTriplesRdfWriter;

/**
 * @group ContentMath
 * @covers ContentMathMLRdfBuilder
 */
class ContentMathMLRdfBuilderTest extends MediaWikiTestCase {
	const ACME_PREFIX_URL = 'http://acme/';
	const ACME_REF = 'testing';

	protected function setUp() : void {
		parent::setUp();
		$this->setMwGlobals( 'wgMathDisableTexFilter', 'always' );
	}

	/**
	 * @param string $test
	 * @return string
	 */
	private function makeCase( $test ) {
		$builder = new ContentMathMLRdfBuilder();
		$writer = new NTriplesRdfWriter();
		$writer->prefix( 'www', "http://www/" );
		$writer->prefix( 'acme', self::ACME_PREFIX_URL );

		$writer->start();
		$writer->about( 'www', 'Q1' );

		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( $test ) );
		$builder->addValue( $writer, 'acme', self::ACME_REF, 'DUMMY', '', $snak );

		return trim( $writer->drain() );
	}

	public function testValidInput() {
		$triples = $this->makeCase( 'a^2' );
		$this->assertStringContainsString(
			self::ACME_PREFIX_URL . self::ACME_REF . '> "<math',
			$triples
		);
		$this->assertStringContainsString( '>a</mi>\n', $triples );
		$this->assertStringContainsString( '>2</mn>\n', $triples );
		$this->assertStringContainsString( 'a^{2}', $triples );
		$this->assertStringContainsString( '^^<http://www.w3.org/1998/Math/MathML> .', $triples );
	}

	public function testInvalidInput() {
		$triples = $this->makeCase( '\notExists' );
		$this->assertStringContainsString( '<math', $triples );
		$this->assertStringContainsString( 'undefined undefined', $triples );
		$this->assertStringContainsString( 'notExists', $triples );
		$this->assertStringContainsString( '^^<http://www.w3.org/1998/Math/MathML> .', $triples );
	}
}
