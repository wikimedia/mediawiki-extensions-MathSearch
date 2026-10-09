<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

use MediaWiki\Parser\ParserOptions;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MathSearchHooks::onParserBeforeInternalParse
 * @covers \MathIdGenerator::addAnchorsTo
 * @group Database
 */
class FormulaIdTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native' ],
			// Keeps BaseX out of the test
			'MathSearchMode' => 'latexml',
		] );
	}

	/**
	 * @return array [ ids of the math elements on the page, revision id ]
	 */
	private function idsOnPage( string $wikitext ): array {
		$revId = $this->editPage( 'Mass energy', $wikitext )->getNewRevision()->getId();
		$options = ParserOptions::newFromAnon();
		// The anchors are added for the legacy parser only
		$options->setUseParsoid( false );
		// Only native puts a math element on the page, which can carry the id
		$options->setOption( 'math', 'native' );
		$html = $this->getExistingTestPage( 'Mass energy' )->getParserOutput( $options, null, true )
			->getContentHolderText();
		preg_match_all( '/<math\b([^>]*)>/', $html, $matches );
		$ids = array_map( static function ( $attributes ) {
			preg_match_all( '/\sid="([^"]*)"/', $attributes, $id );
			return implode( ' ', $id[1] );
		}, $matches[1] );
		return [ $ids, $revId ];
	}

	/**
	 * @covers \MathSearchHooks::onMathFormulaPostRenderRevision
	 */
	public function testLinkToTheInfoPage() {
		$this->overrideConfigValue( 'MathSearchLinkToInfoPage', true );
		$revId = $this->editPage( 'Mass energy', '<math>E=mc^2</math>' )->getNewRevision()->getId();
		$options = ParserOptions::newFromAnon();
		$options->setUseParsoid( false );
		$options->setOption( 'math', 'native' );
		$html = $this->getExistingTestPage( 'Mass energy' )->getParserOutput( $options, null, true )
			->getContentHolderText();

		$this->assertStringContainsString( "Special:FormulaInfo&amp;pid=$revId&amp;eid=math.$revId.0", $html );
	}

	public function testGeneratedIdOnMathElement() {
		[ $ids, $revId ] = $this->idsOnPage( '<math>E=mc^2</math> <math>a^2</math>' );

		$this->assertSame( [ "math.$revId.0", "math.$revId.1" ], $ids );
	}

	public function testManualIdIsNotDoubled() {
		[ $ids ] = $this->idsOnPage( '<math id="emc">E=mc^2</math>' );

		$this->assertSame( [ 'emc' ], $ids );
	}

	public function testRepeatedManualIdKeepsTheIdOnThePage() {
		[ $ids, $revId ] = $this->idsOnPage( '<math id="a">x</math> <math id="a">y</math>' );

		$this->assertSame( [ 'a', 'a' ], $ids );
		$this->assertSame( "math.$revId.1", $this->getDb()->newSelectQueryBuilder()
			->select( 'mathindex_anchor' )
			->from( 'mathindex' )
			->where( [ 'mathindex_revision_id' => $revId, 'mathindex_anchor' => "math.$revId.1" ] )
			->caller( __METHOD__ )
			->fetchField() );
	}

	public function testTagInReferenceFromTemplateGetsNoId() {
		$this->editPage( 'Template:Eq', '<ref><math>z</math></ref>' );
		[ $ids, $revId ] = $this->idsOnPage( "<math>x</math>{{Eq}}\n<references/>" );

		// The template is not part of the wikitext of the revision, so the index has no anchor for z
		$this->assertSame( [ "math.$revId.0", '' ], $ids );
	}

	public function testPreviewWithoutRevisionGetsNoIds() {
		$options = ParserOptions::newFromAnon();
		$options->setUseParsoid( false );
		$options->setOption( 'math', 'native' );
		$html = $this->getServiceContainer()->getParserFactory()->getInstance()->parse(
			'<math>x</math>', $this->getNonexistingTestPage( 'Preview' )->getTitle(), $options
		)->getContentHolderText();

		$this->assertDoesNotMatchRegularExpression( '/<math\b[^>]*\sid=/', $html );
	}
}
