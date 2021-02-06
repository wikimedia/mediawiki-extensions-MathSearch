<?php

namespace MathSearch\StackExchange;

use MediaWikiTestCase;
use SimpleXMLElement;

/**
 * Class WikitextTest
 * @package MathSearch\StackExchange
 * @group Database
 * @group MathSearch
 */
class WikitextTest extends MediaWikiTestCase {
// @codingStandardsIgnoreStart
	const SAMPLE_ROW = <<<'XmlFragment'
<row Body="&lt;p&gt;You use a proof by contradiction. Basically, you suppose that &lt;span class=&quot;math-container&quot; id=&quot;63&quot;&gt;\sqrt{2}&lt;/span&gt; can be written as &lt;span class=&quot;math-container&quot; id=&quot;64&quot;&gt;p/q&lt;/span&gt;. Then you know that &lt;span class=&quot;math-container&quot; id=&quot;65&quot;&gt;2q^2 = p^2&lt;/span&gt;. However, both &lt;span class=&quot;math-container&quot; id=&quot;66&quot;&gt;q^2&lt;/span&gt; and &lt;span class=&quot;math-container&quot; id=&quot;67&quot;&gt;p^2&lt;/span&gt; have an even number of factors of two, so &lt;span class=&quot;math-container&quot; id=&quot;68&quot;&gt;2q^2&lt;/span&gt; has an odd number of factors of 2, which means it can't be equal to &lt;span class=&quot;math-container&quot; id=&quot;69&quot;&gt;p^2&lt;/span&gt;.&lt;/p&gt; " CommentCount="10" CreationDate="2010-07-20T19:21:52.240" Id="7" OwnerUserId="45" ParentId="5" PostTypeId="2" Score="74"/>
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
		$this->assertCount( 7, $wtGen->getFormulae() );
	}

	protected function setUp() : void {
		parent::setUp();
		$this->tablesUsed[] = 'math_wbs_entity_map';
	}
}
