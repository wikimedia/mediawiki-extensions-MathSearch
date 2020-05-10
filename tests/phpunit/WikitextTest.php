<?php

namespace MathSearch\StackExchange;

use MediaWikiTestCase;
use SimpleXMLElement;

class WikitextTest extends MediaWikiTestCase {
// @codingStandardsIgnoreStart
	const SAMPLE_ROW = <<<'XmlFragment'
<row Body="&lt;p&gt;You use a proof by contradiction. Basically, you suppose that &lt;span class=&quot;math-container&quot; id=&quot;63&quot;&gt;\sqrt{2}&lt;/span&gt; can be written as &lt;span class=&quot;math-container&quot; id=&quot;64&quot;&gt;p/q&lt;/span&gt;. Then you know that &lt;span class=&quot;math-container&quot; id=&quot;65&quot;&gt;2q^2 = p^2&lt;/span&gt;. However, both &lt;span class=&quot;math-container&quot; id=&quot;66&quot;&gt;q^2&lt;/span&gt; and &lt;span class=&quot;math-container&quot; id=&quot;67&quot;&gt;p^2&lt;/span&gt; have an even number of factors of two, so &lt;span class=&quot;math-container&quot; id=&quot;68&quot;&gt;2q^2&lt;/span&gt; has an odd number of factors of 2, which means it can't be equal to &lt;span class=&quot;math-container&quot; id=&quot;69&quot;&gt;p^2&lt;/span&gt;.&lt;/p&gt; " CommentCount="10" CreationDate="2010-07-20T19:21:52.240" Id="7" OwnerUserId="45" ParentId="5" PostTypeId="2" Score="74"/>
XmlFragment;
	const SAMPLE2 =<<<'XmlFragment'
<row Body="&lt;&lt;span class=&quot;math-container&quot; id=&quot;13734904&quot;&gt;p&lt;/span&gt;&gt;&lt;em&gt;Hint&lt;/em&gt;&lt;/p&gt;  &lt;p&gt;You have to corrcetlly formalize the different statements :&lt;/p&gt;  &lt;block&lt;span class=&quot;math-container&quot; id=&quot;13734905&quot;&gt;q&lt;/span&gt;uote&gt;   &lt;p&gt;$p$ is &quot;I go to lib&lt;span class=&quot;math-container&quot; id=&quot;13734906&quot;&gt;r&lt;/span&gt;ary&quot;&lt;/p&gt;      &lt;p&gt;$q$ i&lt;span class=&quot;math-container&quot; id=&quot;13734907&quot;&gt;s&lt;/span&gt; &quot;I wai&lt;span class=&quot;math-container&quot; id=&quot;13734908&quot;&gt;t&lt;/span&gt; for my mom&quot;&lt;/p&gt;      &lt;p&gt;$r$ is &quot;I go to the party&quot;&lt;/p&gt;      &lt;p&gt;$s$ is &quot;I meet my friends&quot; &lt;/p&gt;      &lt;p&gt;$t$ is &quot;I finish my homework&quot;.&lt;/p&gt; &lt;/blockquote&gt;  &lt;p&gt;Now we have to correctly express the &lt;em&gt;premises&lt;/em&gt; of the argument :&lt;/p&gt;  &lt;p&gt;1) &lt;span class=&quot;math-container&quot; id=&quot;13734909&quot;&gt;p \lor (q \to r)&lt;/span&gt;&lt;/p&gt;  &lt;p&gt;2) &lt;span class=&quot;math-container&quot; id=&quot;13734910&quot;&gt;s \to r&lt;/span&gt;&lt;/p&gt;  &lt;p&gt;3) &lt;span class=&quot;math-container&quot; id=&quot;13734911&quot;&gt;p \to t&lt;/span&gt;&lt;/p&gt;  &lt;p&gt;4) &lt;span class=&quot;math-container&quot; id=&quot;13734912&quot;&gt;\lnot t&lt;/span&gt;.&lt;/p&gt;  &lt;p&gt;Finally, the sought &lt;em&gt;conclusion&lt;/em&gt; :&lt;/p&gt;  &lt;p&gt;5) &lt;span class=&quot;math-container&quot; id=&quot;13734913&quot;&gt;q \to s&lt;/span&gt;.&lt;/p&gt; " CommentCount="0" CreationDate="2015-10-17T17:42:09.153" Id="1484701" OwnerUserId="108274" ParentId="1484659" PostTypeId="2" Score="0"/>
XmlFragment;


// @codingStandardsIgnoreEnd

	/**
	 * @covers \MathSearch\StackExchange\WikitextGenerator::toWikitext
	 */
	public function testGetFields() {
		$row = new SimpleXMLElement( self::SAMPLE_ROW );
		$wtGen = new WikitextGenerator();
		$wikiText = $wtGen->toWikitext( (string)$row['Body'] );
		$this->assertStringContainsString( '<math id=\'69\'', $wikiText );
		$this->assertEquals( 7, count( $wtGen->getFormulae() ) );
	}

	/**
	 * @covers \MathSearch\StackExchange\WikitextGenerator::toWikitext
	 */
	public function test2GetFields() {
		$row = new SimpleXMLElement( self::SAMPLE2 );
		$wtGen = new WikitextGenerator();
		$wikiText = $wtGen->toWikitext( (string)$row['Body'] );
		$this->assertStringContainsString( '<math id=\'13734904\'', $wikiText );
		$this->assertEquals( 9, count( $wtGen->getFormulae() ) );
	}
}
