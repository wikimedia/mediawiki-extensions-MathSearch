<?php

namespace MathSearch\StackExchange;

use MediaWikiTestCase;

class RowTest extends MediaWikiTestCase {
// @codingStandardsIgnoreStart
	const SAMPLE_ROW = <<<'XmlFragment'
<row AnswerCount="19" Body="&lt;p&gt;&lt;a href=&quot;http://mathfactor.uark.edu/&quot;&gt;mathfactor&lt;/a&gt; is one I listen to.  Does anyone else have a recommendation?&lt;/p&gt; " CommentCount="3" CreationDate="2010-07-20T19:12:14.353" Id="3" OwnerUserId="29" PostTypeId="1" Score="106" Tags="&lt;soft-question&gt;&lt;big-list&gt;&lt;online-resources&gt;" Title="List of interesting math podcasts?" ViewCount="65669"/>
XmlFragment;

// @codingStandardsIgnoreEnd

	/**
	 * @covers \MathSearch\StackExchange\Row::getFields
	 */
	public function testGetFields() {
		$f = new Row( self::SAMPLE_ROW, 'posts.xml' );
		$fields = $f->getFields();
		$this->assertCount( 11, $fields );
		$this->assertSame( 1, $fields['OwnerUserId']->getContent() );
	}

	/**
	 * @covers \MathSearch\StackExchange\Row::getIgnoredFieldCount
	 */
	public function testGetIgnoredFieldCount() {
		$f = new Row( self::SAMPLE_ROW, 'posts.xml' );
		$this->assertSame( 0, $f->getIgnoredFieldCount() );
	}

}
