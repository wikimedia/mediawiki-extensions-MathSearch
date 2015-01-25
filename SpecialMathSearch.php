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
	class SpecialMathSearch extends SpecialPage {
		const  GUI_PATH = '/modules/min/index.xhtml';
		public $qs;
		public $math_result;
		public $mathSearchExpr;
		public $numTextResults;
		public $mathResults;
		public $mathpattern;
		public $textpattern;
		public $mathmlquery;
		public $mathEngine;
		public $displayQuery;
		private $mathBackend;
		private $resultID = 0;
		private $xQueryEngines = array( 'db2' );

		public static function exception_error_handler($errno, $errstr, $errfile, $errline ) {
			if (!(error_reporting() & $errno)) {
				// This error code is not included in error_reporting
				return;
			}
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		}

		/**
		 *
		 */
		function __construct() {
			parent::__construct( 'MathSearch' );
		}

		/**
		 * Processes the submitted Form input
		 * @param array $formData
		 */
		public static function processInput( $formData )
		{
			$instance = new SpecialMathSearch();
			$instance->mathpattern = $formData[ 'mathpattern' ];
			$instance->textpattern = $formData[ 'textpattern' ];
			$instance->mathEngine = $formData[ 'mathEngine' ];
			$instance->displayQuery = $formData[ 'displayQuery' ];
			$instance->performSearch();
		}

		/**
		 * The main function
		 */
		public function execute( $par ) {
			set_error_handler("SpecialMathSearch::exception_error_handler");
			global $wgExtensionAssetsPath;
			$request = $this->getRequest();
			$this->setHeaders();
			$this->mathpattern = $request->getText( 'mathpattern' );
			$this->textpattern = $request->getText( 'textpattern', '' );
			$isEncoded = $request->getBool( 'isEncoded', false );
			if ( $isEncoded ) {
				$this->mathpattern = htmlspecialchars_decode( $this->mathpattern );
			}
			$this->searchForm();
			if( file_exists( __DIR__ . self::GUI_PATH ) ) {
				$minurl = $wgExtensionAssetsPath . '/MathSearch'. self::GUI_PATH ;
				$this->getOutput()->addHTML("<p><a href=\"${minurl}\">Test experimental math input interface</a></p>");
			}
			if ( $this->mathpattern || $this->textpattern ) {
				$this->performSearch();
			}
			restore_error_handler();
		}

		/**
		 * Generates the search input form
		 */
		private function searchForm()
		{
			# A formDescriptor Array to tell HTMLForm what to build
			$formDescriptor = array(
				'mathpattern'  => array(
					'label'   => 'LaTeX pattern', # What's the label of the field
					'class'   => 'HTMLTextField', # What's the input type
					'help'    => 'for example: \sin(?x^2)',
					'default' => $this->mathpattern,
				),
				'textpattern'  => array(
					'label'   => 'Text pattern', # What's the label of the field
					'class'   => 'HTMLTextField', # What's the input type
					'help'    => 'a term like: algebra',
					'default' => $this->textpattern,
				),
				'mathEngine'   => array(
					'label'   => 'Math engine',
					'class'   => 'HTMLSelectField',
					'options' => array(
						'MathWebSearch' => 'mws',
						'DB2'           => 'db2',
						'BaseX'         => 'basex'
					),
					'default' => $this->mathEngine,
				),
				'displayQuery' => array(
					'label'   => 'Display search query',
					'type'    => 'check',
					'default' => $this->displayQuery,
				)
			);
			$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() ); # We build the HTMLForm object
			$htmlForm->setSubmitText( 'Search' );
			$htmlForm->setSubmitCallback( array( get_class( $this ), 'processInput' ) );
			$htmlForm->setHeaderText( "<h2>Input</h2>" );
			$htmlForm->show(); # Displaying the form
		}

		public function performSearch() {
			global $wgMathDebug;
			$out = $this->getOutput();
			$time_start = microtime( true );
			$out->addWikiText( '==Results==' );
			$out->addWikiText( 'You searched for the LaTeX pattern "' . $this->mathpattern . '" and the text pattern "' . $this->textpattern . '".' );
			if ( $this->mathpattern ) {
				$query = new MathQueryObject( $this->mathpattern );
				switch ( $this->mathEngine ) {
					case 'db2':
						$query->setXQueryDialect( 'db2' );
						break;
				}
				$cQuery = $query->getCQuery();
				if ( $cQuery ) {
					$out->addWikiText( "Your mathpattern was successfully rendered!" );
					if ( $this->displayQuery === true ) {
						if ( in_array( $this->mathEngine, $this->xQueryEngines ) ) {
							$this->printSource( $query->getXQuery() );
						} else {
							$this->printSource( $query->getCQuery() );
						}
					}
					switch ( $this->mathEngine ) {
						case 'db2':
							$this->mathBackend = new MathEngineDB2( $query );
							break;
						case 'basex':
							$this->mathBackend = new MathEngineBaseX( $query );
							break;
						default:
							$this->mathBackend = new MathEngineMws( $query );
					}

					if ( $this->mathBackend->postQuery() ) {
						$out->addWikiText( "Your mathquery was successfully submitted and " . $this->mathBackend->getSize() . " hits were obtained." );
					} else {
						$out->addWikiText( "Failed to post query." );
					}
					$time_end = microtime( true );
					$time = $time_end - $time_start;
					wfDebugLog( "MathSearch", "math searched in $time seconds" );
				} else {
					$out->addWikiText( "Your query could not be rendered see the DebugLog for details." );
				}
				// $out->addHTML(var_export($this->mathResults, true));
			} else {
				$out->addWikiText( 'The math-pattern is empty. No math search has been performed.' );
				$out->addWikiText( "To view the text results click [{{canonicalurl:search|search=$this->textpattern}} Text-only search]." );
			}

			if ( $this->mathBackend && $this->textpattern == "" ) {
				$results = $this->mathBackend->getResultSet();
				if ( $results ) {
					foreach ( $results as $revisionID => $page ) {
						$revision = Revision::newFromId( $revisionID );
						if ( $revision ) {
							if ( $revision->isCurrent() ) {
								wfDebugLog( "MathSearch",
									"Found revision " . $revision->getTitle()->getText() );
								$pagename = (string)$revision->getTitle();
								$out->addWikiText( "==[[$pagename]]==" );
								$this->DisplayMath( $revisionID );
							}
						} else
							$out->addWikiText( "Error with Revision (ID=$revisionID) update math index.\n" );
					}
				}
			}
			if ( $this->textpattern ) {
				$textpattern = $this->textpattern;
				$search = SearchEngine::create( "CirrusSearch" );
				$search->setLimitOffset( 10000 );
				$sres = $search->searchText( $textpattern );
				if ( $sres ) {
					if ( !$sres->numRows() ) {
						$out->addWikiText( 'No results found.' );
					} else {
						$out->addWikiText( "You searched for the text '$textpattern' and the TeX-Pattern '{$this->mathpattern}'." );
						$out->addWikiText( "The text search results in [{{canonicalurl:search|search=$textpattern}} " .
							$sres->getTotalHits()
							. "] hits and the math pattern matched {$this->mathBackend->getSize()} times on [{{canonicalurl:{{FULLPAGENAMEE}}|pattern={$this->mathpattern}}} " .
							sizeof( $this->mathBackend->getRelevanceMap() ) .
							"] pages." );
						wfDebugLog( 'mathsearch', 'BOF' );
						$pageList = "";
						while ( $tres = $sres->next() ) {
							$revisionID = $tres->getTitle()->getLatestRevID();
							$rMap = $this->mathBackend->getRelevanceMap();
							if ( isset( $rMap[ $revisionID ] ) ) {
								$out->addWikiText( "[[" . $tres->getTitle() . "]]" );
								$out->addHtml( $tres->getTextSnippet( $textpattern ) );
								$pageList .= "OR [[" . $revisionID . "]]";
								// $out->addHtml($this->showHit($tres),$textpattern);
								$this->DisplayMath( $revisionID );
							} /* else {
					  $out->addWikiText(":NO MATH");
					  }// */
						} // $tres->mHighlightTitle)}

						wfDebugLog( 'mathsearch', 'EOF' );
						wfDebugLog( 'mathsearch', var_export( $this->mathResults, true ) );
					}
				}
			}
			$out->addWikiText( "<math> $this->mathpattern </math>" );
			$dbr = wfGetDB( DB_SLAVE );
			$inputhash = $dbr->encodeBlob( pack( 'H32', md5( $this->mathpattern ) ) );
			$rpage = $dbr->select(
				'mathindex', array( 'mathindex_revision_id', 'mathindex_anchor', 'mathindex_timestamp' ), array( 'mathindex_inputhash' => $inputhash )
			);
			foreach ( $rpage as $row )
				wfDebugLog( "MathSearch", var_export( $row, true ) );
		}

		/**
		 *
		 * @param String $src
		 * @param String $lang the language of the source snippet
		 */
		private function printSource( $src, $lang = "xml" )
		{
			$out = $this->getOutput();
			$out->addWikiText( '<source lang="' . $lang . '">' . $src . '</source>' );
		}

		/**
		 * Displays the equations for one page
		 *
		 * @param int $revisionID
		 *
		 * @return boolean
		 */
		function DisplayMath( $revisionID ) {
			global $wgMathDebug;
			$out = $this->getOutput();
			$results = $this->mathBackend->getResultSet();
			$page = $results[ (string)$revisionID ];
			$revision = Revision::newFromId( $revisionID );
			if ( $revision === false ) {
				wfDebugLog( "MathSearch", "invalid revision number" );
				return false;
			}
			$pagename = (string)$revision->getTitle();
			wfDebugLog( "MathSearch", "Processing results for $pagename" );
			foreach ( $page as $anchorID => $answ ) {
				$res = MathObject::constructformpage( $revisionID, $anchorID );
				if( $res ){
					$mml = $res->getMathml();
					$out->addWikiText( "====[[$pagename#$anchorID|Eq: $anchorID (Result " . $this->resultID++ . ")]]====", false );
					$out->addHtml( "<br />" );
					$xpath = $answ[ 0 ][ 'xpath' ];
					// TODO: Remove hack and report to Prode that he fixes that
					// $xmml->registerXPathNamespace('m', 'http://www.w3.org/1998/Math/MathML');
					$xpath = str_replace( '/m:semantics/m:annotation-xml[@encoding="MathML-Content"]', '', $xpath );
					$dom = new DOMDocument;
					$dom->loadXML( $mml );
					$DOMx = new DOMXpath( $dom );
					$hits = $DOMx->query( $xpath );
					if ( $wgMathDebug ) {
						wfDebugLog( 'MathSearch', "xPATH:" . $xpath );
					}
					if ( !is_null( $hits ) && $hits ) {
						foreach ( $hits as $node ) {
							/* @var DOMDocument $node */
							if ( $node->hasAttributes() ) {
								try {
									$domRes = $dom->getElementById( $node->attributes->getNamedItem( 'xref' )->nodeValue );
								} catch (Exception $e ){
									wfDebugLog( 'MathSearch', 'Problem getting references ' . $e->getMessage() );
									$domRes = false;
								}
								if ( $domRes ) {
									$domRes->setAttribute( 'mathcolor', '#cc0000' );
									$out->addHtml( $domRes->ownerDocument->saveXML() );
								} else {
									$out->addHTML( $node->ownerDocument->saveXML() );
								}
							} else {
								$renderer = new MathMathML();
								$renderer->setMathml( $mml );
								$out->addHtml( $renderer->getHtmlOutput() );
							}
						}
					} else {
						$renderer = new MathMathML();#
						$renderer->setMathml( $mml );
						$out->addHtml( $renderer->getHtmlOutput() );
					}
				} else {
					wfDebugLog( "MathSearch",
						"Failure: Could not get entry $anchorID for page $pagename (id $revisionID) :" .
						var_export( $this->mathResults, TRUE ) );
				}
			}
			return true;
		}

		/**
		 * Renders the math search input to mathml
		 * @return boolean
		 */
		function render()
		{
			$renderer = new MathLaTeXML( $this->mathpattern );
			$renderer->setLaTeXMLSettings( 'profile=mwsquery' );
			$renderer->setAllowedRootElments( array( 'query' ) );
			$renderer->render( true );
			$this->mathmlquery = $renderer->getMathml();
			if ( strlen( $this->mathmlquery ) == 0 ) {
				return false;
			} else {
				return true;
			}
		}
	}
