<?php
/**
 * MediaWiki MathSearch extension
 *
 * (c) 2012 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * 2012/04/25 Changed LaTeXML for the MathML rendering which is passed to MathJAX
 * @file
 * @ingroup extensions
 */
class GetEquationsByQuery extends SpecialPage {
	/**
	 *
	 */
	function __construct() {
		parent::__construct( 'GetEquationsByQuery' );
	}

	/**
	 * @param unknown $par
	 */
	function execute( $par ) {
		global $wgRequest, $wgOut, $wgMathDebug;
		if ( ! $wgMathDebug ) {
			$wgOut->addWikiText( "==Debug mode needed==  This function is only supported in math debug mode." );
			return false;
		}

		$filterID = $wgRequest->getInt( 'filterID', 1000 );
		switch ( $filterID ) {
			case 0:
				$sqlFilter = array( 'valid_xml' => '0' );
				break;
			case 1:
				$sqlFilter = array( 'math_status' => '3' );
				break;
			case 2:
				$math5 = $wgRequest->getVal( 'first5', null );
				$sqlFilter = array( 'valid_xml' => '0',
					'left(math_mathml,5)' => $math5 );
				break;
			case 3:
				$math5 = $wgRequest->getVal( 'first5', null );
				$sqlFilter = array( 'valid_xml' => '0',
						'left(math_tex,5)' => $math5 );
				break;
			case 1000:
			default:
				$sqlFilter = array( 'math_status' => '3', 'valid_xml' => '0' );
		}
		$wgOut->addWikiText( "Displaying first 10 equation for query: <pre>" . var_export( $sqlFilter, true ) . '</pre>' );
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
				array( 'math' ),
				array( 'math_mathml', 'math_inputhash', 'math_log', 'math_tex', 'valid_xml', 'math_status'
						, 'math_timestamp' ),
				$sqlFilter,
				__METHOD__,
				array( 'LIMIT' => $wgRequest->getInt( 'limit', 10 ),
						'OFFSET' => $wgRequest->getInt( 'offset', 0 ) )
		);
		foreach ( $res as $row ) {
			$wgOut->addWikiText( 'Renderd at <b>' . $row->math_timestamp . '</b> ', FALSE );
			$wgOut->addHtml( '<a href="/index.php/Special:FormulaInfo?tex=' . urlencode( $row->math_tex ) . '">more info</a>' );
			$wgOut->addWikiText( ':TeX-Code:<pre>' . $row->math_tex . '</pre> <br />' );
			$showmml = $wgRequest->getVal( 'showmml', false );
			if ( $showmml ) {
				$tstart = microtime( true );
				$renderer = MathRenderer::getRenderer( $row->math_tex, array(), MW_MATH_LATEXML );
				$result = $renderer->render( true );
				$tend = microtime( true );
				$wgOut->addWikiText( ":rendering in " . ( $tend -$tstart ) . "s.", false );
				$renderer->writeCache();
				$wgOut->addHtml( "Output:" . $result . "<br/>" );
			}

		}

	}

}
