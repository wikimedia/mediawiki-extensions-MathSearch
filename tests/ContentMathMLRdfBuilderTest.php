<?php
/**
 * Test the ContentMathML RDF formatter
 *
 * @group ContentMath
 * @covers ContentMathMLRdfBuilder
 * @author Moritz Schubotz (physikerwelt)
 */

use DataValues\StringValue;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikimedia\Purtle\NTriplesRdfWriter;

class ContentMathMLRdfBuilderTest extends MediaWikiTestCase {
	const ACME_PREFIX_URL = 'http://acme/';
	const ACME_REF = 'testing';

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( 'wgMathDisableTexFilter', 'always' );
	}

	/**
	 *
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
		$builder->addValue( $writer, 'acme', self::ACME_REF, 'DUMMY', $snak );

		return trim( $writer->drain() );
	}

	public function testValidInput() {
		$triples = $this->makeCase( 'a^2' );
		$this->assertContains( self::ACME_PREFIX_URL . self::ACME_REF . '> "<math', $triples );
		$this->assertContains( '>a</mi>\n', $triples );
		$this->assertContains( '>2</mn>\n', $triples );
		$this->assertContains( 'a^{2}', $triples );
		$this->assertContains( '^^<http://www.w3.org/1998/Math/MathML> .', $triples );
	}

	public function testInvalidInput() {
		$triples = $this->makeCase( '\notExists' );
		$this->assertContains( '<math', $triples );
		$this->assertContains( 'undefined undefined', $triples );
		$this->assertContains( 'notExists', $triples );
		$this->assertContains( '^^<http://www.w3.org/1998/Math/MathML> .', $triples );
	}
}
