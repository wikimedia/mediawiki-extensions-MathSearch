<?php

namespace MediaWiki\Extension\MathSearch\XQuery;

use MediaWikiUnitTestCase;

/**
 * Test the db2 access of  MathSearch.
 *
 * @group MathSearch
 * @group Database
 * @covers MediaWiki\Extension\MathSearch\XQuery\XQueryGeneratorBaseX
 * @covers MediaWiki\Extension\MathSearch\XQuery\XQueryGenerator
 * @phpcs:ignore Generic.Files.LineLength.TooLong
 */
class MathXQueryTest extends MediaWikiUnitTestCase {
	// phpcs:disable
	private string $q1 = <<<'EOT'
<?xml version="1.0"?>
<mws:query xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML" limitmin="0" answsize="30">
	<mws:expr>
		<m:ci xml:id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1">E</m:ci>
	</mws:expr>
</mws:query>
EOT;

	private string $q2 = <<<'EOT'
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

	private string $q3 = <<<'EOT'
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
	private string $qqx2 = <<<'EOT'
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
	private string $qqx2x = <<<'EOT'
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
	private string $r1 = <<<'EOT'
declare default element namespace "http://www.w3.org/1998/Math/MathML";
declare namespace functx = "http://www.functx.com";
declare function functx:path-to-node
  ( $nodes as node()* )  as xs:string* {

$nodes/string-join(ancestor-or-self::*/name(.), '/')
 } ;<result>{
let $m := .for $x in $m//*:ci
[./text() = 'E']
 where
fn:count($x/*) = 0

 return
<element><x>{$x}</x><p>{data(functx:path-to-node($x))}</p></element>}
</result>
EOT;

	private string $r2 = <<<'EOT'
declare default element namespace "http://www.w3.org/1998/Math/MathML";
declare namespace functx = "http://www.functx.com";
declare function functx:path-to-node
  ( $nodes as node()* )  as xs:string* {

$nodes/string-join(ancestor-or-self::*/name(.), '/')
 } ;<result>{
let $m := .for $x in $m//*:apply
[*[1]/name() ='csymbol' and *[1][./text() = 'superscript'] and *[2]/name() ='ci' and *[2][./text() = 'c'] and *[3]/name() ='cn' and *[3][./text() = '2']]
 where
fn:count($x/*[1]/*) = 0
 and fn:count($x/*[2]/*) = 0
 and fn:count($x/*[3]/*) = 0
 and fn:count($x/*) = 3

 return
<element><x>{$x}</x><p>{data(functx:path-to-node($x))}</p></element>}
</result>
EOT;

	private string $r3 = <<<'EOT'
declare default element namespace "http://www.w3.org/1998/Math/MathML";
declare namespace functx = "http://www.functx.com";
declare function functx:path-to-node
  ( $nodes as node()* )  as xs:string* {

$nodes/string-join(ancestor-or-self::*/name(.), '/')
 } ;<result>{
let $m := .for $x in $m//*:apply
[*[1]/name() ='sin' and *[2]/name() ='ci' and *[2][./text() = 'x']]
 where
fn:count($x/*[2]/*) = 0
 and fn:count($x/*) = 2

 return
<element><x>{$x}</x><p>{data(functx:path-to-node($x))}</p></element>}
</result>
EOT;

	private string $rqx2 = <<<'EOT'
declare default element namespace "http://www.w3.org/1998/Math/MathML";
declare namespace functx = "http://www.functx.com";
declare function functx:path-to-node
  ( $nodes as node()* )  as xs:string* {

$nodes/string-join(ancestor-or-self::*/name(.), '/')
 } ;<result>{
let $m := .for $x in $m//*:apply
[*[1]/name() ='csymbol' and *[1][./text() = 'superscript'] and *[3]/name() ='cn' and *[3][./text() = '2']]
 where
fn:count($x/*[1]/*) = 0
 and fn:count($x/*[3]/*) = 0
 and fn:count($x/*) = 3

 return
<element><x>{$x}</x><p>{data(functx:path-to-node($x))}</p></element>}
</result>
EOT;
	private string $rqx2x = <<<'EOT'
declare default element namespace "http://www.w3.org/1998/Math/MathML";
declare namespace functx = "http://www.functx.com";
declare function functx:path-to-node
  ( $nodes as node()* )  as xs:string* {

$nodes/string-join(ancestor-or-self::*/name(.), '/')
 } ;<result>{
let $m := .for $x in $m//*:apply
[*[1]/name() ='plus' and *[2]/name() ='apply' and *[2][*[1]/name() ='csymbol' and *[1][./text() = 'superscript'] and *[3]/name() ='cn' and *[3][./text() = '2']]]
 where
fn:count($x/*[2]/*[1]/*) = 0
 and fn:count($x/*[2]/*[3]/*) = 0
 and fn:count($x/*[2]/*) = 3
 and fn:count($x/*) = 3
 and $x/*[2]/*[2] = $x/*[3]
 return
<element><x>{$x}</x><p>{data(functx:path-to-node($x))}</p></element>}
</result>
EOT;
	// phpcs:enable

	/**
	 * Searches for $E$
	 */
	public function testE() {
		$xQuery = new XQueryGeneratorBaseX( $this->q1 );
		$this->assertEquals( $this->r1, $xQuery->getXQuery() );
	}

	/**
	 * Searches for $c^2$
	 */
	public function testc2() {
		$xQuery = new XQueryGeneratorBaseX( $this->q2 );
		$this->assertEquals( $this->r2, $xQuery->getXQuery() );
	}

	/**
	 * Searches for $\sin x$
	 */
	public function testsinx() {
		$xQuery = new XQueryGeneratorBaseX( $this->q3 );
		$this->assertEquals( $this->r3, $xQuery->getXQuery() );
	}

	/**
	 * Searches for $?x^2$
	 */
	public function testx2() {
		$xQuery = new XQueryGeneratorBaseX( $this->qqx2 );
		$this->assertEquals( $this->rqx2, $xQuery->getXQuery() );
	}

	/**
	 * Searches for $?x^2+?x$
	 */
	public function testx2x() {
		$xQuery = new XQueryGeneratorBaseX( $this->qqx2x );
		$this->assertEquals( $this->rqx2x, $xQuery->getXQuery() );
	}
}
