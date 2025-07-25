<?php

use MediaWiki\Extension\Math\MathLaTeXML;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Extension\MathSearch\Engine\BaseX;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2012 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @file
 * @ingroup extensions
 */
class SpecialMathSearch extends SpecialPage {

	private const GUI_PATH = '/modules/min/index.xhtml';

	/** @var string */
	private $mathpattern;
	/** @var string */
	private $textpattern;
	/** @var string */
	private $mathmlquery;
	/** @var string */
	private $displayQuery;
	private BaseX $mathBackend;
	/** @var int */
	private $resultID = 0;
	/** @var int|string */
	private $noTerms = 1;
	/** @var array<int,MathSearchTerm> */
	private $terms = [];
	/** @var int[] */
	private $relevanceMap;
	/** @var array<int,array<string,int|string>> */
	private $defaults;

	public static function exception_error_handler( $errno, $errstr, $errfile, $errline ) {
		if ( !( error_reporting() & $errno ) ) {
			// This error code is not included in error_reporting
			return;
		}
		throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
	}

	public function __construct() {
		parent::__construct( 'MathSearch' );
	}

	/**
	 * The main function
	 * @param string|null $par
	 */
	public function execute( $par ) {
		set_error_handler( "SpecialMathSearch::exception_error_handler" );
		$request = $this->getRequest();
		$this->setHeaders();
		$this->mathpattern = $request->getText( 'mathpattern', '' );
		$this->textpattern = $request->getText( 'textpattern', '' );
		$this->noTerms = $request->getText( 'wpnoTerms', $this->noTerms );
		$isEncoded = $request->getBool( 'isEncoded', false );
		if ( $isEncoded ) {
			$this->mathpattern = htmlspecialchars_decode( $this->mathpattern );
		}
		if ( $this->mathpattern || $this->textpattern ) {
			$i = 0;
			if ( $this->mathpattern ) {
				$i++;
				$this->addFormData( $this->mathpattern, $i,
					MathSearchTerm::TYPE_MATH, MathSearchTerm::REL_AND );
			}
			if ( $this->textpattern ) {
				$i++;
				$this->addFormData( $this->textpattern, $i,
					MathSearchTerm::TYPE_TEXT, MathSearchTerm::REL_AND );
			}
			$this->noTerms = $i;
			$form = $this->searchForm();
			$form->prepareForm();
			$res = $form->trySubmit();
			$this->getOutput()->addHTML( $form->getHTML( $res ) );
			// $this->performSearch();
			// $this->performSearch();
		} else {
			$this->searchForm()->show();
			if ( file_exists( __DIR__ . self::GUI_PATH ) ) {
				$minurl = $this->getConfig()->get( 'ExtensionAssetsPath' ) . '/MathSearch' . self::GUI_PATH;
				$this->getOutput()
					->addHTML( "<p><a href=\"{$minurl}\">Test experimental math input interface</a></p>" );
			}
		}
		restore_error_handler();
	}

	/**
	 * Generates the search input form
	 *
	 * @return HTMLForm
	 */
	private function searchForm() {
		# A formDescriptor Array to tell HTMLForm what to build
		$formDescriptor = [
			'displayQuery' => [
				'label' => 'Display search query',
				'type' => 'check',
				'default' => $this->displayQuery,
			],
			'noTerms' => [
				'label' => 'Number of search terms',
				'type' => 'int',
				'min' => 1,
				'default' => $this->noTerms,
			],
		];
		$formDescriptor = array_merge( $formDescriptor, $this->getSearchRows( $this->noTerms ) );
		$htmlForm =	new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Search' );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
		$htmlForm->setHeaderHtml( "<h2>Input</h2>" );
		// $htmlForm->show();
		return $htmlForm;
	}

	private function getSearchRows( int $cnt ): array {
		$out = [];
		for ( $i = 1; $i <= $cnt; $i++ ) {
			if ( $i == 1 ) {
				// Hide the meaningless first relation from the user
				$relType = 'hidden';
			} else {
				$relType = 'select';
			}
			$out["rel-$i"] = [
				'label-message' => 'math-search-relation-label',
				'options' => [
					$this->msg( 'math-search-relation-0' )->text() => 0,
					$this->msg( 'math-search-relation-1' )->text() => 1,
					$this->msg( 'math-search-relation-2' )->text() => 2 // ,
					// 'nor' => 3
				],
				'type' => $relType,
				'default' => $this->getDefault( $i, 'rel' ),
				'section' => "term $i" // TODO: figure out how to localize section with parameter
			];
			$out["type-$i"] = [
				'label-message' => 'math-search-type-label',
				'options' => [
					$this->msg( 'math-search-type-0' )->text() => 0,
					$this->msg( 'math-search-type-1' )->text() => 1,
					$this->msg( 'math-search-type-2' )->text() => 2
				],
				'type' => 'select',
				'section' => "term $i",
				'default' => $this->getDefault( $i, 'type' )
			];
			$out["expr-$i"] = [
				'label-message' => 'math-search-expression-label',
				'type' => 'text',
				'section' => "term $i",
				'default' => $this->getDefault( $i, 'expr' )
			];
		}
		return $out;
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

		for ( $i = 1; $i <= $this->noTerms; $i++ ) {
			$this->addTerm( $i, $formData["rel-$i"], $formData["type-$i"],
				$formData["expr-$i"] );
		}

		$this->displayQuery = $formData['displayQuery'];
		$this->performSearch();
	}

	public function performSearch() {
		$out = $this->getOutput();
		$out->addWikiTextAsInterface( '==Results==' );
		$out->addWikiTextAsInterface( 'You searched for the following terms:' );
		$this->mathBackend = new BaseX( null );
		/** @var MathSearchTerm $term */
		foreach ( $this->terms as $term ) {
			if ( $term->getExpr() == "" ) {
				continue;
			}
			$term->doSearch( $this->mathBackend );
			$this->enableMathStyles();
			$this->printTerm( $term );
			if ( $term->getKey() == 1 ) {
				$this->relevanceMap = $term->getRelevanceMap();

			} else {
				switch ( $term->getRel() ) {
					case $term::REL_AND:
						$this->relevanceMap =
							array_intersect( $this->relevanceMap, $term->getRelevanceMap() );
						break;
					case $term::REL_OR:
						$this->relevanceMap = $this->relevanceMap + $term->getRelevanceMap();
						break;
					case $term::REL_NAND:
						$this->relevanceMap =
							array_diff( $this->relevanceMap, $term->getRelevanceMap() );
					// case $term::REL_NOR: (too many results)
				}
			}
		}
		$formulaCount = 0;
		if ( $this->relevanceMap != null ) {
			$formulaCount = count( $this->relevanceMap, true );
			foreach ( $this->relevanceMap as $revisionID ) {
				$this->displayRevisionResults( $revisionID );
			}
		}
		$this->getOutput()->addWikiTextAsInterface( "In total " . $formulaCount . ' results.' );
	}

	/**
	 * @param int $revisionID
	 * @param array[] $mathElements
	 * @param string $pagename
	 */
	public function displayMathElements( $revisionID, $mathElements, $pagename ) {
		$out = $this->getOutput();
		foreach ( $mathElements as $anchorID => $answ ) {
			$res = MathObject::constructformpage( $revisionID, $anchorID );
			if ( !$res ) {
				LoggerFactory::getInstance(
					'MathSearch'
				)->error( "Failure: Could not get entry $anchorID for page $pagename (id $revisionID)" );
				return;
			}
			$mml = $res->getMathml();
			if ( !$mml ) {
				LoggerFactory::getInstance( 'MathSearch' )
					->error( "Failure: Could not get MathML $anchorID for page $pagename (id $revisionID)" );
				continue;
			}
			$out->addWikiTextAsInterface( "====[[$pagename#$anchorID|Eq: $anchorID (Result " .
				$this->resultID++ . ")]]====", false );
			$out->addHTML( "<br />" );
			$xpath = $answ[0]['xpath'];
			// TODO: Remove hack and report to Prode that he fixes that
			// $xmml->registerXPathNamespace('m', 'http://www.w3.org/1998/Math/MathML');
			$xpath =
				str_replace( '/m:semantics/m:annotation-xml[@encoding="MathML-Content"]',
					'', $xpath );
			$dom = new DOMDocument;
			$dom->preserveWhiteSpace = false;
			$dom->validateOnParse = true;
			$dom->loadXML( $mml );
			$DOMx = new DOMXpath( $dom );
			$hits = $DOMx->query( $xpath );
			if ( $this->getConfig()->get( 'MathDebug' ) ) {
				LoggerFactory::getInstance( 'MathSearch' )->debug( 'xPATH:' . $xpath );
			}
			if ( $hits !== null && $hits ) {
				foreach ( $hits as $node ) {
					$this->highlightHit( $node, $dom, $mml );
				}
			}
			if ( $mml != $res->getMathml() ) {
				$renderer = new MathMathML( $mml, [ 'type' => 'pmml' ] );
				$renderer->setMathml( $mml );
				$renderer->render();
				$out->addHTML( $renderer->getHtmlOutput() );
				$renderer->writeCache();
			} else {
				$res->render();
				$out->addHTML( $res->getHtmlOutput() );
			}

		}
	}

	/**
	 * Note that the default getElementById function
	 * <code>
	 *  $dom->getElementById( $id );
	 * </code>
	 * works for "xml:id" only,  but not for "id" which is extended to "math:id"
	 * 	TODO: could be fixed with
	 * @link http://php.net/manual/de/domdocument.getelementbyid.php#86056
	 * @param string $id
	 * @param DOMDocument $doc
	 * @return DOMElement|null
	 */
	private function getElementById( $id, $doc ) {
		$xpath = new DOMXPath( $doc );
		return $xpath->query( "//*[@id='$id']" )->item( 0 );
	}

	/**
	 * @param MathSearchTerm $term
	 */
	public function printTerm( $term ) {
		if ( $term->getType() == MathSearchTerm::TYPE_MATH ) {
			$expr = "<math>{$term->getExpr()}</math>";
		} else {
			$expr = "<code>{$term->getExpr()}</code>";
		}
		$this->getOutput()->addWikiMsg( 'math-search-term',
			$term->getKey(),
			$expr,
			$this->msg( "math-search-type-{$term->getType()}" )->text(),
			$term->getRel() == '' ? '' : $this->msg( "math-search-relation-{$term->getRel()}" )->text(),
			count( $term->getRelevanceMap() ) );
	}

	/**
	 * @param DOMNode $node
	 * @param DOMDocument $dom
	 * @param string &$mml
	 */
	protected function highlightHit( $node, $dom, &$mml ) {
		if ( $node == null || !$node->hasAttributes() ) {
			return;
		}
		try {
			$xRef = $node->attributes->getNamedItem( 'xref' );
			if ( $xRef ) {
				$domRes = $this->getElementById( $xRef->nodeValue, $dom );
				if ( $domRes ) {
					$domRes->setAttribute( 'mathcolor', '#cc0000' );
					$mml = $domRes->ownerDocument->saveXML();
				}
			} else {
				// CMML node has no corresponding PMML element
				$fallback = $node->parentNode;
				$this->highlightHit( $fallback, $dom, $mml );
			}
		} catch ( Exception $e ) {
			LoggerFactory::getInstance(
				'MathSearch'
			)->error( 'Problem highlighting hit ' . $e->getMessage() );
		}
	}

	/**
	 * @param string $src
	 * @param string $lang the language of the source snippet
	 */
	private function printSource( $src, $lang = "xml" ) {
		$out = $this->getOutput();
		$out->addWikiTextAsInterface( '<source lang="' . $lang . '">' . $src . '</source>' );
	}

	/**
	 * Displays the equations for one page
	 *
	 * @param int $revisionID
	 *
	 * @return bool
	 */
	private function displayRevisionResults( $revisionID ) {
		$out = $this->getOutput();
		$revisionStoreRecord = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $revisionID );
		if ( !$revisionStoreRecord ) {
			LoggerFactory::getInstance( 'MathSearch' )->error( 'invalid revision number' );
			return false;
		}
		$title = $revisionStoreRecord->getPageAsLinkTarget(); # MCR migration note: this replaced Revision::getTitle
		$pagename = (string)$title;
		$mathElements = [];
		$textElements = [];
		/** @var MathSearchTerm $term */
		foreach ( $this->terms as $term ) {
			if ( $term->getExpr() == "" ) {
				continue;
			}
			if ( $term->getType() == MathSearchTerm::TYPE_MATH ) {
				$mathElements += $term->getRevisionResult( $revisionID );
			} elseif ( $term->getType() == MathSearchTerm::TYPE_TEXT ) { // Forward compatible
				/** @var SearchResult $textResult */
				$textResult = $term->getRevisionResult( $revisionID );
				// see: T90976
				$textElements[] = $textResult->getTextSnippet( [ $term->getExpr() ] );
				// $textElements[]=$textResult->getSectionSnippet();
			}
		}
		$out->addWikiTextAsInterface( "=== [[Special:Permalink/$revisionID | $pagename]] ===" );
		foreach ( $textElements as $textResult ) {
			$out->addWikiTextAsInterface( $textResult );
		}
		LoggerFactory::getInstance( 'MathSearch' )->warning( "Processing results for $pagename" );
		$this->displayMathElements( $revisionID, $mathElements, $pagename );
		return true;
	}

	/**
	 * Renders the math search input to mathml
	 * @return bool
	 */
	private function render() {
		$renderer = new MathLaTeXML( $this->mathpattern );
		$renderer->setLaTeXMLSettings( 'profile=mwsquery' );
		$renderer->setAllowedRootElements( [ 'query' ] );
		$renderer->render( true );
		$this->mathmlquery = $renderer->getMathml();

		return $this->mathmlquery !== '';
	}

	/**
	 * @param int $i
	 * @param int $rel
	 * @param int $type
	 * @param string $expr
	 */
	private function addTerm( $i, $rel, $type, $expr ) {
		$this->terms[ $i ] = new MathSearchTerm( $i, $rel, $type, $expr );
	}

	private function enableMathStyles() {
		$out = $this->getOutput();
		$out->addModuleStyles(
			[ 'ext.math.styles', 'ext.math.desktop.styles', 'ext.math.scripts' ]
		);
	}

	private function addFormData( string $mathpattern, int $i, int $TYPE_MATH, int $REL_AND ) {
		$this->defaults[$i]['type'] = $TYPE_MATH;
		$this->defaults[$i]['rel'] = $REL_AND;
		$this->defaults[$i]['expr'] = $mathpattern;
	}

	/**
	 * @param int $i
	 * @param string $what
	 * @return int|string
	 */
	private function getDefault( $i, $what ) {
		if ( isset( $this->defaults[$i][$what] ) ) {
			return $this->defaults[$i][$what];
		} else {
			switch ( $what ) {
				case 'expr':
					return '';
				case 'type':
					return MathSearchTerm::TYPE_MATH;
				case 'rel':
					return MathSearchTerm::REL_AND;
			}
			return "";
		}
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}
