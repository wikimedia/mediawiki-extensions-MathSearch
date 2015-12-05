<?php

/**
 * MediaWiki MathSearch extension
 *
 * (c)2015 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @group MathSearch
 * Class MathSearchHooksTest
 */
class WikidataDriverTest extends MediaWikiTestCase {

	public function testSuccess() {
		$wd = new WikidataDriver();
		$this->assertContains( 'wikidata', $wd->getBackendUrl() );
		$this->assertTrue( $wd->search( 'magnet' ) );
		$res = $wd->getResults();
		$this->assertArrayHasKey( 'Q11421', $res );
		$this->assertContains( 'magnetic field', $res['Q11421'] );
		$res = $wd->getResults( false, false );
		$this->assertEquals( 'magnet', $res['Q11421'] );
	}
}