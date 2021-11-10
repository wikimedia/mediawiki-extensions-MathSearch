<?php

/**
 * MediaWiki MathSearch extension
 *
 * (c)2015 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @group MathSearch
 */
class WikidataDriverTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers WikidataDriver::<public>
	 */
	public function testSuccess() {
		$this->markTestSkipped( 'All HTTP requests are banned in tests. See T265628.' );
		$wd = new WikidataDriver();
		$this->assertStringContainsString( 'wikidata', $wd->getBackendUrl() );
		$this->assertTrue( $wd->search( 'magnet' ) );
		$res = $wd->getResults();
		$this->assertArrayHasKey( 'Q11421', $res );
		$this->assertStringContainsString( 'magnetic field', $res['Q11421'] );
		$res = $wd->getResults( false, false );
		$this->assertEquals( 'magnet', $res['Q11421'] );
	}
}
