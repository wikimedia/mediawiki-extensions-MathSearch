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
		if ( $pid === null || $eid === null ) {
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
			$wgOut->addWikiTextAsInterface( "tex queries only supported in debug mode" );
			return false;
		}
		$wgOut->addWikiTextAsInterface( "Info for <code>" . $tex . '</code>' );

		/**
		 * @var MathObject Description
		 */
		$mo = new MathObject( $tex );
		$allPages = $mo->getAllOccurences();
		if ( $allPages ) {
			$this->DisplayInfo( $allPages[0]->getPageID(), $allPages[0]->getAnchorID() );
		} else {
			$wgOut->addWikiTextAsInterface(
				"No occurrences found clean up the database to remove unused formulae"
			);
		}

		$this->DisplayTranslations( $tex );
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

		if ( $resultMaple !== false ) {
			self::PrintTranslationResult( 'Maple', $resultMaple );
		}

		if ( $resultMathe !== false ) {
			self::PrintTranslationResult( 'Mathematica', $resultMathe );
		}

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

		$wgOut->addHtml(
			'<div class="toccolours mw-collapsible mw-collapsed"  style="text-align: left">'
		);
		$wgOut->addWikiTextAsInterface( 'In ' . $cas . ': <code>' . $jsonResult['result'] . '</code>' );

		$wgOut->addHtml( '<div class="mw-collapsible-content">' );
		$wgOut->addWikiTextAsInterface( str_replace( "\n", "\n\n", $jsonResult['log'] ) );
		$wgOut->addHtml( '</div></div>' );
	}

	/**
	 * @param int $oldID
	 * @param string $eid
	 *
	 * @return bool
	 * @throws MWException
	 */
	public function DisplayInfo( $oldID, $eid ) {
		global $wgMathDebug;
		$out = $this->getOutput();
		$out->addModuleStyles( [ 'ext.mathsearch.styles' ] );
		$out->addWikiTextAsInterface( '==General==' );
		$out->addWikiTextAsInterface(
			'Display information for equation id:' . $eid . ' on revision:' . $oldID
		);
		$revision = Revision::newFromId( $oldID );
		if ( !$revision ) {
			$out->addWikiTextAsInterface( 'There is no revision with id:' . $oldID . ' in the database.' );
			return false;
		}

		$pageName = (string)$revision->getTitle();
		$out->addWikiTextAsInterface( "* Page found: [[$pageName#$eid|$pageName]] (eq $eid)  ", false );
		$link = $revision->getTitle()->getLinkURL( [
				'action' => 'purge',
				'mathpurge' => 'true'
		] );
		$out->addHtml( "<a href=\"$link\">(force rerendering)</a>" );
		/* @var $mo MathObject  */
		$mo = MathObject::constructformpage( $oldID, $eid );
		if ( !$mo ) {
			$out->addWikiTextAsInterface( 'Cannot find the equation data in the database.' .
				' Fetching from revision text.' );
			$mo = MathObject::newFromRevisionText( $oldID, $eid );
		}
		$out->addWikiTextAsInterface( "Occurrences on the following pages:" );
		$all = $mo->getAllOccurences();
		foreach ( $all as  $occ ) {
			/** @var MathObject $occ */
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

		$this->DisplayTranslations( $mo->getUserInputTex() );

		$out->addWikiTextAsInterface( '==Similar pages==' );
		$out->addWikiTextAsInterface(
			'Calculated based on the variables occurring on the entire ' . $pageName . ' page'
		);
		$pid = Revision::newFromId( $oldID )->getTitle()->getArticleID();
		$mo->findSimilarPages( $pid );
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
		if ( $wgMathDebug ) {
			$out->addWikiTextAsInterface( '==LOG and Debug==' );
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
		if ( $mode === 'latexml' || $mode === 'mathml' ) {
			return true;
		} else {
			return false;
		}
	}

	public static function hasSvgSupport( $mode ) {
		if ( $mode === 'latexml' || $mode === 'mathml' ) {
			return true;
		} else {
			return false;
		}
	}

	public static function hasPngSupport( $mode ) {
		if ( $mode === 'mathml' ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param string $tex
	 * @param string $mode
	 *
	 * @throws MWException
	 */
	private function DisplayRendering( $tex, $mode ) {
		global $wgExtensionAssetsPath, $wgMathValidModes;
		if ( !in_array( $mode, $wgMathValidModes ) ) {
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
			$out->addHtml(
				'<div class="toccolours mw-collapsible mw-collapsed"  style="text-align: left">'
			);
			$out->addWikiTextAsInterface(
				'MathML (' . self::getlengh( $renderer->getMathml() ) . ') :', false
			);
			$imgUrl = $wgExtensionAssetsPath . "/MathSearch/resources/images/math_search_logo.png";
			$mathSearchImg = Html::element(
				'img', [ 'src' => $imgUrl, 'width' => 15, 'height' => 15 ]
			);
			$out->addHtml( '<a href="/wiki/Special:MathSearch?mathpattern=' . urlencode( $tex ) .
				'&searchx=Search">' . $mathSearchImg . '</a>' );
			$out->addHtml( $renderer->getMathml() );
			$out->addHtml( '<div class="mw-collapsible-content">' );
			$out->addWikiTextAsInterface(
				'<syntaxhighlight lang="xml">' . ( $renderer->getMathml() ) . '</syntaxhighlight>'
			);
			$out->addHtml( '</div></div>' );
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
				$out->addHtml( $svg ); // FALSE, 'mwe-math-demo' ) );
				$out->addHtml( "<br />\n" );
			} catch ( Exception $e ) {
				$out->addHTML( 'Failed to get svg.' );
			}
		}
		if ( self::hasPngSupport( $mode ) ) {
			if ( method_exists( $renderer, 'getPng' ) ) {
				$out->addWikiTextAsInterface( 'PNG (' . self::getlengh( $renderer->getPng() ) . ') :', false );
				$out->addHtml( $renderer->getHtmlOutput() );
				$out->addHtml( "<br />\n" );
			} else {
				try {
					$renderer = MathObject::cloneFromRenderer( $renderer );
					$rbi = $renderer->getRbi();
					$pngUrl = preg_replace( '#/svg/#', '/png/', $rbi->getFullSvgUrl() );
					$png = file_get_contents( $pngUrl );
					$out->addWikiTextAsInterface( 'PNG (' . self::getlengh( $png ) . ') :', false );
					$out->addHtml( "<img src='$pngUrl' />" );
					$out->addHtml( "<br />\n" );
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
