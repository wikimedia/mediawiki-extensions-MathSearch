<?php

namespace MediaWiki\Extension\MathSearch\Tests\Rest;

use MediaWiki\Extension\MathSearch\Rest\SvgRenderer;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\SvgRenderer
 */
class SvgRendererTest extends MediaWikiUnitTestCase {

	public function testReadsTheSvgFromTheKarmaLog() {
		$log = "INFO [karma-server]: started\n"
			. "LOG: 'MATHSEARCH_SVG:{\"key\":\"formula\",\"svg\":\"<svg><path d=\\\"M1 2\\\"/></svg>\"}'\n"
			. "LOG: 'MATHSEARCH_SVG:{\"key\":\"failed\",\"svg\":null}'\n"
			. "LOG: 'MATHSEARCH_SVG:not json'\n"
			. "1 test completed\n";
		$this->assertSame(
			[ 'formula' => '<svg><path d="M1 2"/></svg>' ],
			SvgRenderer::parseLog( $log )
		);
	}
}
