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
class XQueryGenerator extends SpecialPage {
	/**
	 * 
	 */
	function __construct() {
		parent::__construct( 'XQueryGenerator' );
	}
	function searchForm( $tex, $type ) {
		$out = '';
		// The form header, which links back to this page.
		$pageID = Title::makeTitle( NS_SPECIAL, 'XQueryGenerator' );
		$action = $pageID->escapeLocalURL();
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
		global $wgRequest, $wgOut;
		$tex=$wgRequest->getVal('tex');//Page ID
		$type=$wgRequest->getVal('type');//Equation ID
		if(is_null($tex) or is_null($type)){
			$out=$this->searchForm($tex, $type);
			$wgOut->addHTML($out);
		} else {
			self::DisplayInfo($tex , $type);
		}
	}
	private static function isSyntaxHighlightingPossible(){
		global $wgParser;
		$tags = $wgParser->getTags();
		return in_array('syntaxhighlight', $tags);
	}
		
	
	public static function DisplayInfo($tex , $type){
		global $wgOut, $wgDebugMath,$wgParser;
		$settings= 'xhtml&'.
			'whatsin=math&'.
			'whatsout=math&'.
			'pmml&'.
			'preload=LaTeX.pool&'.
			'preload=article.cls&'.
			'preload=amsmath&'.
			'preload=amsthm&'.
			'preload=amstext&'.
			'preload=amssymb&'.
			'preload=eucal&'.
			'preload=[dvipsnames]xcolor&'.
			'preload=url&'.
			'preload=hyperref&'.
			'preload=mws&'.
			'preload=texvc';
		$wgOut->addWikiText('==General==');
		$renderer=MathLaTeXML::getRenderer($tex,array(),MW_MATH_LATEXML);
		$renderer->setLaTeXMLSettings($settings);
		$wgOut->addHTML($renderer->render(true));
		$wgOut->addWikiText('==code==');
		if (self::isSyntaxHighlightingPossible()){
			$wgOut->addWikiText('<syntaxhighlight lang="xml">'.$renderer->mathml.'</syntaxhighlight>');
			$xml = new SimpleXMLElement($renderer->mathml);
			$wgOut->addWikiText('<syntaxhighlight lang="xml">//*:mrow['.self::generateConstraint($xml->mrow).']</syntaxhighlight>');
		}

	}
	
	private static function generateConstraint($xml){
		$i=0;
		$out="";
		$hastext=false;
		foreach($xml->children() as $child){
			$i++;
			if($hastext){
				$out.=' and ';
			}
			$out.='*['.$i.']/name() =\''. $child->getName().'\'';
			$hastext=true;
			if($child->count()>0){
				$out.=' and *['.$i."][\n\t".self::generateConstraint($child)."\n]";
			} else {
				$out.=' and *['.$i."]/text()='".$child[0]."'";
			}
 		}
		return $out;
	}
}
