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
class FormulaInfo extends SpecialPage {
	/**
	 *
	 */
	function __construct() {
		parent::__construct( 'FormulaInfo' );
	}

	/**
	 * @param unknown $par
	 */
	function execute( $par ) {
		global $wgRequest, $wgOut;
		$pid = $wgRequest->getVal( 'pid' );// Page ID
		$eid = $wgRequest->getVal( 'eid' );// Equation ID
		if ( is_null( $pid ) or is_null( $eid ) ) {
			$tex = $wgRequest->getVal( 'tex', '' );
			if ( $tex == '' ) {
				$wgOut->addHTML( '<b>Please specify page and equation id</b>' );
			} else {
				self::InfoTex( $tex );
			}
		} else {
			self::DisplayInfo( $pid, $eid );
		}
	}
	public function InfoTex( $tex ) {
		global $wgMathDebug, $wgOut;
		if ( !$wgMathDebug ) {
			$wgOut->addWikiTex( "tex queries only supported in debug mode" );
			return false;
		}
		$wgOut->addWikiText( "Info for <code>" . $tex . '</code>' );
		/**
		 * @var MathObject Description
		 */
		$mo = new MathObject( $tex );
		$allPages = $mo->getAllOccurences();
		if ( $allPages ) {
			$this->DisplayInfo( $allPages[0]->getPageID(), $allPages[0]->getAnchorID() );
		} else {
			$wgOut->addWikiText( "No occurrences found clean up the database to remove unused formulae" );
		}
	}

	/**
	 * @param $oldID
	 * @param $eid
	 *
	 * @return bool
	 * @throws MWException
	 */
	public function DisplayInfo( $oldID, $eid ) {
		global $wgMathDebug, $wgExtensionAssetsPath;
		$out = $this->getOutput();
		$out->addModuleStyles( array( 'ext.mathsearch.styles' ) );
		$out->addWikiText( '==General==' );
		$out->addWikiText( 'Display information for equation id:' . $eid . ' on revision:' . $oldID );
		$revision = Revision::newFromId( $oldID );
		if ( !$revision ) {
			$out->addWikiText( 'There is no revision with id:' . $oldID . ' in the database.' );
			return false;
		}

		$pageName = (string)$revision->getTitle();
		$out->addWikiText( "* Page found: [[$pageName#$eid|$pageName]] (eq $eid)  ", false );
		$out->addHtml( '<a href="/index.php?title=' . $pageName . '&action=purge&mathpurge=true">(force rerendering)</a>' );
		$pid = Revision::newFromId($oldID)->getTitle()->getArticleID();
		/* @var $mo MathObject  */
		$mo = MathObject::constructformpage( $pid, $eid );
		if ( !$mo ) {
			$out->addWikiText( 'Cannot find the equation data in the database.' );
			return false;
		}
		$out->addWikiText( "Occurrences on the following pages:" );
		$all = $mo->getAllOccurences();
		foreach( $all as  $occ ){
			/** @type MathObject $occ */
			$out->addWikiText( '*' . $occ->printLink2Page( false ) );
		}
		// $wgOut->addWikiText('<b>:'.var_export($res,true).'</b>');
		$out->addWikiText( 'TeX (as stored in database): <syntaxhighlight lang="latex">' . $mo->getTex() . '</syntaxhighlight>' );
		$out->addWikiText( 'MathML (' . self::getlengh( $mo->getMathml() ) . ') :', false );

		$imgUrl = $wgExtensionAssetsPath . "/MathSearch/images/math_search_logo.png";
		$mathSearchImg = Html::element( 'img', array( 'src' => $imgUrl, 'width' => 15, 'height' => 15 ) );
		$out->addHtml( '<a href="/wiki/Special:MathSearch?mathpattern=' . urlencode( $mo->getTex() ) . '&searchx=Search">' . $mathSearchImg . '</a>' );
		$out->addHtml(  $mo->getMathml() );
		# $log=htmlspecialchars( $res->math_log );
		$out->addHtml( "<br />\n" );
		$out->addWikiText( 'SVG (' . self::getlengh( $mo->getSvg() ) . ') :', false );
		$out->addHtml( $mo->getFallbackImage( false , 'mwe-math-demo' ) );
		$out->addHtml( "<br />\n" );
		$out->addWikiText( 'PNG (' . self::getlengh( $mo->getPng() ) . ') :', false );
		$out->addHtml( preg_replace( '/mode=\d/', 'mode=0' ,$mo->getFallbackImage( true , 'mwe-math-demo' ) ) );
		$out->addHtml( "<br />\n" );
		$out->addWikiText( 'Hash : ' . $mo->getMd5(), false );
		$out->addHtml( "<br />" );
		$out->addWikiText( '==Similar pages==' );
		$out->addWikiText( 'Calculated based on the variables occurring on the entire ' . $pageName . ' page' );
		$mo->findSimilarPages( $pid );
		$out->addWikiText( '==Variables==' );
		$mo->getObservations();
		$out->addWikiText( '==MathML==' );

		$out->addHtml( "<br />" );
		$out->addHtml( '<div class="NavFrame"><div class="NavHead">mathml</div>
<div class="NavContent" style="text-align: left">' );
		$out->addWikiText( '<syntaxhighlight lang="xml">' . ( $mo->getMathml() ) . '</syntaxhighlight>' );
		$out->addHtml( '</div></div>' );
		$out->addHtml( "<br />" );
		$out->addHtml( "<br />" );
		if ( $wgMathDebug ) {
		$out->addWikiText( '==LOG and Debug==' );
		$out->addWikiText( 'Rendered at : <syntaxhighlight lang="text">' . $mo->getTimestamp()
			. '</syntaxhighlight> an idexed at <syntaxhighlight lang="text">' . $mo->getIndexTimestamp() . '</syntaxhighlight>' );
		$out->addWikiText( 'validxml : <syntaxhighlight lang="text">' . $mo->isValidMathML( $mo->getMathml() ) . '</syntaxhighlight> recheck:', false );
		$out->addHtml( $mo->isValidMathML( $mo->getMathml() ) ? "valid":"invalid" );
		$out->addWikiText( 'status : <syntaxhighlight lang="text">' . $mo->getStatusCode() . '</syntaxhighlight>' );
		$out->addHtml( htmlspecialchars( $mo->getLog() ) );
		}
	}
	private static function getlengh( $binray ) {
		$uncompressed = strlen( $binray );
		$compressed = strlen( gzcompress ( $binray ) );
		return self::formatBytes( $uncompressed ) . " / " . self::formatBytes( $compressed ) ;
	}
		private static function formatBytes( $bytes, $precision = 3 ) {
			$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

			$bytes = max( $bytes, 0 );
			$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
			$pow = min( $pow, count( $units ) - 1 );

			// Uncomment one of the following alternatives
			$bytes /= pow( 1024, $pow );
			// $bytes /= (1 << (10 * $pow));

		return round( $bytes, $precision ) . ' ' . $units[$pow];
	}
}
