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
class FormulaInfo extends SpecialPage {
	private $purge = false;
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
		$this->purge = $wgRequest->getVal( 'purge', false );
		if ( is_null( $pid ) or is_null( $eid ) ) {
			$tex = $wgRequest->getVal( 'tex', '' );
			if ( $tex == '' ) {
				$wgOut->addHTML( '<b>Please specify page and equation id</b>' );
			} else {
				$this->InfoTex( $tex );
			}
		} else {
			$this->DisplayInfo( $pid, $eid );
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
		global $wgMathDebug;
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
		$link = $revision->getTitle()->getLinkURL( array(
				'action' => 'purge',
				'mathpurge' => 'true'
		) );
		$out->addHtml( "<a href=\"$link\">(force rerendering)</a>" );
		/* @var $mo MathObject  */
		$mo = MathObject::constructformpage( $oldID, $eid );
		if ( !$mo ) {
			$out->addWikiText( 'Cannot find the equation data in the database.' );
			return false;
		}
		$out->addWikiText( "Occurrences on the following pages:" );
		$all = $mo->getAllOccurences();
		foreach ( $all as  $occ ) {
			/** @type MathObject $occ */
			$out->addWikiText( '*' . $occ->printLink2Page( false ) );
		}
		$out->addWikiText( 'Hash: ' . $mo->getMd5() );
		$this->printSource( $mo->getUserInputTex(), 'TeX (original user input)', 'latex' );
		$texInfo = $mo->getTexInfo();
		$this->printSource( $texInfo->getChecked(), 'TeX (checked)', 'latex' );
		$this->DisplayRendering( $mo->getUserInputTex(), 'latexml' );
		$this->DisplayRendering( $mo->getUserInputTex(), 'mathml' );
		$this->DisplayRendering( $mo->getUserInputTex(), 'png' );
		$out->addWikiText( '==Similar pages==' );
		$out->addWikiText(
			'Calculated based on the variables occurring on the entire ' . $pageName . ' page'
		);
		$pid = Revision::newFromId( $oldID )->getTitle()->getArticleID();
		$mo->findSimilarPages( $pid );
		$out->addWikiText( '==Identifiers==' );
		foreach ( $texInfo->getIdentifiers() as $x ){
			$out->addWikiText( '* <math>'.$x.'</math>' );
		}
		$out->addWikiText( '=== MathML observations ===' );
		$mo->getObservations();
		if ( $wgMathDebug ) {
			$out->addWikiText( '==LOG and Debug==' );
			$this->printSource( $mo->getTimestamp(), 'Rendered at', 'text', false );
			$this->printSource( $mo->getIndexTimestamp(), 'and indexed at', 'text', false );
			$this->printSource( $mo->isValidMathML( $mo->getMathml() ), 'validxml', 'text', false );
			$out->addHtml( $mo->isValidMathML( $mo->getMathml() ) ? "valid" : "invalid" );
			$this->printSource( $mo->getStatusCode(), 'status' );
			$out->addHtml( htmlspecialchars( $mo->getLog(), 'status' ) );
		}
	}

	private function printSource( $source, $description = "", $language = "text", $linestart = true ) {
		if ( $description ) {
			$description .= ": ";
		}
		$this->getOutput()->addWikiText( "$description<syntaxhighlight lang=\"$language\">" .
			$source . '</syntaxhighlight>', $linestart );
	}

	private static function getlengh( $binray ) {
		$uncompressed = strlen( $binray );
		$compressed = strlen( gzcompress( $binray ) );
		return self::formatBytes( $uncompressed ) . " / " . self::formatBytes( $compressed );
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

	public static function hasMathMLSupport( $mode ) {
		if ( $mode === 'latexml' or $mode === 'mathml' ) {
			return true;
		} else {
			return false;
		}
	}

	public static function hasSvgSupport( $mode ) {
		if ( $mode === 'latexml' or $mode === 'mathml' ) {
			return true;
		} else {
			return false;
		}
	}

	public static function hasPngSupport( $mode ) {
		if ( $mode === 'png' ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param $tex
	 * @param $mode
	 *
	 * @throws MWException
	 *
	 * @internal param $out
	 * @internal param $mo
	 */
	private function DisplayRendering( $tex, $mode ) {
		global $wgExtensionAssetsPath, $wgMathValidModes;
		if ( !in_array( $mode, $wgMathValidModes ) ) {
			return;
		}
		$out = $this->getOutput();
		$names = MathHooks::getMathNames();
		$name = $names[$mode];
		$out->addWikiText( "=== $name rendering === " );
		$renderer = MathRenderer::getRenderer( $tex, array(), $mode );
		if ( $this->purge ) {
			$renderer->render( true );
		} elseif ( $mode == 'mathml' || ! $renderer->isInDatabase() ) {
			// workaround for restbase mathml mode that does not support database access
			// $out->addWikiText( "No database entry. Start rendering" );
			$renderer->render();
		}
		if ( self::hasMathMLSupport( $mode ) ) {
			$out->addHtml(
				'<div class="toccolours mw-collapsible mw-collapsed"  style="text-align: left">'
			);
			$out->addWikiText( 'MathML (' . self::getlengh( $renderer->getMathml() ) . ') :', false );
			$imgUrl = $wgExtensionAssetsPath . "/MathSearch/images/math_search_logo.png";
			$mathSearchImg = Html::element(
				'img', array( 'src' => $imgUrl, 'width' => 15, 'height' => 15 )
			);
			$out->addHtml( '<a href="/wiki/Special:MathSearch?mathpattern=' . urlencode( $tex ) .
				'&searchx=Search">' . $mathSearchImg . '</a>' );
			$out->addHtml( $renderer->getMathml() );
			$out->addHtml( '<div class="mw-collapsible-content">' );
			$out->addWikiText(
				'<syntaxhighlight lang="xml">' . ( $renderer->getMathml() ) . '</syntaxhighlight>'
			);
			$out->addHtml( '</div></div>' );
		}
		if ( self::hasSvgSupport( $mode ) ) {
			if ( $renderer->getSvg( 'cached' ) === '' ) {
				$out->addWikiText( 'SVG image empty. Force Re-Rendering' );
				$renderer->render( true );
			}
			$out->addWikiText( 'SVG (' . self::getlengh( $renderer->getSvg( 'render' ) ) . ') :', false );
			$out->addHtml( $renderer->getSvg() ); // FALSE, 'mwe-math-demo' ) );
			$out->addHtml( "<br />\n" );
		}
		if ( self::hasPngSupport( $mode ) ) {
			$out->addWikiText( 'PNG (' . self::getlengh( $renderer->getPng() ) . ') :', false );
			$out->addHtml( $renderer->getHtmlOutput() );
			$out->addHtml( "<br />\n" );
		}
		$renderer->writeCache();
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}
