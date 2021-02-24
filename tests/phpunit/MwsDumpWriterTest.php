<?php
/**
 * Test the MathSearchUtils script.
 *
 * @group MathSearch
 */
class MwsDumpWriterTest extends MediaWikiTestCase {
	// TODO: update tests strategy resources etc. T249429
	private const TEST_WIKITEXT = <<<'WikiText'
<math>
E = m c
</math>

<math>
E = m c ^ 2
</math>

<math id=incorrect>
E = m c ^ 3
</math>

<math>
E = m c ^ 4
</math>
WikiText;

	// phpcs:disable Generic.Files.LineLength
	private const EXPECTED_OUTPUT = <<<'XML'
<?xml version="1.0"?>
<mws:harvest xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML">
<mws:expr url="28378#math.28378.1">
	<math xmlns="http://www.w3.org/1998/Math/MathML" id="p1.1.m1.1" class="ltx_Math" alttext="{\displaystyle E=mc}" display="inline">
  <semantics id="p1.1.m1.1a">
    <mrow id="p1.1.m1.1.5" xref="p1.1.m1.1.5.cmml">
      <mi id="p1.1.m1.1.1" xref="p1.1.m1.1.1.cmml">E</mi>
      <mo id="p1.1.m1.1.2" xref="p1.1.m1.1.2.cmml">=</mo>
      <mrow id="p1.1.m1.1.5.1" xref="p1.1.m1.1.5.1.cmml">
        <mi id="p1.1.m1.1.3" xref="p1.1.m1.1.3.cmml">m</mi>
        <mo id="p1.1.m1.1.5.1.1" xref="p1.1.m1.1.5.1.1.cmml">⁢</mo>
        <mi id="p1.1.m1.1.4" xref="p1.1.m1.1.4.cmml">c</mi>
      </mrow>
    </mrow>
    <annotation-xml encoding="MathML-Content" id="p1.1.m1.1b">
      <apply id="p1.1.m1.1.5.cmml" xref="p1.1.m1.1.5">
        <eq id="p1.1.m1.1.2.cmml" xref="p1.1.m1.1.2"/>
        <ci id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1">E</ci>
        <apply id="p1.1.m1.1.5.1.cmml" xref="p1.1.m1.1.5.1">
          <times id="p1.1.m1.1.5.1.1.cmml" xref="p1.1.m1.1.5.1.1"/>
          <ci id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3">m</ci>
          <ci id="p1.1.m1.1.4.cmml" xref="p1.1.m1.1.4">c</ci>
        </apply>
      </apply>
    </annotation-xml>
  </semantics>
</math>
</mws:expr>

<mws:expr url="28378#math.28378.2">
	<math xmlns="http://www.w3.org/1998/Math/MathML" id="p1.1.m1.1" class="ltx_Math" alttext="{\displaystyle E=mc^{2}}" display="inline">
  <semantics id="p1.1.m1.1a">
    <mrow id="p1.1.m1.1.6" xref="p1.1.m1.1.6.cmml">
      <mi id="p1.1.m1.1.1" xref="p1.1.m1.1.1.cmml">E</mi>
      <mo id="p1.1.m1.1.2" xref="p1.1.m1.1.2.cmml">=</mo>
      <mrow id="p1.1.m1.1.6.1" xref="p1.1.m1.1.6.1.cmml">
        <mi id="p1.1.m1.1.3" xref="p1.1.m1.1.3.cmml">m</mi>
        <mo id="p1.1.m1.1.6.1.1" xref="p1.1.m1.1.6.1.1.cmml">⁢</mo>
        <msup id="p1.1.m1.1.6.1.2" xref="p1.1.m1.1.6.1.2.cmml">
          <mi id="p1.1.m1.1.4" xref="p1.1.m1.1.4.cmml">c</mi>
          <mn id="p1.1.m1.1.5.1" xref="p1.1.m1.1.5.1.cmml">2</mn>
        </msup>
      </mrow>
    </mrow>
    <annotation-xml encoding="MathML-Content" id="p1.1.m1.1b">
      <apply id="p1.1.m1.1.6.cmml" xref="p1.1.m1.1.6">
        <eq id="p1.1.m1.1.2.cmml" xref="p1.1.m1.1.2"/>
        <ci id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1">E</ci>
        <apply id="p1.1.m1.1.6.1.cmml" xref="p1.1.m1.1.6.1">
          <times id="p1.1.m1.1.6.1.1.cmml" xref="p1.1.m1.1.6.1.1"/>
          <ci id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3">m</ci>
          <apply id="p1.1.m1.1.6.1.2.cmml" xref="p1.1.m1.1.6.1.2">
            <csymbol cd="ambiguous" id="p1.1.m1.1.6.1.2.1.cmml">superscript</csymbol>
            <ci id="p1.1.m1.1.4.cmml" xref="p1.1.m1.1.4">c</ci>
            <cn type="integer" id="p1.1.m1.1.5.1.cmml" xref="p1.1.m1.1.5.1">2</cn>
          </apply>
        </apply>
      </apply>
    </annotation-xml>
  </semantics>
</math>
</mws:expr>

<mws:expr url="28378#incorrect">
	<math xmlns="http://www.w3.org/1998/Math/MathML" id="p1.1.m1.1" class="ltx_Math" alttext="{\displaystyle E=mc^{3}}" display="inline">
  <semantics id="p1.1.m1.1a">
    <mrow id="p1.1.m1.1.6" xref="p1.1.m1.1.6.cmml">
      <mi id="p1.1.m1.1.1" xref="p1.1.m1.1.1.cmml">E</mi>
      <mo id="p1.1.m1.1.2" xref="p1.1.m1.1.2.cmml">=</mo>
      <mrow id="p1.1.m1.1.6.1" xref="p1.1.m1.1.6.1.cmml">
        <mi id="p1.1.m1.1.3" xref="p1.1.m1.1.3.cmml">m</mi>
        <mo id="p1.1.m1.1.6.1.1" xref="p1.1.m1.1.6.1.1.cmml">⁢</mo>
        <msup id="p1.1.m1.1.6.1.2" xref="p1.1.m1.1.6.1.2.cmml">
          <mi id="p1.1.m1.1.4" xref="p1.1.m1.1.4.cmml">c</mi>
          <mn id="p1.1.m1.1.5.1" xref="p1.1.m1.1.5.1.cmml">3</mn>
        </msup>
      </mrow>
    </mrow>
    <annotation-xml encoding="MathML-Content" id="p1.1.m1.1b">
      <apply id="p1.1.m1.1.6.cmml" xref="p1.1.m1.1.6">
        <eq id="p1.1.m1.1.2.cmml" xref="p1.1.m1.1.2"/>
        <ci id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1">E</ci>
        <apply id="p1.1.m1.1.6.1.cmml" xref="p1.1.m1.1.6.1">
          <times id="p1.1.m1.1.6.1.1.cmml" xref="p1.1.m1.1.6.1.1"/>
          <ci id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3">m</ci>
          <apply id="p1.1.m1.1.6.1.2.cmml" xref="p1.1.m1.1.6.1.2">
            <csymbol cd="ambiguous" id="p1.1.m1.1.6.1.2.1.cmml">superscript</csymbol>
            <ci id="p1.1.m1.1.4.cmml" xref="p1.1.m1.1.4">c</ci>
            <cn type="integer" id="p1.1.m1.1.5.1.cmml" xref="p1.1.m1.1.5.1">3</cn>
          </apply>
        </apply>
      </apply>
    </annotation-xml>
  </semantics>
</math>
</mws:expr>

<mws:expr url="28378#math.28378.4">
	<math xmlns="http://www.w3.org/1998/Math/MathML" id="p1.1.m1.1" class="ltx_Math" alttext="{\displaystyle E=mc^{4}}" display="inline">
  <semantics id="p1.1.m1.1a">
    <mrow id="p1.1.m1.1.6" xref="p1.1.m1.1.6.cmml">
      <mi id="p1.1.m1.1.1" xref="p1.1.m1.1.1.cmml">E</mi>
      <mo id="p1.1.m1.1.2" xref="p1.1.m1.1.2.cmml">=</mo>
      <mrow id="p1.1.m1.1.6.1" xref="p1.1.m1.1.6.1.cmml">
        <mi id="p1.1.m1.1.3" xref="p1.1.m1.1.3.cmml">m</mi>
        <mo id="p1.1.m1.1.6.1.1" xref="p1.1.m1.1.6.1.1.cmml">⁢</mo>
        <msup id="p1.1.m1.1.6.1.2" xref="p1.1.m1.1.6.1.2.cmml">
          <mi id="p1.1.m1.1.4" xref="p1.1.m1.1.4.cmml">c</mi>
          <mn id="p1.1.m1.1.5.1" xref="p1.1.m1.1.5.1.cmml">4</mn>
        </msup>
      </mrow>
    </mrow>
    <annotation-xml encoding="MathML-Content" id="p1.1.m1.1b">
      <apply id="p1.1.m1.1.6.cmml" xref="p1.1.m1.1.6">
        <eq id="p1.1.m1.1.2.cmml" xref="p1.1.m1.1.2"/>
        <ci id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1">E</ci>
        <apply id="p1.1.m1.1.6.1.cmml" xref="p1.1.m1.1.6.1">
          <times id="p1.1.m1.1.6.1.1.cmml" xref="p1.1.m1.1.6.1.1"/>
          <ci id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3">m</ci>
          <apply id="p1.1.m1.1.6.1.2.cmml" xref="p1.1.m1.1.6.1.2">
            <csymbol cd="ambiguous" id="p1.1.m1.1.6.1.2.1.cmml">superscript</csymbol>
            <ci id="p1.1.m1.1.4.cmml" xref="p1.1.m1.1.4">c</ci>
            <cn type="integer" id="p1.1.m1.1.5.1.cmml" xref="p1.1.m1.1.5.1">4</cn>
          </apply>
        </apply>
      </apply>
    </annotation-xml>
  </semantics>
</math>
</mws:expr>
</mws:harvest>
XML;

	/** @var bool */
	private static $hasRestbase;

	public static function setUpBeforeClass() : void {
		$rbi = new MathRestbaseInterface();
		self::$hasRestbase = $rbi->checkBackend( true );
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() : void {
		$this->markTestSkipped( "MwsDumpWriterTest temporary disabled" ); // T249429
		parent::setUp();
		if ( !self::$hasRestbase ) {
			$this->markTestSkipped( "Can not connect to Restbase Math interface." );
		}
	}

	/**
	 * @covers MwsDumpWriter::addFromMathIdGenerator
	 * @covers MwsDumpWriter::getOutput
	 * @throws MWException
	 */
	public function testExtract() {
		$revId = 28378;
		$dw = new MwsDumpWriter();
		$this->setMwGlobals(
			[
				'wgMathValidModes' => [ 'latexml' ],
				'wgMathDefaultLaTeXMLSetting' => [
					'format' => 'xhtml',
					'whatsin' => 'math',
					'whatsout' => 'math',
					'pmml',
					'cmml',
					'nodefaultresources',
					'preload' => [
						'LaTeX.pool',
						'article.cls',
						'amsmath.sty',
						'amsthm.sty',
						'amstext.sty',
						'amssymb.sty',
						'eucal.sty',
						'[dvipsnames]xcolor.sty',
						'url.sty',
						'hyperref.sty',
						'[ids]latexml.sty',
						'texvc'
					]
				]
			]
		);
		$gen = new MathIdGenerator( self::TEST_WIKITEXT, $revId );
		$gen->setUseCustomIds( true );
		$dw->addFromMathIdGenerator( $gen );
		$this->assertEquals( self::EXPECTED_OUTPUT, $dw->getOutput() );
	}
}
