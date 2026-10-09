<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

require_once dirname( __DIR__, 3 ) . '/maintenance/UpdateMath.php';

use MediaWiki\Content\TextContent;
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
		// The anchors are added for the legacy parser only
		$options->setUseParsoid( false );
		// Only native puts a math element on the page, which can carry the id
		$options->setOption( 'math', 'native' );
		$html = $page->getParserOutput( $options, null, true )->getContentHolderText();
		preg_match_all( '/<math\b[^>]*\sid="([^"]+)"/', $html, $matches );
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
		$saved = $this->idsInIndex( $revId );

		$this->truncateTable( 'mathindex' );
		$onPage = $this->idsOnPage( 'Formula ids' );
		$this->assertSame( [], $this->idsInIndex( $revId ), 'A page view writes no index' );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();
		$index = $this->idsInIndex( $revId );

		$this->assertSame( $index, $saved, 'Saving indexes the same anchors and hashes as UpdateMath' );
		// Formulae in references are placed after the text, so only the set of ids is the same
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

	public function testAttributeInReference() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Cite' ) ) {
			$this->markTestSkipped( 'Needs Cite' );
		}
		// The page view renders the plain x first, which must not take the anchor of the one with qid
		$this->assertPageMatchesIndex( "A<ref><math qid=\"Q1\">x</math></ref> <math>x</math>\n<references/>" );
		$this->expectOutputRegex( '/./' );
	}

	public function testSavingIndexesTheNewRevision() {
		$old = $this->editPage( 'Formula ids', '<math>x</math>' )->getNewRevision()->getId();
		$new = $this->editPage( 'Formula ids', '<math>y</math> <math>z</math>' )->getNewRevision()->getId();

		$this->assertSame( [ "math.$old.0" ], array_keys( $this->idsInIndex( $old ) ) );
		$this->assertSame( [ "math.$new.0", "math.$new.1" ], array_keys( $this->idsInIndex( $new ) ) );
		$this->newSelectQueryBuilder()
			->select( 'math_input' )
			->from( 'mathlog' )
			->where( [ 'math_input' => [ 'y', 'z' ] ] )
			->distinct()
			->orderBy( 'math_input' )
			->assertResultSet( [ [ 'y' ], [ 'z' ] ] );
	}

	public function testSavingIndexesTextOfOtherModelsButNotCode() {
		$text = $this->editPage( 'Plain', new TextContent( '<math>x</math>' ) )->getNewRevision()->getId();
		$code = $this->editPage( 'MediaWiki:Common.js', '"<math>x</math>"' )->getNewRevision()->getId();

		$this->assertSame( [ "math.$text.0" ], array_keys( $this->idsInIndex( $text ) ) );
		$this->assertSame( [], $this->idsInIndex( $code ) );
	}

	public function testUpdateMathSkipsCode() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$code = $this->editPage( 'MediaWiki:Common.js', '"<math>x</math>"' )->getNewRevision()->getId();

		$this->maintenance->loadWithArgv( [ '--mode', 'source' ] );
		$this->maintenance->execute();

		$this->assertSame( [], $this->idsInIndex( $code ) );
		$this->expectOutputRegex( '/Updated 0 formulae/' );
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
