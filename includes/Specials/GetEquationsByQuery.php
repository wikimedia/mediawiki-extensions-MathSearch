<?php
namespace MediaWiki\Extension\MathSearch\Specials;

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2012 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @file
 * @ingroup extensions
 */

use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

class GetEquationsByQuery extends SpecialPage {

	public function __construct() {
		parent::__construct( 'GetEquationsByQuery' );
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		if ( !$this->getConfig()->get( 'MathDebug' ) ) {
			$this->getOutput()->addWikiTextAsInterface(
				"==Debug mode needed==  This function is only supported in math debug mode."
			);
			return;
		}

		$filterID = $this->getRequest()->getInt( 'filterID', 1 );
		switch ( $filterID ) {
			case 1:
				$sqlFilter = [ 'math_statuscode' => '3' ];
				break;
			case 2:
				$math5 = $this->getRequest()->getVal( 'first5', null );
				$sqlFilter = [
					'left(math_mathml,5)' => $math5
				];
				break;
			default:
				$math5 = $this->getRequest()->getVal( 'first5', null );
				$sqlFilter = [
					'left(math_tex,5)' => $math5
				];
		}
		$this->getOutput()->addWikiTextAsInterface(
			"Displaying first 10 equation for query: <pre>" . var_export( $sqlFilter, true ) . '</pre>'
		);
		$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getReplicaDatabase();
		$res = $dbr->select(
			[ 'mathlog' ],
			[
				'math_mathml', 'math_inputhash', 'math_log', 'math_tex', 'math_statuscode', 'math_timestamp'
			],
			$sqlFilter,
			__METHOD__,
			[
				'LIMIT' => $this->getRequest()->getInt( 'limit', 10 ),
				'OFFSET' => $this->getRequest()->getInt( 'offset' ),
			]
		);
		foreach ( $res as $row ) {
			$this->getOutput()->addWikiTextAsInterface( 'Renderd at <b>' . $row->math_timestamp . '</b> ', false );
			$this->getOutput()->addHTML( '<a href="/index.php/Special:FormulaInfo?tex=' .
				urlencode( $row->math_tex ) . '">more info</a>' );
			$this->getOutput()->addWikiTextAsInterface( ':TeX-Code:<pre>' . $row->math_tex . '</pre> <br />' );
			$showmml = $this->getRequest()->getVal( 'showmml', false );
			if ( $showmml ) {
				$tstart = microtime( true );
				$renderer = MathRenderer::getRenderer( $row->math_tex, [], 'latexml' );
				$renderer->render( true );
				$tend = microtime( true );
				$this->getOutput()->addWikiTextAsInterface( ":rendering in " . ( $tend - $tstart ) . "s.", false );
				$renderer->writeCache();
				$this->getOutput()->addHTML( "Output:" . $renderer->getHtmlOutput() . "<br/>" );
			}

		}
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}
