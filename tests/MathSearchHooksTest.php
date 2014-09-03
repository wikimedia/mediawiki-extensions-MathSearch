<?php

/**
 * MediaWiki MathSearch extension
 *
 * (c)2014 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @group MathSearch
 * Class MathSearchHooksTest
 */
class MathSearchHooksTest extends MediaWikiTestCase {

	private $mathMLSample = <<<EOT
<math xref="p1.1.m1.1.cmml" xml:id="p1.1.m1.1" display="inline" alttext="{\displaystyle\min\left(1;\exp\left(-\beta\cdot\Delta E\right)\right)}" class="ltx_Math" id="p1.1.m1.1" xmlns="http://www.w3.org/1998/Math/MathML">
  <semantics xref="p1.1.m1.1.cmml" xml:id="p1.1.m1.1a">
    <mrow xref="p1.1.m1.1.14.cmml" xml:id="p1.1.m1.1.14">
      <mo xref="p1.1.m1.1.1.cmml" xml:id="p1.1.m1.1.1" movablelimits="false" title="not set">min</mo>
      <mo xref="p1.1.m1.1.14.cmml" xml:id="p1.1.m1.1.14a" title="not set">&ApplyFunction;</mo>
      <mrow xref="p1.1.m1.1.14.1.cmml" xml:id="p1.1.m1.1.14.1">
        <mo xref="p1.1.m1.1.14.1.cmml" xml:id="p1.1.m1.1.14.1a" title="not set">(</mo>
        <mrow xref="p1.1.m1.1.14.1.cmml" xml:id="p1.1.m1.1.14.1b">
          <mn xref="p1.1.m1.1.3.cmml" xml:id="p1.1.m1.1.3">1</mn>
          <mo xref="p1.1.m1.1.14.1.cmml" xml:id="p1.1.m1.1.14.1c" title="not set">;</mo>
          <mrow xref="p1.1.m1.1.14.1.2.cmml" xml:id="p1.1.m1.1.14.1.2">
            <mi xref="p1.1.m1.1.5.cmml" xml:id="p1.1.m1.1.5" title="not set">exp</mi>
            <mo xref="p1.1.m1.1.14.1.2.cmml" xml:id="p1.1.m1.1.14.1.2a" title="not set">&ApplyFunction;</mo>
            <mrow xref="p1.1.m1.1.14.1.2.cmml" xml:id="p1.1.m1.1.14.1.2b">
              <mo xref="p1.1.m1.1.14.1.2.cmml" xml:id="p1.1.m1.1.14.1.2c" title="not set">(</mo>
              <mrow xref="p1.1.m1.1.14.1.2.1.cmml" xml:id="p1.1.m1.1.14.1.2.1">

                <mo xref="p1.1.m1.1.7.cmml" xml:id="p1.1.m1.1.7" title="not set">-</mo>

                <mrow xref="p1.1.m1.1.14.1.2.1.1.cmml" xml:id="p1.1.m1.1.14.1.2.1.1">
                  <mrow xref="p1.1.m1.1.14.1.2.1.1.2.cmml" xml:id="p1.1.m1.1.14.1.2.1.1.2">
                    <mi xref="p1.1.m1.1.8.cmml" xml:id="p1.1.m1.1.8" title="not set">&beta;</mi>
                    <mo xref="p1.1.m1.1.9.cmml" xml:id="p1.1.m1.1.9" title="not set">&sdot;</mo>
                    <mi xref="p1.1.m1.1.10.cmml" xml:id="p1.1.m1.1.10" mathvariant="normal" title="not set">&Delta;</mi>
                  </mrow>
                  <mo xref="p1.1.m1.1.14.1.2.1.1.1.cmml" xml:id="p1.1.m1.1.14.1.2.1.1.1" title="not set">&InvisibleTimes;</mo>
                  <mi xref="p1.1.m1.1.11.cmml" xml:id="p1.1.m1.1.11" title="not set">E</mi>
                </mrow>
              </mrow>
              <mo xref="p1.1.m1.1.14.1.2.cmml" xml:id="p1.1.m1.1.14.1.2d" title="not set">)</mo>
            </mrow>
          </mrow>
        </mrow>
        <mo xref="p1.1.m1.1.14.1.cmml" xml:id="p1.1.m1.1.14.1d" title="not set">)</mo>
      </mrow>
    </mrow>
    <annotation-xml xref="p1.1.m1.1" xml:id="p1.1.m1.1.cmml" encoding="MathML-Content">
      <apply xref="p1.1.m1.1.14" xml:id="p1.1.m1.1.14.cmml">
        <min xref="p1.1.m1.1.1" xml:id="p1.1.m1.1.1.cmml"/>
        <apply xref="p1.1.m1.1.14.1" xml:id="p1.1.m1.1.14.1.cmml">
          <list xml:id="p1.1.m1.1.14.1.1.cmml"/>
          <cn xref="p1.1.m1.1.3" xml:id="p1.1.m1.1.3.cmml" type="integer">1</cn>
          <apply xref="p1.1.m1.1.14.1.2" xml:id="p1.1.m1.1.14.1.2.cmml">
            <exp xref="p1.1.m1.1.5" xml:id="p1.1.m1.1.5.cmml"/>
            <apply xref="p1.1.m1.1.14.1.2.1" xml:id="p1.1.m1.1.14.1.2.1.cmml">
              <minus xref="p1.1.m1.1.7" xml:id="p1.1.m1.1.7.cmml"/>
              <apply xref="p1.1.m1.1.14.1.2.1.1" xml:id="p1.1.m1.1.14.1.2.1.1.cmml">
                <times xref="p1.1.m1.1.14.1.2.1.1.1" xml:id="p1.1.m1.1.14.1.2.1.1.1.cmml"/>
                <apply xref="p1.1.m1.1.14.1.2.1.1.2" xml:id="p1.1.m1.1.14.1.2.1.1.2.cmml">
                  <ci xref="p1.1.m1.1.9" xml:id="p1.1.m1.1.9.cmml">normal-&sdot;</ci>
                  <ci xref="p1.1.m1.1.8" xml:id="p1.1.m1.1.8.cmml">&beta;</ci>
                  <ci xref="p1.1.m1.1.10" xml:id="p1.1.m1.1.10.cmml">normal-&Delta;</ci>
                </apply>
                <ci xref="p1.1.m1.1.11" xml:id="p1.1.m1.1.11.cmml">E</ci>
              </apply>
            </apply>
          </apply>
        </apply>
      </apply>
    </annotation-xml>
    <annotation xref="p1.1.m1.1.cmml" xml:id="p1.1.m1.1b" encoding="application/x-tex">{\displaystyle\min\left(1;\exp\left(-\beta\cdot\Delta E\right)\right)}</annotation>
  </semantics>
</math>
EOT;

	/**
	 * Tests if ID's for math elements are replaced correctly
	 * //TODO: Update test
	 * @group ignore
	 */
	public function testNTCIRHook() {
		//use Page-ID = 0 to avoid that the test tries to save something to the DB
		$sample = $this->mathMLSample;
		$this->assertTrue( MathSearchHooks::onMathFormulaRenderedNoLink( null, $sample, 0, 6 ), 'Hook did not return true' );
		$this->assertContains( "math.0.6.14.1.cmml", $sample, "expected replaced id not found" );
	}
}