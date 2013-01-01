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
class FormulaInfo extends SpecialPage {
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
		$sqlFilter=$wgRequest->getVal('sqlfilter');
		$res = $dbr->select(
				array('math'),
				array( 'math_mathml','math_inputhash','math_log','math_tex','valid_xml','math_status'
						,'math_timestamp' ),
				$sqlFilter
		);
		foreach ($row as $res){
			$mo=MathObject::constructformpagerow($res);
		}
		
	}
	
	public static function DisplayInfo($pid,$eid){
		global $wgOut;
		$wgOut->addWikiText('Display information for equation id:'.$eid.' on page id:'.$pid);
		$article = Article::newFromId( $pid );
		if(!$article){
			$wgOut->addWikiText('There is no page with page id:'.$pid.' in the database.');
			return false;
		}
		
		$pagename = (string)$article->getTitle();
		$wgOut->addWikiText( "* Page found: [[$pagename#math$eid|$pagename]] (eq $eid)  ",false);
		$wgOut->addHtml('<a href="/index.php?title='.$pagename.'&action=purge&mathpurge=true">(force rerendering)</a>' );
		$mo=MathObject::constructformpage($pid,$eid);
		$wgOut->addWikiText("Occurences on the following pages:");
		wfDebugLog( "MathSearch",var_export($mo->getAllOccurences(),true));
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow(
			array('mathindex','math'),
			array( 'math_mathml', 'mathindex_page_id', 'mathindex_anchor',
					'mathindex_inputhash','math_inputhash','math_log','math_tex','valid_xml','math_status'
					,'mathindex_timestamp','math_timestamp' ),
			'mathindex_page_id = "' . $pid
				.'" AND mathindex_anchor= "' . $eid
				.'" AND mathindex_inputhash = math_inputhash'
			);
		if(! $res){
			$wgOut->addWikiText('No matching database entries in math and mathsearch tables found in the database.');
			return false;
		}
		
		$StartS='Symbols assumed as simple identifiers (with # of occurences):';
		$StopS='Conversion complete';
		//$wgOut->addWikiText('<b>:'.var_export($res,true).'</b>');
		$wgOut->addWikiText('TeX : <code>'.$mo->getTex().'</code>');
		$wgOut->addWikiText('Rendered at : <code>'.$res->math_timestamp.'</code> an idexed at <code>'.$res->mathindex_timestamp.'</code>');
		$wgOut->addWikiText('validxml : <code>'.$res->valid_xml.'</code> recheck:',false);
		$wgOut->addHtml(MathLaTeXML::isValidMathML($res->math_mathml)?"valid":"invalid");
		$wgOut->addWikiText('status : <code>'.$res->math_status.'</code>');
		$log=htmlspecialchars( $res->math_log );
		$sPos=strpos($log,$StartS);
		$sPos+=strlen($StartS);
		$ePos=strpos($log,$StopS,$sPos);
		$varS=substr($log, $sPos,$ePos-$sPos);
		$wgOut->addWikiText('Variables:'.trim($varS));
		$wgOut->addHtml( "&nbsp;&nbsp;&nbsp;" );
		//$wgOut->addWikiText( "[[$pagename#math$eid|Eq: $eid]] ", false );
		$wgOut->addHtml( htmlspecialchars( $res->math_mathml ) );
		$wgOut->addHtml( "<br />" );
		$wgOut->addHtml( "<br />" );
		$wgOut->addHtml( "<br />" );
		$wgOut->addHtml(htmlspecialchars( $res->math_log ) );
	}
}
