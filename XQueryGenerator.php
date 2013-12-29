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
// Include for the Object-Oriented API

class XQueryGenerator extends SpecialPage {
	/**
	 *
	 */
	function __construct() {
		parent::__construct( 'XQueryGenerator' );
	}
	private $qvar = array();
	private $relativeXPath ="";
	private $lengthConstraint='';
	function searchForm( $tex, $type ) {

		$out = '';
		// The form header, which links back to this page.
		$pageID = Title::makeTitle( NS_SPECIAL, 'XQueryGenerator' );
		$action = htmlspecialchars( $pageID->getLocalURL() );
		$out .= "<form method=\"get\" action=\"$action\">\n";
		// The search text field.
		$tex = htmlspecialchars( $tex );
		$out .= "<p>Search for LaTeX pattern <input type=\"text\" name=\"tex\"" . " value=\"$tex\" size=\"36\" /> for example \sin(a+?b) \n";
		$out .= "<p>Search for Text pattern <input type=\"text\" name=\"type\"" . " value=\"$type\" size=\"36\" />\n";
		// The search button.
		$out .= "<input type=\"submit\" name=\"searchx\" value=\"Search\" /></p>\n";
		// The table of namespace checkboxes.
		$out .= "<p><table><tr>\n";
		$out .= "</tr></table></p>\n";
		$out .= "</form>\n";
		return $out;
	}
	/**
	 * @param unknown $par
	 */
	function execute( $par ) {
		global $wgRequest, $wgOut, $wgDebugMath;
		if ( ! $wgDebugMath ) {
			$wgOut->addWikiText( "==Debug mode needed==  This function is only supported in math debug mode." );
			return false;
		}
/*		require_once 'zorba_api.php';

// create Zorba instance in memory
$ms = InMemoryStore::getInstance();
$zorba = Zorba::getInstance($ms);

try {
  // create and compile query string
  $queryStr = <<<END
  let \$message := 'She sells sea shells by the sea shore'
  return
  <result>{\$message}</result>
END;
  $query = $zorba->compileQuery($queryStr);

  // execute query and display result
  $result = $query->execute();
  echo $result;

  // clean up
  $query->destroy();
  $zorba->shutdown();
  InMemoryStore::shutdown($ms);
} catch (Exception $e) {
  die('ERROR:' . $e->getMessage());
}*/
//die ("all right");
		require_once 'Zorba/XQueryProcessor.php';
		$tex = $wgRequest->getVal( 'tex' );// Page ID
		$type = $wgRequest->getVal( 'type' );// Equation ID
		if ( is_null( $tex ) or is_null( $type ) ) {
			$out = $this->searchForm( $tex, $type );
			$wgOut->addHTML( $out );
		} else {
			$this->DisplayInfo( $tex , $type );
		}
	}
	private static function isSyntaxHighlightingPossible() {
		global $wgParser;
		$tags = $wgParser->getTags();
		return in_array( 'syntaxhighlight', $tags );
	}


	public function DisplayInfo( $tex , $type ) {
		global $wgOut, $wgDebugMath, $wgParser;
		$settings = //'xhtml&' .
			'whatsin=math&' .
			'whatsout=math&' .
			'cmml&' .
			'preload=LaTeX.pool&' .
			'preload=article.cls&' .
			'preload=amsmath&' .
			'preload=amsthm&' .
			'preload=amstext&' .
			'preload=amssymb&' .
			'preload=eucal&' .
			'preload=[dvipsnames]xcolor&' .
			'preload=url&' .
			'preload=hyperref&' .
			'preload=mws&' .
			'preload=texvc';
		$wgOut->addWikiText( '==General==' );
		$renderer = MathLaTeXML::getRenderer( $tex, array(), MW_MATH_LATEXML );
		$renderer->setLaTeXMLSettings( $settings );
		$wgOut->addHTML( $renderer->render( true ) );
		$wgOut->addWikiText( '==code==' );
		if ( self::isSyntaxHighlightingPossible() ) {
			$wgOut->addWikiText( '<syntaxhighlight lang="xml">' . $renderer->getMathml() . '</syntaxhighlight>' );
			$xml = new SimpleXMLElement( $renderer->getMathml() );
			//$wgOut->addWikiText( '<syntaxhighlight lang="xml">//*:mrow[' . self::generateConstraint( $xml->mrow ) . ']</syntaxhighlight>' );
		}
		$xquery = new XQueryProcessor();

$query = <<<'XQ'
declare variable $foo  as xs:string external;
declare variable $bar  as xs:integer external;
declare variable $doc1 as document-node() external;
declare variable $doc2 as document-node() external;

$foo, $bar, $doc1, $doc2
XQ;
$doc = $xquery->parseXML( $renderer->getMathml() );
$xquery->importQuery( $query );
$fixedConstraints= $this->generateConstraint( $xml->children() );
$qvarConstraintString='';
foreach ( $this->qvar as $key => $value ) {
	$first=$value[0];
	if( $qvarConstraintString ){
		$qvarConstraintString .= ' and ';
	}
	$qvarConstraintString .= '$x'.$first;
	$second ='';
	foreach ($value as $second ){
		if($second){
			$second.=' and $x'.$first;
		}
		$qvarConstraintString .= ' = $x' .$second;
	}
}
$query = 'declare default element namespace "http://www.w3.org/1998/Math/MathML";
for $m in //*:expr return
	for $x in $m//*:'.self::getFirstChildName($xml).'['.
		 $fixedConstraints. '] return
			if( '.$qvarConstraintString.$this->lengthConstraint.') then
 <a href="http://demo.formulasearchengine.com/index.php?curid={$m/@url}">result</a>
else endif';
// <res>{$m}</res>';
 // $xquery->importQuery($query);
$wgOut->addWikiText( '<syntaxhighlight lang="xml">'.$query.'</syntaxhighlight>' );

$xquery->setVariable( 'world', 'World!' );
$xquery->setVariable( 'foo', 'bar' );

$xquery->setVariable( 'bar', 3 );

$doc = $xquery->parseXML( "<root />" );
$xquery->setVariable( "doc1", $doc );

$doc = $xquery->parseXML( $renderer->getMathml() );
$xquery->setVariable( "doc2", $doc );
$wgOut->addHtml( $xquery->execute() );

	}
private static function getFirstChildName($xml){
	return $xml->children()->getName();
}
	private function generateConstraint( $xml ) {
		$i = 0;
		$out = "";
		$hastext = false;
		foreach ( $xml->children() as $child ) {
			$i++;
			$attrib  = $child->attributes();
			if( $child->getName() == "csymbol" && $attrib=="mws" ){
				$qvarname = (string) $child[0];
				//die ($qvarname);
				if(array_key_exists($qvarname, $this->qvar)){
					$this->qvar[$qvarname][]=$this->relativeXPath."/*[".$i."]";
				} else {
					$this->qvar[$qvarname]=array($this->relativeXPath."/*[".$i."]");
				}
			} else {
			if ( $hastext ) {
				$out .= ' and ';
			}
			$out .= '*[' . $i . ']/name() =\'' . $child->getName() . '\'';
			$hastext = true;
			if ( $child->count() > 0 ) {
				$this->relativeXPath.="/*[".$i."]";
				$out .= ' and *[' . $i . "][\n\t" . $this->generateConstraint( $child  ) . "\n]";
			} else {
				if($child[0]){
					$out .= ' and *[' . $i . "]/text()='" . $child[0] . "'";
				}

			}
 		}}
 		$this->lengthConstraint .=' and fn:count($x'.$this->relativeXPath .'/*) = '. $i;
		if ($this->relativeXPath){
			$this->relativeXPath=  substr($this->relativeXPath, 0, strrpos($this->relativeXPath,"/"));
		}
		return $out;
	}
}
