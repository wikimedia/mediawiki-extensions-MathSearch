<?php

namespace MediaWiki\Extension\MathSearch\StackExchange;

use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group MathSearch
 */
class RowTest extends MediaWikiIntegrationTestCase {

	// phpcs:disable Generic.Files.LineLength
	private const SAMPLE_ROW = <<<'XmlFragment'
<row AnswerCount="19" Body="&lt;p&gt;&lt;a href=&quot;http://mathfactor.uark.edu/&quot;&gt;mathfactor&lt;/a&gt; is one I listen to.  Does anyone else have a recommendation?&lt;/p&gt; " CommentCount="3" CreationDate="2010-07-20T19:12:14.353" Id="3" OwnerUserId="29" PostTypeId="1" Score="106" Tags="&lt;soft-question&gt;&lt;big-list&gt;&lt;online-resources&gt;" Title="List of interesting math podcasts?" ViewCount="65669"/>
XmlFragment;

	/**
	 * @covers MediaWiki\Extension\MathSearch\StackExchange\Row::getFields
	 */
	public function testGetFields() {
		$f = new Row( self::SAMPLE_ROW, 'Posts_V1.0_0.xml' );
		$fields = $f->getFields();
		$this->assertCount( 11, $fields );
		$this->assertSame( '3', $fields['CommentCount']->getContent() );
	}

	/**
	 * @covers MediaWiki\Extension\MathSearch\StackExchange\Row::getIgnoredFieldCount
	 */
	public function testGetIgnoredFieldCount() {
		$f = new Row( self::SAMPLE_ROW, 'Posts_V1.0_0.xml' );
		$this->assertSame( 0, $f->getIgnoredFieldCount() );
	}

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'math_wbs_entity_map';
	}

}
