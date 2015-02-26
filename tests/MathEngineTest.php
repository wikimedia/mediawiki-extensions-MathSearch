<?php
/**
 * MediaWiki MathSearch extension
 *
 */

class MathEngineTest extends MediaWikiTestCase {

	public function testHash(){
		$test_tex = 'E=mc^2';
		$test_hash = '826676a6a5ad24552f0d5af1593434cc';
		$renderer = new MathLaTeXML($test_tex);
		$realHash = $renderer->getMd5();
		$this->assertEquals($realHash,$test_hash,'wrong hash');
	}

}