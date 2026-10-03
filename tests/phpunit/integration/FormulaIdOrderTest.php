<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

require_once dirname( __DIR__, 3 ) . '/maintenance/UpdateMath.php';

use MediaWiki\Parser\ParserOptions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use UpdateMath;

/**
 * A link to a formula id from mathindex has to reach the same formula on the page.
 *
 * @covers \MathSearchHooks
 * @covers \MathIdGenerator
 * @group Database
 */
class FormulaIdOrderTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return UpdateMath::class;
	}

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native' ],
			// Keeps BaseX out of the test
			'MathSearchMode' => 'latexml',
		] );
	}

	/**
	 * @return string[] the formula ids in the order of the page
	 */
	private function idsOnPage( string $title ): array {
		$page = $this->getExistingTestPage( $title );
		$options = ParserOptions::newFromAnon();
		// Only native puts a math element on the page, which can carry the id
		$options->setOption( 'math', 'native' );
		$html = $page->getParserOutput( $options, null, true )->getContentHolderText();
		preg_match_all( '/<math id="([^"]+)"/', $html, $matches );
		return $matches[1];
	}

	/**
	 * @return array<string,string> anchor => input hash, as UpdateMath indexed them
	 */
	private function idsInIndex( int $revId ): array {
		$rows = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'mathindex_anchor', 'mathindex_inputhash' ] )
			->from( 'mathindex' )
			->where( [ 'mathindex_revision_id' => $revId ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$ids = [];
		foreach ( $rows as $row ) {
			$ids[$row->mathindex_anchor] = $row->mathindex_inputhash;
		}
		return $ids;
	}

	/**
	 * @return string[] the formula ids on the page
	 */
	private function assertPageMatchesIndex( string $wikitext ): array {
		$revId = $this->editPage( 'Formula ids', $wikitext )->getNewRevision()->getId();
		$this->truncateTable( 'mathindex' );
		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();
		$index = $this->idsInIndex( $revId );

		$this->truncateTable( 'mathindex' );
		$onPage = $this->idsOnPage( 'Formula ids' );

		$this->assertSame( array_keys( $index ), array_keys( $this->idsInIndex( $revId ) ),
			'The page view indexes the same ids as UpdateMath' );
		$this->assertSame( $index, $this->idsInIndex( $revId ),
			'Each id refers to the same formula in UpdateMath and in the page view' );
		// Formulae in references are rendered after the text, so only the set of ids is the same
		$this->assertEqualsCanonicalizing( array_keys( $index ), $onPage );
		return $onPage;
	}

	public function testRepeatedFormula() {
		$this->assertPageMatchesIndex( '<math>x</math> <math>y</math> <math>x</math>' );
		$this->expectOutputRegex( '/./' );
	}

	public function testRepeatedFormulaInReference() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Cite' ) ) {
			$this->markTestSkipped( 'Needs Cite' );
		}
		$this->assertPageMatchesIndex(
			// The reference is rendered after the text, unlike its position in the source
			"A<ref><math>x</math></ref> <math>y</math> <math>x</math>\n<references/>" );
		$this->expectOutputRegex( '/./' );
	}

	public function testChem() {
		$ids = $this->assertPageMatchesIndex( '<math>x</math> <chem>H2O</chem> <ce>H2O</ce>' );
		$this->assertCount( 3, $ids );
		$this->expectOutputRegex( '/./' );
	}

	public function testIdsAreStableWhenParsedTwice() {
		$this->editPage( 'Formula ids', '<math>x</math> <math>y</math> <math>x</math>' );

		$this->assertSame( $this->idsOnPage( 'Formula ids' ), $this->idsOnPage( 'Formula ids' ) );
	}

	public function testMathInNowikiIsNotCounted() {
		$revId = $this->editPage( 'Formula ids', '<nowiki><math>y</math></nowiki> <math>x</math>' )
			->getNewRevision()->getId();

		$this->assertSame( [ "math.$revId.0" ], $this->idsOnPage( 'Formula ids' ) );
	}
}
