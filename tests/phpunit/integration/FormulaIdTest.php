<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

use MathSearchHooks;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MathSearchHooks::onMathFormulaPostRenderRevision
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
	 * @return array [ html, rendered MathML, revision id ]
	 */
	private function renderOnPage( string $wikitext, string $tex, array $params ): array {
		$revision = $this->editPage( 'Mass energy', $wikitext )->getNewRevision();
		$services = $this->getServiceContainer();
		$renderer = $services->get( 'Math.RendererFactory' )->getRenderer( $tex, $params, 'native' );
		$renderer->render();
		$html = $renderer->getHtmlOutput();
		$hooks = new MathSearchHooks(
			$services->getConnectionProvider(),
			$services->get( 'Math.RendererFactory' ),
			$services->getRevisionLookup()
		);
		$hooks->onMathFormulaPostRenderRevision( $revision, $renderer, $html );
		return [ $html, $renderer->getMathml(), $revision->getId() ];
	}

	public function testGeneratedIdOnMathElementOnly() {
		[ $html, $mathml, $revId ] = $this->renderOnPage( '<math>E=mc^2</math>', 'E=mc^2', [] );

		$this->assertMatchesRegularExpression( "/^<math id=\"math\\.$revId\\.\\d+\" class=\"mwe-math-element/", $html );
		$this->assertStringNotContainsString( 'id=', $mathml );
		$anchor = $this->getDb()->newSelectQueryBuilder()
			->select( 'mathindex_anchor' )
			->from( 'mathindex' )
			->where( [ 'mathindex_revision_id' => $revId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertStringContainsString( "<math id=\"$anchor\"", $html );
		$stored = $this->getDb()->newSelectQueryBuilder()
			->select( 'math_mathml' )
			->from( 'mathlog' )
			->caller( __METHOD__ )
			->fetchFieldValues();
		$this->assertNotSame( [], $stored );
		foreach ( $stored as $mml ) {
			$this->assertStringNotContainsString( 'id=', $mml );
		}
	}

	public function testManualIdIsNotDoubled() {
		[ $html ] = $this->renderOnPage( '<math id="emc">E=mc^2</math>', 'E=mc^2', [ 'id' => 'emc' ] );

		$this->assertSame( 1, substr_count( $html, 'id=' ) );
		$this->assertStringContainsString( 'id="emc"', $html );
	}
}
