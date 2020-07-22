<?php
/**
 * MediaWiki MathSearch extension
 *
 * (c) 2012 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @file
 * @ingroup extensions
 */
class GetEquationsByQuery extends SpecialPage {
	function __construct() {
		parent::__construct( 'GetEquationsByQuery' );
	}

	/**
	 * @param unknown $par
	 */
	function execute( $par ) {
		global $wgRequest, $wgOut, $wgMathDebug;
		if ( !$wgMathDebug ) {
			$wgOut->addWikiTextAsInterface(
				"==Debug mode needed==  This function is only supported in math debug mode."
			);
			return;
		}

		$filterID = $wgRequest->getInt( 'filterID', 1000 );
		switch ( $filterID ) {
			case 0:
				$sqlFilter = [ 'valid_xml' => '0' ];
				break;
			case 1:
				$sqlFilter = [ 'math_status' => '3' ];
				break;
			case 2:
				$math5 = $wgRequest->getVal( 'first5', null );
				$sqlFilter = [
					'valid_xml' => '0',
					'left(math_mathml,5)' => $math5
				];
				break;
			case 3:
				$math5 = $wgRequest->getVal( 'first5', null );
				$sqlFilter = [
					'valid_xml' => '0',
						'left(math_tex,5)' => $math5
				];
				break;
			case 1000:
			default:
				$sqlFilter = [ 'math_status' => '3', 'valid_xml' => '0' ];
		}
		$wgOut->addWikiTextAsInterface(
			"Displaying first 10 equation for query: <pre>" . var_export( $sqlFilter, true ) . '</pre>'
		);
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
				[ 'math' ],
				[
					'math_mathml', 'math_inputhash', 'math_log', 'math_tex', 'valid_xml', 'math_status'
						, 'math_timestamp'
				],
				$sqlFilter,
				__METHOD__,
				[
					'LIMIT' => $wgRequest->getInt( 'limit', 10 ),
						'OFFSET' => $wgRequest->getInt( 'offset', 0 )
				]
		);
		foreach ( $res as $row ) {
			$wgOut->addWikiTextAsInterface( 'Renderd at <b>' . $row->math_timestamp . '</b> ', false );
			// @codingStandardsIgnoreStart
			$wgOut->addHtml( '<a href="/index.php/Special:FormulaInfo?tex=' . urlencode( $row->math_tex ) . '">more info</a>' );
			// @codingStandardsIgnoreEnd
			$wgOut->addWikiTextAsInterface( ':TeX-Code:<pre>' . $row->math_tex . '</pre> <br />' );
			$showmml = $wgRequest->getVal( 'showmml', false );
			if ( $showmml ) {
				$tstart = microtime( true );
				$renderer = MathRenderer::getRenderer( $row->math_tex, [], 'latexml' );
				$result = $renderer->render( true );
				$tend = microtime( true );
				$wgOut->addWikiTextAsInterface( ":rendering in " . ( $tend - $tstart ) . "s.", false );
				$renderer->writeCache();
				$wgOut->addHtml( "Output:" . $result . "<br/>" );
			}

		}
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}
