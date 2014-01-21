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

	var $qs;
	var $math_result;
	var $mathSearchExpr;
	var $relevantMathMap;
	var $numMathResults;
	var $numTextResults;
	var $mathResults;
	var $mathpattern;
	var $textpattern;
	var $mathmlquery;
	private $resultID = 0;

	/**
	 *
	 */
	function __construct() {
		parent::__construct( 'MathSearch' );
	}

	/**
	 *
	 * @return \CirrusSearch|boolean
	 */
	public static function getCirrusSearch() {
		if ( class_exists( 'CirrusSearch' ) ) {
			return new CirrusSearch();
		} else {
			wfDebugLog( 'MathSearch', 'Text search not possible. Class CirrusSearch is missing.' );
			return false;
		}
	}

	/**
	 * The main function
	 */
	public function execute( $par ) {
		$request = $this->getRequest();
		$this->setHeaders();
		$this->mathpattern = $request->getText( 'mathpattern' );
		$this->textpattern = $request->getText( 'textpattern', '' );
		$isEncoded = $request->getBool( 'isEncoded', false );
		if ( $isEncoded ) {
			$this->mathpattern = htmlspecialchars_decode( $this->mathpattern );
		}
		$this->searchForm();
		if ( $this->mathpattern || $this->textpattern ) {
			$this->performSearch();
		}
	}

		/**
	 * Generates the search input form
	 */
	private function searchForm() {
		# A formDescriptor Array to tell HTMLForm what to build
		$formDescriptor = array(
			'mathpattern' => array(
				'label' => 'LaTeX pattern', # What's the label of the field
				'class' => 'HTMLTextField', # What's the input type
				'help' => 'for example: \sin(?x^2)',
				'default' => $this->mathpattern,
			),
			'textpattern' => array(
				'label' => 'Text pattern', # What's the label of the field
				'class' => 'HTMLTextField', # What's the input type
				'help' => 'a term like: algebra',
				'default' => $this->textpattern,
			)
		);
		if ( !self::getCirrusSearch() ) {
			$formDescriptor['textpattern']['disabled'] = true;
			$formDescriptor['textpattern']['help'] = 'CirrusSearch not found. Text search <b>disabled</b>!<br/> For details see <a href=\"http://www.mediawiki.org/wiki/Extension:MWSearch\">MWSearch</a>.';
		}
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() ); # We build the HTMLForm object
		$htmlForm->setSubmitText( 'Search' );
		$htmlForm->setSubmitCallback( array( get_class( $this ), 'processInput' ) );
		$htmlForm->setHeaderText( "<h2>Input</h2>" );
		$htmlForm->show(); # Displaying the form
	}

		/**
	 * Processes the submitted Form input
	 * @param array $formData
	 */
	public static function processInput( $formData ) {
		$instance = new SpecialMathSearch();
		$instance->mathpattern = $formData['mathpattern'];
		$instance->textpattern = $formData['textpattern'];
		$instance->performSearch();
	}

	/**
	 * Displays the equations for one page
	 * @param unknown $pageID
	 * @return boolean
	 */
	function DisplayMath( $pageID ) {
		global $wgMathDebug;
		$out = $this->getOutput();
		$page = $this->mathResults[(string) $pageID];
		$dbr = wfGetDB( DB_SLAVE );
		$article = Article::newFromId( $pageID );
		$pagename = (string) $article->getTitle();
		wfDebugLog( "MathSearch", "Processing results for $pagename" );
		foreach ( $page as $anchorID => $answ ) {
			$res = $dbr->selectRow(
					array( 'mathindex', 'math' ), array( 'math_mathml', 'mathindex_page_id', 'mathindex_anchor',
				'mathindex_inputhash', 'math_inputhash' ), 'mathindex_page_id = "' . $pageID
					. '" AND mathindex_anchor= "' . $anchorID
					. '" AND mathindex_inputhash = math_inputhash'
			);
			if ( $res ) {
				$mml = utf8_decode( $res->math_mathml );
				$out->addWikiText( "====[[$pagename#math$anchorID|Eq: $anchorID (Result " . $this->resultID++ . ")]]====", false );
				// $out->addHtml(MathLaTeXML::embedMathML($mml));
				$out->addHtml( "<br />" );
				$xpath = $answ[0]['xpath'];
				$xmml = new SimpleXMLElement( $res->math_mathml );
				// TODO: Remove hack and report to Prode that he fixes that
				// $xmml->registerXPathNamespace('m', 'http://www.w3.org/1998/Math/MathML');
				$xpath = str_replace( '/m:semantics/m:annotation-xml[@encoding="MathML-Content"]', '', $xpath );
				if ( !$wgMathDebug ) {
					$out->addWikiText( "xPATH:" . $xpath );
					$out->addWikiText( 'MATHML:<source lang="xml">' . $xmml->asXML() . '</source>' );
				}
				$hit = $xmml->xpath( $xpath );
				while ( list( , $node ) = each( $hit ) ) {
					// $out->addHtml(var_export($node["xref"][0],true));
					$dom = new DOMDocument;
					$dom->loadXML( $mml );
					$domRes = $dom->getElementById( $node["xref"][0] );
					if ( $domRes ) {
						$domRes->setAttribute( 'mathcolor', '#cc0000' );
						$out->addHtml( $domRes->ownerDocument->saveXML() );
					} else {
						$renderer = new MathMathML();
						$renderer->setMathml( $mml );
						$out->addHtml( $renderer->getHtmlOutput() );
					}
				}


				wfDebugLog( "MathSearch", "PositionInfo:" . var_export( $this->mathResults[$pageID][$anchorID], true ) );
			}
			else
				wfDebugLog( "MathSearch", "Failure: Could not get entry $anchorID for page $pagename (id $pageID) :" . var_export( $this->mathResults, true ) );
		}
		// var_dump($answ);
// 		$xansw=new SimpleXMLElement($answ);
// 		 foreach($xansw->children("mws",TRUE) as $substpair){
// 		$substattrs=$substpair->attributes();
// 		$substarr[]=array("qvar"=>(string) $substattrs["qvar"], "xpath"=>(string) $substattrs["xpath"]);
// 		}/// */
		// $this->mathResults[$pname][$eqAnchor][]=array("xpath"=>(string) $attrs["xpath"],"mappings"=>$substarr);
		// foreach($page as $anchor=>$eq){//$out.=var_export($eq, true);	}
		return true;
	}


	public function performSearch() {
		global $wgMathDebug;
		$out = $this->getOutput();
		$time_start = microtime( true );
		$out->addWikiText( '==Results==' );
		$out->addWikiText( 'You serached for the LaTeX pattern "' . $this->mathpattern . '" and the text pattern "' . $this->textpattern . '".' );
		if ( $this->mathpattern ) {
			if ( $this->render() ) {
				$out->addWikiText( "Your mathpattern was suceessfully rendered!" );
				if ( $wgMathDebug ) {
					$out->addWikiText( " <source lang=\"xml\">" . $this->mathmlquery . "</source>" );
				}
				if ( $this->postQuery() ) {
					$out->addWikiText( "Your mathquery was sucessfully submitted and " . $this->numMathResults . " hits were obtained." );
				} else {
					$out->addWikiText( "Failed to post query." );
				}
				$time_end = microtime( true );
				$time = $time_end - $time_start;
				wfDebugLog( "MathSearch", "math searched in $time seconds" );
			} else {
				$out->addWikiText( "Your query could not be renderded see the DebugLog for details." );
			}
			// $out->addHTML(var_export($this->mathResults, true));
		} else {
			$out->addWikiText( 'The math-pattern is empty. No math search has been performed.' );
			$out->addWikiText( "To view the text results click [{{canonicalurl:search|search=$this->textpattern}} Text-only search]." );
		}

		if ( $this->textpattern == "" ) {
			$mathout = "";
			if ( $this->mathResults ) {
				foreach ( $this->mathResults as $pageID => $page ) {
					$article = Article::newFromId( $pageID );
					if ( $article ) {
						$pagename = (string) $article->getTitle();
						$out->addWikiText( "==[[$pagename]]==" );
						$this->DisplayMath( $pageID );
					}
					else
						$out->addWikiText( "Error with Page (ID=$pageID) update math index.\n" );
				}
			}
		}
		$ls = self::getCirrusSearch();
		if ( $ls ) {
			$ls->limit = 1000000;
			if ( $this->textpattern ) {
				$textpattern = $this->textpattern;
				$sres = $ls->searchText( $textpattern );
				if ( $sres && $sres->hasResults() ) {
					$out->addWikiText( "You searched for the text '$textpattern' and the TeX-Pattern '$pattern'." );
					$out->addWikiText( "The text search results in [{{canonicalurl:search|search=$textpattern}} " .
							$sres->getTotalHits()
							. "] hits and the math pattern matched $this->numMathResults times on [{{canonicalurl:{{FULLPAGENAMEE}}|pattern=$pattern}} " .
							sizeof( $this->relevantMathMap ) .
							"] pages." );
					//// var_dump($sres);
					wfDebugLog( 'mathsearch', 'BOF' );
					// $out->addWikiText(var_export($this->relevantMathMap,true));
					$pageList = "";
					while ( $tres = $sres->next() ) {
						$pageID = $tres->getTitle()->getArticleID();
						// $out->addWikiText($pageID);

						if ( isset( $this->relevantMathMap[$pageID] ) ) {
							$out->addWikiText( "[[" . $tres->getTitle() . "]]" );
							$out->addHtml( $tres->getTextSnippet( $textpattern ) );
							$pageList .= "OR [[" . $pageID . "]]";
							// $out->addHtml($this->showHit($tres),$textpattern);
							$this->DisplayMath( $pageID );
						} /* else {
						  $out->addWikiText(":NO MATH");
						  }// */
					} // $tres->mHighlightTitle)}

					wfDebugLog( 'mathsearch', 'EOF' );
					wfDebugLog( 'mathsearch', var_export( $this->mathResults, true ) );
				}
			}
		}
		// $out->addHtml(htmlspecialchars( $pattern) );
		$out->addWikiText( "<math> $this->mathpattern </math>" );
		// dbw = wfGetDB( DB_MASTER );$dbw->encodeBlob(pack( 'H32'
		// $inputhash= $dbw->encodeBlob(pack( 'H32',md5($pattern) );
		// $out->addWikiText("$inputhash");
		$dbr = wfGetDB( DB_SLAVE );
		$inputhash = $dbr->encodeBlob( pack( 'H32', md5( $this->mathpattern ) ) );
		$rpage = $dbr->select(
				'mathindex', array( 'mathindex_page_id', 'mathindex_anchor', 'mathindex_timestamp' ), array( 'mathindex_inputhash' => $inputhash )
		);
		foreach ( $rpage as $row )
			wfDebugLog( "MathSearch", var_export( $row, true ) );
	}


	/**
	 * Renders the math search input to mathml
	 * @return boolean
	 */
	function render() {
		$renderer = new MathLaTeXMLML( $this->mathpattern );
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
	 * Posts the query to mwsd and evaluates the result data
	 * @return boolean
	 */
	function postQuery() {
		global $wgMWSUrl, $wgMathDebug;

		$numProcess = 30000;
		$tmp = str_replace( "answsize=\"30\"", "answsize=\"$numProcess\" totalreq=\"yes\"", $this->mathmlquery );
		$mwsExpr = str_replace( "m:", "", $tmp );
		wfDebugLog( 'mathsearch', 'MWS query:' . $mwsExpr );
		$res = Http::post( $wgMWSUrl, array( "postData" => $mwsExpr, "timeout" => 60 ) );
		if ( $res == false ) {
			if ( function_exists( 'curl_init' ) ) {
				$handle = curl_init();
				$options = array(
					CURLOPT_URL => $wgMWSUrl,
					CURLOPT_CUSTOMREQUEST => 'POST', // GET POST PUT PATCH DELETE HEAD OPTIONS
				);
				// TODO: Figure out how not to write the error in a message and not in top of the output page
				curl_setopt_array( $handle, $options );
				$details = curl_exec( $handle );
			} else {
				$details = "curl is not installed.";
			}
			wfDebugLog( "MathSearch", "Nothing retreived from $wgMWSUrl. Check if mwsd is running. Error:" .
					var_export( $details, true ) );
			return false;
		}
		$xres = new SimpleXMLElement( $res );
		if ( $wgMathDebug ) {
			$out = $this->getOutput();
			$out->addWikiText( '<source lang="xml">' . $res . '</source>' );
		}
		$this->numMathResults = (int) $xres["total"];
		wfDebugLog( "MathSearch", $this->numMathResults . " results retreived from $wgMWSUrl." );
		if ( $this->numMathResults == 0 )
			return true;
		$this->relevantMathMap = array();
		$this->mathResults = array();
		$this->processMathResults( $xres );
		if ( $this->numMathResults >= $numProcess ) {
			ini_set( 'memory_limit', '256M' );
			for ( $i = $numProcess; $i <= $this->numMathResults; $i += $numProcess ) {
				$query = str_replace( "limitmin=\"0\" ", "limitmin=\"$i\" ", $mwsExpr );
				$res = Http::post( $wgMWSUrl, array( "postData" => $query, "timeout" => 60 ) );
				wfDebugLog( 'mathsearch', 'MWS query:' . $query );
				if ( $res == false ) {
					wfDebugLog( "MathSearch", "Nothing retreived from $wgMWSUrl. check if mwsd is running there" );
					return false;
				}
				$xres = new SimpleXMLElement( $res );
				$this->processMathResults( $xres );
			}
		}
		return true;
	}

	/**
	 * @param unknown $xmlRoot
	 */
	function processMathResults( $xmlRoot ) {
		foreach ( $xmlRoot->children( "mws", TRUE ) as $page ) {
			$attrs = $page->attributes();
			$uri = explode( "#", $attrs["uri"] );
			$pageID = $uri[0];
			$AnchorID = substr( $uri[1], 4 );
			$this->relevantMathMap[$pageID] = true;
			$substarr = array();
			// $this->mathResults[(string) $pageID][(string) $AnchorID][]=$page->asXML();
			foreach ( $page->children( "mws", TRUE ) as $substpair ) {
				$substattrs = $substpair->attributes();
				$substarr[] = array( "qvar" => (string) $substattrs["qvar"], "xpath" => (string) $substattrs["xpath"] );
			}
			$this->mathResults[(string) $pageID][(string) $AnchorID][] = array( "xpath" => (string) $attrs["xpath"], "mappings" => $substarr ); // ,"original"=>$page->asXML()
		}
	}

}
