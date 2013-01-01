<?php
class MathObject extends MathRenderer {
	protected $anchorID = 0;
	protected $pageID = 0;
	protected $index_timestamp=null;
	public function getAnchorID() {
		return $this->anchorID;
	}
	public function setAnchorID( $ID ) {
		$this->anchorID = $ID;
	}
	public function getPageID() {
		return $this->pageID;
	}
	public function setPageID( $ID ) {
		$this->pageID = $ID;
	}
	
	public static function constructformpagerow($res){
		global $wgDebugMath;
		$instance = new self();
		$instance->setPageID($res->mathindex_page_id);
		$instance->setAnchorID($res->mathindex_anchor);
		if ($wgDebugMath){
			$instance->index_timestamp=$res->mathindex_timestamp;
		}
		$instance->inputhash=$res->mathindex_inputhash;
		$instance->_readfromDB();
		return $instance;
	}
	
	public static function constructformpage($pid,$eid){
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow(
				array('mathindex'),
				self::dbIndexFieldsArray(),
				'mathindex_page_id = ' . $pid
				.' AND mathindex_anchor= ' . $eid
		);
		wfDebugLog("MathSearch",var_export($res,true));
		return self::constructformpagerow($res);
	}
	
	/**
	 * Gets all occurences of the tex.
	 * @return array(MathObject)
	 */
	public function getAllOccurences(){

		$out=array();
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
				'mathindex',
				self::dbIndexFieldsArray(),
				'mathindex_inputhash="'.$this->getInputHash().'"'
		);
		foreach($res as $row){
			wfDebugLog("MathSearch",var_export($row,true));
			$var=self::constructformpagerow($row);
			$var->printLink2Page(false);
			array_push($out, $var);
		}
		return $out;
	}
	public function getPageTitle(){
		$article = Article::newFromId( $this->getPageID());
		return (string)$article->getTitle();
	}
	public function printLink2Page($hidePage=true){
		global $wgOut;
		$wgOut->addHtml( "&nbsp;&nbsp;&nbsp;" );
		$pageString=$hidePage?"":$this->getPageTitle()." ";
		$wgOut->addWikiText( "[[".$this->getPageTitle()."#math".$this->getAnchorID()
				."|".$pageString."Eq: ".$this->getAnchorID()."]] ", false );
		//$wgOut->addHtml( MathLaTeXML::embedMathML( $this->mathml ) );
		$wgOut->addHtml( "<br />" );
	}
	
	/**
	 * @return Ambigous <multitype:, multitype:unknown number string mixed >
	 */
	private static function dbIndexFieldsArray(){
		global $wgDebugMath;
		$in= array(
				'mathindex_page_id',
				'mathindex_anchor'  ,
				'mathindex_inputhash');
		if ($wgDebugMath){
			$debug_in= array(
					'mathindex_timestamp');
			$in=array_merge($in,$debug_in);
		}
		return $in;
	}
	public function getPageTitle(){
		$article = Article::newFromId( $this->getPageID());
		return (string)$article->getTitle();
	}
	public function printLink2Page($hidePage=true){
		global $wgOut;
		$wgOut->addHtml( "&nbsp;&nbsp;&nbsp;" );
		$pageString=$hidePage?"":$this->getPageTitle()." ";
		$wgOut->addWikiText( "[[".$this->getPageTitle()."#math".$this->getAnchorID()
				."|".$pageString."Eq: ".$this->getAnchorID()."]] ", false );
		//$wgOut->addHtml( MathLaTeXML::embedMathML( $this->mathml ) );
		$wgOut->addHtml( "<br />" );
	}
	
	public function render($purge = false){
		
	}
}