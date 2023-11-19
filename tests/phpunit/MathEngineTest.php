<?php

use MediaWiki\Extension\Math\MathLaTeXML;

class MathEngineTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers MediaWiki\Extension\Math\MathMathML::getInputHash
	 */
	public function testHash() {
		$test_tex = 'E=mc^2';
		$test_hash = 'cd3401b0f0692f2818f217807fa9cc48';
		$renderer = new MathLaTeXML( $test_tex );
		$realHash = $renderer->getInputHash();
		$this->assertEquals( $realHash, $test_hash, 'wrong hash' );
	}

}
