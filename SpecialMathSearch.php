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

	/**
	 *
	 */
	function __construct() {
		parent::__construct( 'MathSearch' );
	}

	function getLucene() {
		if ( class_exists( "LuceneSearch" ) ) {
			return new LuceneSearch();
		} else {
			wfDebugLog( "MathSearch", "Text search not possible. Class LuceneSearch is missing." );
			return false;
		}
	}
	/**
	 * @param unknown $par
	 */
	function execute( $par ) {
		global $wgRequest, $wgOut;
		$text = "";
		$this->setHeaders();
		$param = $wgRequest->getText( 'param' );
		$text = $wgRequest->getVal( 'text' );
		$pattern = $wgRequest->getVal( 'pattern' );
		if ( $param ) {
			 $pattern = htmlspecialchars_decode( $param ); }
		$wgOut->addHTML( $this->searchForm( $pattern, $text ) );
		$time_start = microtime( true );
		if ( $pattern ) {
			$this->render( $pattern );
			$time_end = microtime( true );
			$time = $time_end - $time_start;
			wfDebugLog( "MathSearch", "math searched in $time seconds" );
			// $wgOut->addHTML(var_export($this->mathResults, true));
		}
		$text = $wgRequest->getVal( 'text' );

		if ( $text == "" ) {
			// $wgOut->addWikiText($this->searchResults($pattern));
			$mathout = "";
			// $wgOut->addWikiText(var_export($this->mathResults,true));
			if ( $this->mathResults )
				foreach ( $this->mathResults as $pageID => $page ) {
				$article = Article::newFromId( $pageID );
				if ( $article )
				{
					$pagename = (string)$article->getTitle();
					$wgOut->addWikiText( "[[$pagename]]:" );
					$this->DisplayMath( $pageID );
					// echo "success with pid:$pageID\n";
				}
				else
					echo "error with pid:$pageID update mathematical index\n";
			}
		} // *
		$ls = self::getLucene() ;
		if ( $ls ) {
		$ls->limit = 1000000;
		$sres = $ls->searchText( $text );
		if ( $sres ) {
			$wgOut->addWikiText( "You searched for the text '$text' and the TeX-Pattern '$pattern'." );
			$wgOut->addWikiText( "The text search results in [{{canonicalurl:search|search=$text}} " .
					$sres->getTotalHits()
					. "] hits and the math pattern matched $this->numMathResults times on [{{canonicalurl:{{FULLPAGENAMEE}}|pattern=$pattern}} " .
					sizeof( $this->relevantMathMap ) .
					"] pages." );
			//// var_dump($sres);
			wfDebugLog( 'mathsearch', 'BOF' );
			// $wgOut->addWikiText(var_export($this->relevantMathMap,true));
			$pageList = "";
			while ( $tres = $sres->next() ) {
				$pageID = $tres->getTitle()->getArticleID();
				// $wgOut->addWikiText($pageID);

				if ( isset( $this->relevantMathMap[$pageID] ) ) {
					$wgOut->addWikiText( "[[" . $tres->getTitle() . "]]" );
					$wgOut->addHtml( $tres->getTextSnippet( $text ) );
					$pageList .= "OR [[" . $pageID . "]]";
					// $wgOut->addHtml($this->showHit($tres),$text);
					$this->DisplayMath( $pageID );
				} /*else {
				$wgOut->addWikiText(":NO MATH");
				}//*/
			} // $tres->mHighlightTitle)}

			wfDebugLog( 'mathsearch', 'EOF' );
			wfDebugLog( 'mathsearch', var_export( $this->mathResults , true ) );
		}
		}
		// $wgOut->addHtml(htmlspecialchars( $pattern) );
		$wgOut->addWikiText( "<math> $pattern </math>" );
		// dbw = wfGetDB( DB_MASTER );$dbw->encodeBlob(pack( 'H32'
		// $inputhash= $dbw->encodeBlob(pack( 'H32',md5($pattern) );
		// $wgOut->addWikiText("$inputhash");
		$dbr = wfGetDB( DB_SLAVE );
		$inputhash = $dbr->encodeBlob( pack( 'H32', md5( $pattern ) ) );
		$rpage = $dbr->select(
			'mathindex',
			array( 'mathindex_page_id', 'mathindex_anchor', 'mathindex_timestamp' ),
			array( 'mathindex_inputhash' => $inputhash )
		);
		foreach ( $rpage as $row )
			wfDebugLog( "MathSearch", var_export( $row, true ) );
		/*$wt="{{#ask:".substr($pageList,2)."
		 | ?Dct:title
		| ?Personname
		| ?Dct:dateSubmitted
		| ?Dct:subject
		}}";
		$wgOut->addWikiText($wt);
	 wfDebugLog( 'mathsearch', $wt);*/

	}

	/**
	 * @param unknown $pageID
	 * @return boolean
	 */
	function DisplayMath( $pageID ) {
		global $wgOut;
		$page = $this->mathResults[(string)$pageID];
		$dbr = wfGetDB( DB_SLAVE );
		$article = Article::newFromId( $pageID );
		$pagename = (string)$article->getTitle();
		wfDebugLog( "MathSearch", "Processing results for $pagename" );
		foreach ( $page as $anchorID => $answ ) {
			$res = $dbr->selectRow(
					array( 'mathindex', 'math' ),
					array( 'math_mathml', 'mathindex_page_id', 'mathindex_anchor',
							'mathindex_inputhash', 'math_inputhash' ),
					'mathindex_page_id = "' . $pageID
						. '" AND mathindex_anchor= "' . $anchorID
						. '" AND mathindex_inputhash = math_inputhash'
					);
			if ( $res ) {
				$mml = utf8_decode( $res->math_mathml );
				$wgOut->addHtml( "&nbsp;&nbsp;&nbsp;" );
				$wgOut->addWikiText( "[[$pagename#math$anchorID|Eq: $anchorID]] ", false );
				$wgOut->addHtml( MathLaTeXML::embedMathML( $mml ) );
				$wgOut->addHtml( "<br />" );
				$xpath = $answ[0]['xpath'];
				$xmml = new SimpleXMLElement( $res->math_mathml );
				$hit = $xmml->xpath( $xpath );
				while ( list( , $node ) = each( $hit ) ) {
					// $wgOut->addHtml(var_export($node,true));
					$wgOut->addHtml( "<math>" . utf8_decode( $node->asXML() ) . "</math>" );
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

	/**
	 * @param unknown $pattern
	 * @param unknown $text
	 * @return string
	 */
	function searchForm( $pattern, $text ) {
		$out = '';
		// The form header, which links back to this page.
		$pageID = Title::makeTitle( NS_SPECIAL, 'MathSearch' );
		$action = $pageID->escapeLocalURL();
		$out .= "<form method=\"get\" action=\"$action\">\n";
		// The search text field.
		$pattern = htmlspecialchars( $pattern );
		$out .= "<p>Search for LaTeX pattern <input type=\"text\" name=\"pattern\"" . " value=\"$pattern\" size=\"36\" /> for example \sin(a+?b) \n";
		if ( self::getLucene() ) {
			$out .= "<p>Search for Text pattern <input type=\"text\" name=\"text\"" . " value=\"$text\" size=\"36\" />\n";
		} else {
			$out .= "<p> LuceneSearch not found. Text search <b>disabled</b>!<br/> For details see <a href=\"http://www.mediawiki.org/wiki/Extension:MWSearch\">MWSearch</a>.</p>";
		}
			// The search button.
		$out .= "<input type=\"submit\" name=\"searchx\" value=\"Search\" /></p>\n";
		// The table of namespace checkboxes.
		$out .= "<p><table><tr>\n";
		$out .= "</tr></table></p>\n";
		$out .= "</form>\n";
		return $out;
	}

	/**
	 * @param unknown $URL
	 * @param unknown $texcmd
	 */
	function LaTeXMLRender( $URL, $texcmd ) {
		global $wgOut;
		$renderer = new MathLaTeXML($texcmd);
		$renderer->setLaTeXMLSettings('profile=mwsquery');
		$renderer->render(true);
		return $renderer->getMathml();
	}

	/**
	 * @param unknown $tex
	 * @return string|boolean
	 */
	function render( $tex ) {
		global $wgLaTeXMLUrl;
		$contents = $this->LaTeXMLRender( $wgLaTeXMLUrl, $tex );
		if ( strlen( $contents ) == 0 ) {
			return 'ERROR unknown';
		}
		return $this->genSerachString( $contents );
	}


	/**
	 * @param unknown $cmml
	 * @return boolean
	 */
	function genSerachString( $cmml ) {
		global $wgMWSUrl;

		$out = "";	$numProcess = 30000;
		$mwsExpr = str_replace( "answsize=\"30\"", "answsize=\"$numProcess\" totalreq=\"yes\"", $cmml );
		$mwsExpr = str_replace( "m:", "", $mwsExpr );
		wfDebugLog( 'mathsearch', 'MWS query:' . $mwsExpr );
		$res = Http::post( $wgMWSUrl, array( "postData" => $mwsExpr, "timeout" => 60 ) );

		if ( $res == false ) {
			wfDebugLog( "MathSearch", "Nothing retreived from $wgMWSUrl. Check if mwsd is running." );
			return false;
		}
		$xres = new SimpleXMLElement( $res );
		$this->numMathResults = (int) $xres["total"];
		wfDebugLog( "MathSearch", $this->numMathResults . " results retreived from $wgMWSUrl." );
		if ( $this->numMathResults == 0 )
			return false;


		$this->relevantMathMap = array(); $this->mathResults = array();
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
		//  $this->qs = "<mws:expr> $out </mws:expr>";

		return true;
		//  return "$pre <mws:expr> $out </mws:expr> $post"; //$this->math_result;//"$pre <mws:expr> $out </mws:expr> $post";
	}


	/**
	 * @param unknown $xmlRoot
	 */
	function processMathResults( $xmlRoot )
	{
		foreach ( $xmlRoot->children( "mws", TRUE ) as $page ) {
			$attrs = $page->attributes();
			$uri = explode( "#", $attrs["uri"] );
			$pageID = $uri[0];	$AnchorID = substr( $uri[1], 4 );
			$this->relevantMathMap[$pageID] = true;
			$substarr = array();
			// $this->mathResults[(string) $pageID][(string) $AnchorID][]=$page->asXML();
			foreach ( $page->children( "mws", TRUE ) as $substpair ) {
				$substattrs = $substpair->attributes();
				$substarr[] = array( "qvar" => (string) $substattrs["qvar"], "xpath" => (string) $substattrs["xpath"] );
			}
			$this->mathResults[(string) $pageID][(string) $AnchorID][] = array( "xpath" => (string) $attrs["xpath"], "mappings" => $substarr );// ,"original"=>$page->asXML()

		}
	}
}
