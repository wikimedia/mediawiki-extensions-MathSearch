<?php
class MathObject extends MathRenderer {
	protected $anchorID = 0;
	protected $pageID = 0;
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
	
	public static function constructformpage($pid,$eid){
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow(
				array('mathindex'),
				array('mathindex_page_id',
					'mathindex_anchor',
					'mathindex_inputhash' ),
				'mathindex_page_id = "' . $pid
				.'" AND mathindex_anchor= "' . $eid
		);
		$this->inputhash=$res->mathindex_inputhash;
		$this->_readfromDB();
		
	}
	
}