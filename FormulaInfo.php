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
		$pid=$wgRequest->getVal('pid');//Page ID
		$eid=$wgRequest->getVal('eid');//Equation ID
		if(is_null($pid) or is_null($eid)){
			$tex=$wgRequest->getVal('tex','');
			if($tex==''){
				$wgOut->addHTML('<b>Please specify page and equation id</b>');
			} else {
				self::InfoTex($tex);
			}
		} else {
			self::DisplayInfo($pid,$eid);
		}
	}
	public static function InfoTex($tex){
		global $wgDebugMath,$wgOut;
		if(!$wgDebugMath){
			$wgOut->addWikiTex("tex queries only supported in debug mode");
			return false;
		}
		$wgOut->addWikiText("Info for <code>".$tex.'</code>');
		$mo=new MathObject($tex);
		$allPages=$mo->getAllOccurences();
		if($allPages){
			self::DisplayInfo($allPages[0]->getPageID(), $allPages[0]->getAnchorID());
		} else{
			$wgOut->addWikiText("No occurences found clean up the database to remove unused formulae");
		}
	}
	public static function DisplayInfo($pid,$eid){
		global $wgOut, $wgDebugMath;
		$wgOut->addWikiText('==General==');
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
		//$wgOut->addWikiText('<b>:'.var_export($res,true).'</b>');
		$wgOut->addWikiText('TeX : <code>'.$mo->getTex().'</code>');
		
		$wgOut->addWikiText('MathML : ',false);
		$wgOut->addHTML($mo->mathml);
		$wgOut->addHtml('<a href="/wiki/Special:MathSearch?pattern='.urlencode($mo->getTex()).'&searchx=Search"><img src="http://wikidemo.formulasearchengine.com/images/FSE-PIC.png" width="15" height="15"></a>' );
		#$log=htmlspecialchars( $res->math_log );
		$wgOut->addWikiText('==Similar pages==');
		$wgOut->addWikiText('Calculataed based on the variables occuring on the entire '.$pagename.' page' );
		$mo->findSimilarPages($pid);
		$wgOut->addWikiText('==Variables==');
		$mo->getObservations();
		//$wgOut->addWikiText( "[[$pagename#math$eid|Eq: $eid]] ", false );
		$wgOut->addWikiText('==MathML==');
		
		$wgOut->addHtml( "<br />" );
		$wgOut->addHtml( htmlspecialchars( $mo->mathml ) );
		$wgOut->addHtml( "<br />" );
		$wgOut->addHtml( "<br />" );
		$wgOut->addHtml( "<br />" );
		if($wgDebugMath){
		$wgOut->addWikiText('==LOG and Debug==');
		$wgOut->addWikiText('Rendered at : <code>'.$mo->getTimestamp() 
			.'</code> an idexed at <code>'.$mo->getIndexTimestamp().'</code>');
		$wgOut->addWikiText('validxml : <code>'.$mo->getValidXml().'</code> recheck:',false);
		$wgOut->addHtml(MathLaTeXML::isValidMathML($mo->getMathml())?"valid":"invalid");
		$wgOut->addWikiText('status : <code>'.$mo->getStatusCode().'</code>');
		$wgOut->addHtml(htmlspecialchars( $mo->getLog() ) );
		}
	}
}
