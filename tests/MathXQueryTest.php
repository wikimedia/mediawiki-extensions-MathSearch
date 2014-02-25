<?php
/**
 * Test the db2 access of  MathSearch.
*
* @group MathSearch
* @group Database
*/
class MathXQueryTest extends MediaWikiTestCase {

	private $q1 = <<<'EOT'
<?xml version="1.0"?>
<mws:query xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML" limitmin="0" answsize="30">
	<mws:expr>
		<m:ci xml:id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1">E</m:ci>
	</mws:expr>
</mws:query>
EOT;

	private $q2 = <<<'EOT'
<?xml version="1.0"?>
<mws:query xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML" limitmin="0" answsize="30">
  <mws:expr>
    <m:apply xml:id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3">
      <m:csymbol cd="ambiguous" xml:id="p1.1.m1.1.3.1.cmml">superscript</m:csymbol>
      <m:ci xml:id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1">c</m:ci>
      <m:cn type="integer" xml:id="p1.1.m1.1.2.1.cmml" xref="p1.1.m1.1.2.1">2</m:cn>
    </m:apply>
  </mws:expr>
</mws:query>
EOT;

	private $q3 = <<<'EOT'
<?xml version="1.0"?>
<mws:query xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML" limitmin="0" answsize="30">
  <mws:expr>
    <m:apply xml:id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3">
      <m:sin xml:id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1"/>
      <m:ci xml:id="p1.1.m1.1.2.cmml" xref="p1.1.m1.1.2">x</m:ci>
    </m:apply>
  </mws:expr>
</mws:query>
EOT;
	private $qqx2 = <<<'EOT'
<?xml version="1.0"?>
<mws:query xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML" limitmin="0" answsize="30">
  <mws:expr>
    <m:apply xml:id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3">
      <m:csymbol cd="ambiguous" xml:id="p1.1.m1.1.3.1.cmml">superscript</m:csymbol>
      <mws:qvar>x</mws:qvar>
      <m:cn type="integer" xml:id="p1.1.m1.1.2.1.cmml" xref="p1.1.m1.1.2.1">2</m:cn>
    </m:apply>
  </mws:expr>
</mws:query>
EOT;
	private $qqx2x = <<<'EOT'
<?xml version="1.0"?>
<mws:query xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML" limitmin="0" answsize="30">
  <mws:expr>
    <m:apply xml:id="p1.1.m1.1.5.cmml" xref="p1.1.m1.1.5">
      <m:plus xml:id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3"/>
      <m:apply xml:id="p1.1.m1.1.5.1.cmml" xref="p1.1.m1.1.5.1">
        <m:csymbol cd="ambiguous" xml:id="p1.1.m1.1.5.1.1.cmml">superscript</m:csymbol>
        <mws:qvar>x</mws:qvar>
        <m:cn type="integer" xml:id="p1.1.m1.1.2.1.cmml" xref="p1.1.m1.1.2.1">2</m:cn>
      </m:apply>
      <mws:qvar>x</mws:qvar>
    </m:apply>
  </mws:expr>
</mws:query>
EOT;
	private $r1 = <<<'EOT'
xquery declare default element namespace "http://www.w3.org/1998/Math/MathML";
 for $m in db2-fn:xmlcolumn("math.math_mathml") return
for $x in $m//*:ci
[./text() = 'E']
 where
fn:count($x/*) = 0

 return
data($m/*[1]/@alttext)
EOT;

	private $r2 = <<<'EOT'
xquery declare default element namespace "http://www.w3.org/1998/Math/MathML";
 for $m in db2-fn:xmlcolumn("math.math_mathml") return
for $x in $m//*:apply
[*[1]/name() ='csymbol' and *[1][./text() = 'superscript'] and *[2]/name() ='ci' and *[2][./text() = 'c'] and *[3]/name() ='cn' and *[3][./text() = '2']]
 where
fn:count($x/*[1]/*) = 0
 and fn:count($x/*[2]/*) = 0
 and fn:count($x/*[3]/*) = 0
 and fn:count($x/*) = 3

 return
data($m/*[1]/@alttext)
EOT;

	private $r3 = <<<'EOT'
xquery declare default element namespace "http://www.w3.org/1998/Math/MathML";
 for $m in db2-fn:xmlcolumn("math.math_mathml") return
for $x in $m//*:apply
[*[1]/name() ='sin' and *[2]/name() ='ci' and *[2][./text() = 'x']]
 where
fn:count($x/*[2]/*) = 0
 and fn:count($x/*) = 2

 return
data($m/*[1]/@alttext)
EOT;


	private $rqx2 = <<<'EOT'
xquery declare default element namespace "http://www.w3.org/1998/Math/MathML";
 for $m in db2-fn:xmlcolumn("math.math_mathml") return
for $x in $m//*:apply
[*[1]/name() ='csymbol' and *[1][./text() = 'superscript'] and *[3]/name() ='cn' and *[3][./text() = '2']]
 where
fn:count($x/*[1]/*) = 0
 and fn:count($x/*[3]/*) = 0
 and fn:count($x/*) = 3

 return
data($m/*[1]/@alttext)
EOT;
	private $rqx2x = <<<'EOT'
xquery declare default element namespace "http://www.w3.org/1998/Math/MathML";
 for $m in db2-fn:xmlcolumn("math.math_mathml") return
for $x in $m//*:apply
[*[1]/name() ='plus' and *[2]/name() ='apply' and *[2][*[1]/name() ='csymbol' and *[1][./text() = 'superscript'] and *[3]/name() ='cn' and *[3][./text() = '2']]]
 where
fn:count($x/*[2]/*[1]/*) = 0
 and fn:count($x/*[2]/*[3]/*) = 0
 and fn:count($x/*[2]/*) = 3
 and fn:count($x/*) = 3
 and $x/*[2]/*[2] = $x/*[3]
 return
data($m/*[1]/@alttext)
EOT;
	/**
	 * Searches for $E$
	 */
	public function testE(){
		$xQuery = new XQueryGeneratorDB2($this->q1);
		$this->assertEquals($this->r1,$xQuery->getXQuery());
	}

	/*
	 * Searches for $c^2$
	 */
	public function testc2(){
		$xQuery = new XQueryGeneratorDB2($this->q2);
		$this->assertEquals($this->r2,$xQuery->getXQuery());
	}

	/*
	 * Searches for $\sin x$
	 */
	public function testsinx(){
		$xQuery = new XQueryGeneratorDB2($this->q3);
		$this->assertEquals($this->r3,$xQuery->getXQuery());
	}

	/*
	 * Searches for $?x^2$
	 */
	public function testx2(){
		$xQuery = new XQueryGeneratorDB2($this->qqx2);
		$this->assertEquals($this->rqx2,$xQuery->getXQuery());
	}
	/*
	 * Searches for $?x^2+?x$
	 */
	public function testx2x(){
		$xQuery = new XQueryGeneratorDB2($this->qqx2x);
		$this->assertEquals($this->rqx2x,$xQuery->getXQuery());
	}
	/*public function testBasicSQL(){
		global $wgMathSearchDB2ConnStr;
		$conn = db2_connect($wgMathSearchDB2ConnStr, '', '');
		$stmt = db2_exec($conn,'select "math_tex" from "math"');
		while($row = db2_fetch_object($stmt)){
			printf("$row->math_tex\n");
		}
	}







	private function testBasic() {
            $cmmlQueryString = <<<'XML'
<?xml version="1.0"?>
<mws:query xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML" limitmin="0" answsize="30">
  <mws:expr>
    <m:apply xml:id="p1.1.m1.1.6.cmml" xref="p1.1.m1.1.6">
      <m:eq xml:id="p1.1.m1.1.2.cmml" xref="p1.1.m1.1.2"/>
      <m:ci xml:id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1">E</m:ci>
      <m:apply xml:id="p1.1.m1.1.6.1.cmml" xref="p1.1.m1.1.6.1">
        <m:times xml:id="p1.1.m1.1.6.1.1.cmml" xref="p1.1.m1.1.6.1.1"/>
        <m:ci xml:id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3">m</m:ci>
        <m:apply xml:id="p1.1.m1.1.6.1.2.cmml" xref="p1.1.m1.1.6.1.2">
          <m:csymbol cd="ambiguous" xml:id="p1.1.m1.1.6.1.2.1.cmml">superscript</m:csymbol>
          <m:ci xml:id="p1.1.m1.1.4.cmml" xref="p1.1.m1.1.4">c</m:ci>
          <m:cn type="integer" xml:id="p1.1.m1.1.5.1.cmml" xref="p1.1.m1.1.5.1">2</m:cn>
        </m:apply>
      </m:apply>
    </m:apply>
  </mws:expr>
</mws:query>
XML;
            //TODO: fix test
            $xqueryTest = new XQueryGeneratorDB2($cmmlQueryString);
            echo $xqueryTest->getXQuery();
	}*/
}