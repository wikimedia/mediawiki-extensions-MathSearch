<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

use MathObject;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MathObject::hashInput
 */
class MathObjectHashInputTest extends MediaWikiIntegrationTestCase {

	public function testTheIdIsLeftOut() {
		$this->assertSame( MathObject::hashInput( 'x', [] ), MathObject::hashInput( 'x', [ 'id' => 'a' ] ) );
	}

	public function testOtherAttributesCount() {
		$this->assertNotSame( MathObject::hashInput( 'x', [] ), MathObject::hashInput( 'x', [ 'qid' => 'Q1' ] ) );
	}

	public function testAttributeOrderDoesNotMatter() {
		$this->assertSame( MathObject::hashInput( 'x', [ 'qid' => 'Q1', 'display' => 'block' ] ),
			MathObject::hashInput( 'x', [ 'display' => 'block', 'qid' => 'Q1' ] ) );
	}
}
