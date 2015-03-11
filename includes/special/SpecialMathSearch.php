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
		private $noTerms = 1;
		private $terms = array();
		private $relevanceMap;


		public static function exception_error_handler( $errno, $errstr, $errfile, $errline ) {
			if ( !( error_reporting() & $errno ) ) {
				// This error code is not included in error_reporting
				return;
			}
			throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
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
		 * @return bool
		 */
		public function processInput( $formData ) {
			if ( $formData['noTerms'] != $this->noTerms ) {
				$this->noTerms = $formData['noTerms'];
				$this->searchForm();
				return true;
			}

			for ( $i = 1; $i <= $this->noTerms; $i ++ ) {
				$this->addTerm( $i, $formData["rel-$i"], $formData["type-$i"],
					$formData["expr-$i"] );
			}

			$this->mathEngine = $formData['mathEngine'];
			$this->displayQuery = $formData['displayQuery'];
			$this->performSearch();
		}

		/**
		 * The main function
		 */
		public function execute( $par ) {
			set_error_handler( "SpecialMathSearch::exception_error_handler" );
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
			if ( file_exists( __DIR__ . self::GUI_PATH ) ) {
				$minurl = $wgExtensionAssetsPath . '/MathSearch' . self::GUI_PATH;
				$this->getOutput()
					->addHTML( "<p><a href=\"${minurl}\">Test experimental math input interface</a></p>" );
			}
			if ( $this->mathpattern || $this->textpattern ) {
				$this->performSearch();
			}
			restore_error_handler();
		}

		/**
		 * Generates the search input form
		 */
		private function searchForm() {
			# A formDescriptor Array to tell HTMLForm what to build
			$formDescriptor = array(
				'mathEngine' => array(
					'label' => 'Math engine',
					'class' => 'HTMLSelectField',
					'options' => array(
						'MathWebSearch' => 'mws',
						'BaseX' => 'basex'
					),
					'default' => $this->mathEngine,
				),
				'displayQuery' => array(
					'label' => 'Display search query',
					'type' => 'check',
					'default' => $this->displayQuery,
				),
				'noTerms' => array(
					'label' => 'Number of search terms',
					'type' => 'int',
					'min' => 1,
					'default' => 1,
				),
			);
			$formDescriptor =
				array_merge( $formDescriptor, $this->getSearchRows( $this->noTerms ) );
			$htmlForm =
				new HTMLForm( $formDescriptor, $this->getContext() ); # We build the HTMLForm object
			$htmlForm->setSubmitText( 'Search' );
			$htmlForm->setSubmitCallback( array( $this, 'processInput' ) );
			$htmlForm->setHeaderText( "<h2>Input</h2>" );
			$htmlForm->show(); # Displaying the form
		}


		private function getSearchRows( $cnt ) {
			$out = array();
			for ( $i = 1; $i <= $cnt; $i ++ ) {
				if ( $i == 1 ) {
					// Hide the meaningless first relation from the user
					$relType = 'hidden';
				} else {
					$relType = 'select';
				}
				$out["rel-$i"] = array(
					'label-message' => 'math-search-relation-label',
					'options' => array(
						wfMessage('math-search-relation-0')->text() => 0,
						wfMessage('math-search-relation-1')->text() => 1,
						wfMessage('math-search-relation-2')->text() => 2//,
						//'nor' => 3
					),
					'type' => $relType,
					'section' => "term $i" //TODO: figure out how to localize section with parameter
				);
				$out["type-$i"] = array(
					'label-message' => 'math-search-type-label',
					'options' => array(
						wfMessage('math-search-type-0')->text() => 0,
						wfMessage('math-search-type-1')->text() => 1,
						wfMessage('math-search-type-2')->text() => 2
					),
					'type' => 'select',
					'section' => "term $i",
				);
				$out["expr-$i"] = array(
					'label-message' => 'math-search-expression-label',
					'type' => 'text',
					'section' => "term $i"
				);
			}
			return $out;
		}

		public function performSearch() {
			$out = $this->getOutput();
			$time_start = microtime( true );
			$out->addWikiText( '==Results==' );
			$out->addWikiText( 'You searched for the following terms:' );
			switch ( $this->mathEngine ) {
				case 'basex':
					$this->mathBackend = new MathEngineBaseX( null );
					break;
				default:
					$this->mathBackend = new MathEngineMws( null );
			}
			/** @var MathSearchTerm $term */
			foreach ( $this->terms as $term ) {
				$term->doSearch( $this->mathBackend );
				$this->printTerm( $term );
				if ( $term->getKey() == 1 ) {
					$this->relevanceMap = $term->getRelevanceMap();

				} else {
					switch ( $term->getRel() ) {
						case $term::REL_AND:
							$this->relevanceMap =
								array_intersect( $this->relevanceMap, $term->getRelevanceMap() );
//							$this->getOutput()->addWikiText("Intersected with {$term->getKey()}");
//							$this->getOutput()->addWikiText("In total".sizeof($term->getRelevanceMap()));
							break;
						case $term::REL_OR:
							$this->relevanceMap = $this->relevanceMap + $term->getRelevanceMap();
							break;
						case $term::REL_NAND:
							$this->relevanceMap =
								array_diff( $this->relevanceMap, $term->getRelevanceMap() );
						//case $term::REL_NOR: (too many results)
					}
				}
			}
			foreach( $this->relevanceMap as $revisionID ){
				$this->displayRevisionResults($revisionID);
			}
//			$this->getOutput()->addWikiText("In total".sizeof($this->relevanceMap,true));
//			$this->getOutput()->addWikiText("Map2".var_export($this->relevanceMap,true));

		}

		/**
		 * @param $revisionID
		 * @param $mathElements
		 * @param $out
		 * @param $pagename
		 */
		public function displayMathElements( $revisionID, $mathElements, $out, $pagename ) {
			global $wgMathDebug;
			foreach ( $mathElements as $anchorID => $answ ) {
				$res = MathObject::constructformpage( $revisionID, $anchorID );
				if ( $res ) {
					$mml = $res->getMathml();
					$out->addWikiText( "====[[$pagename#$anchorID|Eq: $anchorID (Result " .
									   $this->resultID ++ . ")]]====", false );
					$out->addHtml( "<br />" );
					$xpath = $answ[0]['xpath'];
					// TODO: Remove hack and report to Prode that he fixes that
					// $xmml->registerXPathNamespace('m', 'http://www.w3.org/1998/Math/MathML');
					$xpath =
						str_replace( '/m:semantics/m:annotation-xml[@encoding="MathML-Content"]',
							'', $xpath );
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
									$domRes =
										$dom->getElementById( $node->attributes->getNamedItem( 'xref' )->nodeValue );
								}
								catch ( Exception $e ) {
									wfDebugLog( 'MathSearch',
										'Problem getting references ' . $e->getMessage() );
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
		}

		/**
		 * @param MathSearchTerm $term
		 */
		public function printTerm( $term ) {
			$this->getOutput()->addWikiMsg( 'math-search-term',
				$term->getKey(),
				$term->getExpr(),
				wfMessage( "math-search-type-{$term->getType()}")->text(),
				$term->getRel() == '' ? '' : wfMessage( "math-search-relation-{$term->getRel()}")->text(),
				sizeof( $term->getRelevanceMap() ) );
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
		function displayRevisionResults( $revisionID ) {
			$out = $this->getOutput();
			$revision = Revision::newFromId( $revisionID );
			if ( $revision === false ) {
				wfDebugLog( "MathSearch", "invalid revision number" );
				return false;
			}
			$pagename = (string)$revision->getTitle();
			$mathElements = array();
			$textElements = array();
			/** @var MathSearchTerm $term */
			foreach($this->terms as $term ){
				if ( $term->getType() == MathSearchTerm::TYPE_MATH ){
					$mathElements += $term->getRevisionResult( $revisionID );
				} elseif ( $term->getType() == MathSearchTerm::TYPE_TEXT ) { //Forward compatible
					/** @var SearchResult $textResult */
					$textResult = $term->getRevisionResult( $revisionID );
					//see: T90976
					$textElements[]= $textResult->getTextSnippet( array( $term->getExpr() ) );
					//$textElements[]=$textResult->getSectionSnippet();
				}
			}
			$out->addWikiText( "=== [[Special:Permalink/$revisionID | $pagename]] ===" );
			foreach( $textElements as $textResult ){
				$out->addWikiText( $textResult );
			}
			wfDebugLog( "MathSearch", "Processing results for $pagename" );
			$this->displayMathElements( $revisionID, $mathElements, $out, $pagename );
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

		/**
		 * @param int $i
		 * @param int $rel
		 * @param int $type
		 * @param string $expr
		 */
		private function addTerm( $i, $rel, $type, $expr ) {
			$this->terms[ $i ]= new MathSearchTerm($i, $rel, $type, $expr );
		}

	}