<?php

use MediaWiki\Extension\Math\Hooks as MathHooks;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\MediaWikiServices;

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

	/** @var bool */
	private $purge = false;

	function __construct() {
		parent::__construct( 'FormulaInfo' );
	}

	/**
	 * @param string|null $par
	 */
	function execute( $par ) {
		$pid = $this->getRequest()->getVal( 'pid' ); // Page ID
		$eid = $this->getRequest()->getVal( 'eid' ); // Equation ID
		$this->purge = $this->getRequest()->getVal( 'purge', false );
		if ( $pid === null || $eid === null ) {
			$tex = $this->getRequest()->getVal( 'tex', '' );
			if ( $tex == '' ) {
				$this->getOutput()->addHTML( '<b>Please specify page and equation id</b>' );
			} else {
				$this->InfoTex( $tex );
			}
		} else {
			$this->DisplayInfo( $pid, $eid );
		}
	}

	public function InfoTex( $tex ) {
		if ( !$this->getConfig()->get( 'MathDebug' ) ) {
			$this->getOutput()->addWikiTextAsInterface( "tex queries only supported in debug mode" );
			return false;
		}
		$this->getOutput()->addWikiTextAsInterface( "Info for <code>" . $tex . '</code>' );

		$mo = new MathObject( $tex );
		$allPages = $mo->getAllOccurrences();
		if ( $allPages ) {
			$this->DisplayInfo( $allPages[0]->getRevisionID(), $allPages[0]->getAnchorID() );
		} else {
			$this->getOutput()->addWikiTextAsInterface(
				"No occurrences found clean up the database to remove unused formulae"
			);
		}

		self::DisplayTranslations( $tex );
	}

	/**
	 * @param string $tex
	 * @return bool
	 */
	public static function DisplayTranslations( $tex ) {
		global $wgOut, $wgMathSearchTranslationUrl;

		if ( $wgMathSearchTranslationUrl === false ) {
			return false;
		}

		$resultMaple = self::GetTranslation( 'Maple', $tex );
		$resultMathe = self::GetTranslation( 'Mathematica', $tex );

		$wgOut->addWikiTextAsInterface( '==Translations to Computer Algebra Systems==' );

		if ( $resultMaple === false || $resultMathe === false ) {
			$wgOut->addWikiTextAsInterface( 'An error occurred during translation.' );
			return false;
		}

		self::PrintTranslationResult( 'Maple', $resultMaple );
		self::PrintTranslationResult( 'Mathematica', $resultMathe );
		return true;
	}

	private static function GetTranslation( $cas, $tex ) {
		global $wgMathSearchTranslationUrl;
		$params = [ 'cas' => $cas, 'latex' => $tex ];
		return Http::post(
			$wgMathSearchTranslationUrl, [ "postData" => $params, "timeout" => 60 ]
		);
	}

	private static function PrintTranslationResult( $cas, $result ) {
		global $wgOut;

		$jsonResult = json_decode( $result, true );
		$wgOut->addWikiTextAsInterface( '=== Translation to ' . $cas . '===' );

		$wgOut->addHTML(
			'<div class="toccolours mw-collapsible mw-collapsed"  style="text-align: left">'
		);
		$wgOut->addWikiTextAsInterface( 'In ' . $cas . ': <code>' . $jsonResult['result'] . '</code>' );

		$wgOut->addHTML( '<div class="mw-collapsible-content">' );
		$wgOut->addWikiTextAsInterface( str_replace( "\n", "\n\n", $jsonResult['log'] ) );
		$wgOut->addHTML( '</div></div>' );
	}

	/**
	 * @param int $oldID
	 * @param string $eid
	 *
	 * @return bool
	 */
	public function DisplayInfo( $oldID, $eid ) {
		$out = $this->getOutput();
		$out->addModuleStyles( [ 'ext.mathsearch.styles' ] );
		$out->addWikiTextAsInterface( '==General==' );
		$out->addWikiTextAsInterface(
			'Display information for equation id:' . $eid . ' on revision:' . $oldID
		);
		$revisionRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionById( $oldID );
		if ( !$revisionRecord ) {
			$out->addWikiTextAsInterface( 'There is no revision with id:' . $oldID . ' in the database.' );
			return false;
		}

		$title = Title::newFromLinkTarget( $revisionRecord->getPageAsLinkTarget() );
		$pageName = (string)$title;
		$out->addWikiTextAsInterface( "* Page found: [[$pageName#$eid|$pageName]] (eq $eid)  ", false );
		$link = $title->getLinkURL( [
			'action' => 'purge',
			'mathpurge' => 'true'
		] );
		$out->addHTML( "<a href=\"$link\">(force rerendering)</a>" );
		$mo = MathObject::constructformpage( $oldID, $eid );
		if ( !$mo ) {
			$out->addWikiTextAsInterface( 'Cannot find the equation data in the database.' .
				' Fetching from revision text.' );
			$mo = MathObject::newFromRevisionText( $oldID, $eid );
		}
		$out->addWikiTextAsInterface( "Occurrences on the following pages:" );
		$all = $mo->getAllOccurrences();
		foreach ( $all as $occ ) {
			$out->addWikiTextAsInterface( '*' . $occ->printLink2Page( false ) );
		}
		$out->addWikiTextAsInterface( 'Hash: ' . $mo->getMd5() );
		$this->printSource( $mo->getUserInputTex(), 'TeX (original user input)', 'latex' );
		$texInfo = $mo->getTexInfo();
		if ( $texInfo ) {
			$this->printSource( $texInfo->getChecked(), 'TeX (checked)', 'latex' );
		}
		$this->DisplayRendering( $mo->getUserInputTex(), 'latexml' );
		$this->DisplayRendering( $mo->getUserInputTex(), 'mathml' );

		self::DisplayTranslations( $mo->getUserInputTex() );

		$out->addWikiTextAsInterface( '==Similar pages==' );
		$out->addWikiTextAsInterface(
			'Calculated based on the variables occurring on the entire ' . $pageName . ' page'
		);
		$pid = $title->getArticleID();
		MathObject::findSimilarPages( $pid );
		$out->addWikiTextAsInterface( '==Identifiers==' );
		$relations = $mo->getRelations();
		if ( $texInfo ) {
			foreach ( $texInfo->getIdentifiers() as $x ) {
				$line = '* <math>' . $x . '</math>';
				if ( isset( $relations[$x] ) ) {
					foreach ( $relations[$x] as $r ) {
						$line .= ", {$r->definition} ($r->score)";
					}
				}
				$out->addWikiTextAsInterface( $line );
			}
		}
		$out->addWikiTextAsInterface( '=== MathML observations ===' );
		$mo->getObservations();
		if ( $this->getConfig()->get( 'MathDebug' ) ) {
			$out->addWikiTextAsInterface( '==LOG and Debug==' );
			$this->printSource( $mo->getTimestamp(), 'Rendered at', 'text', false );
			$this->printSource( $mo->getIndexTimestamp(), 'and indexed at', 'text', false );
			$this->printSource( $mo->isValidMathML( $mo->getMathml() ), 'validxml', 'text', false );
			$out->addHTML( $mo->isValidMathML( $mo->getMathml() ) ? "valid" : "invalid" );
			$this->printSource( $mo->getStatusCode(), 'status' );
			$out->addHTML( htmlspecialchars( $mo->getLog(), 'status' ) );
		}
	}

	private function printSource( $source, $description = "", $language = "text", $linestart = true ) {
		if ( $description ) {
			$description .= ": ";
		}
		$this->getOutput()->addWikiTextAsInterface( "$description<syntaxhighlight lang=\"$language\">" .
			$source . '</syntaxhighlight>', $linestart );
	}

	private static function getlengh( $binray ) {
		$uncompressed = strlen( $binray );
		$compressed = strlen( gzcompress( $binray ) );
		return self::formatBytes( $uncompressed ) . " / " . self::formatBytes( $compressed );
	}

	private static function formatBytes( $bytes, $precision = 3 ) {
		$units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];

		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );

		// Uncomment one of the following alternatives
		$bytes /= pow( 1024, $pow );
		// $bytes /= (1 << (10 * $pow));

		return round( $bytes, $precision ) . ' ' . $units[$pow];
	}

	public static function hasMathMLSupport( $mode ) {
		return ( $mode === 'latexml' || $mode === 'mathml' );
	}

	public static function hasSvgSupport( $mode ) {
		return ( $mode === 'latexml' || $mode === 'mathml' );
	}

	public static function hasPngSupport( $mode ) {
		return ( $mode === 'mathml' );
	}

	/**
	 * @param string $tex
	 * @param string $mode
	 */
	private function DisplayRendering( $tex, $mode ) {
		if ( !in_array( $mode, $this->getConfig()->get( 'MathValidModes' ) ) ) {
			return;
		}
		$out = $this->getOutput();
		$names = MathHooks::getMathNames();
		$name = $names[$mode];
		$out->addWikiTextAsInterface( "=== $name rendering === " );
		$renderer = MathRenderer::getRenderer( $tex, [], $mode );
		if ( $this->purge ) {
			$renderer->render( true );
		} elseif ( $mode == 'mathml' || !$renderer->isInDatabase() ) {
			// workaround for restbase mathml mode that does not support database access
			// $out->addWikiTextAsInterface( "No database entry. Start rendering" );
			$renderer->render();
		}
		if ( self::hasMathMLSupport( $mode ) ) {
			$out->addHTML(
				'<div class="toccolours mw-collapsible mw-collapsed"  style="text-align: left">'
			);
			$out->addWikiTextAsInterface(
				'MathML (' . self::getlengh( $renderer->getMathml() ) . ') :', false
			);
			$imgUrl = $this->getConfig()->get( 'ExtensionAssetsPath' ) .
				'/MathSearch/resources/images/math_search_logo.png';
			$mathSearchImg = Html::element(
				'img', [ 'src' => $imgUrl, 'width' => 15, 'height' => 15 ]
			);
			$out->addHTML( '<a href="/wiki/Special:MathSearch?mathpattern=' . urlencode( $tex ) .
				'&searchx=Search">' . $mathSearchImg . '</a>' );
			$out->addHTML( $renderer->getMathml() );
			$out->addHTML( '<div class="mw-collapsible-content">' );
			$out->addWikiTextAsInterface(
				'<syntaxhighlight lang="xml">' . ( $renderer->getMathml() ) . '</syntaxhighlight>'
			);
			$out->addHTML( '</div></div>' );
		}
		if ( self::hasSvgSupport( $mode ) ) {
			try {
				$svg = $renderer->getSvg( 'cached' );
				if ( $svg === '' ) {
					$out->addWikiTextAsInterface( 'SVG image empty. Force Re-Rendering' );
					$renderer->render( true );
					$svg = $renderer->getSvg( 'render' );
				}
				$out->addWikiTextAsInterface( 'SVG (' . self::getlengh( $svg ) . ') :', false );
				$out->addHTML( $svg ); // FALSE, 'mwe-math-demo' ) );
				$out->addHTML( "<br />\n" );
			} catch ( Exception $e ) {
				$out->addHTML( 'Failed to get svg.' );
			}
		}
		if ( self::hasPngSupport( $mode ) ) {
			if ( method_exists( $renderer, 'getPng' ) ) {
				$out->addWikiTextAsInterface( 'PNG (' . self::getlengh( $renderer->getPng() ) . ') :', false );
				$out->addHTML( $renderer->getHtmlOutput() );
				$out->addHTML( "<br />\n" );
			} else {
				try {
					$renderer = MathObject::cloneFromRenderer( $renderer );
					$rbi = $renderer->getRbi();
					$pngUrl = preg_replace( '#/svg/#', '/png/', $rbi->getFullSvgUrl() );
					$png = file_get_contents( $pngUrl );
					$out->addWikiTextAsInterface( 'PNG (' . self::getlengh( $png ) . ') :', false );
					$out->addHTML( "<img src='$pngUrl' />" );
					$out->addHTML( "<br />\n" );
				} catch ( Exception $e ) {
					$out->addHTML( 'Failed to get png.' );
				}
			}
		}
		$renderer->writeCache();
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}
