<?php

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2014 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @file
 * @ingroup extensions
 */
class MathEngineDB2 {
	/** @var MathQueryObject the query to be answered*/
	protected $query;
	protected $size = false;
	protected $resultSet;
	protected $relevanceMap;

    /**
	 * 
	 * @return MathQueryObject
	 */
	public function getQuery() {
		return $this->query;
	}

	function __construct(MathQueryObject $query) {
		$this->query = $query;
	}
	public function getSize() {
		return $this->size;
	}

	public function getResultSet() {
		return $this->resultSet;
	}

	public function getRelevanceMap() {
		return $this->relevanceMap;
	}

		/**
	 * 
	 * @param MathQueryObject $query
	 * @return \MathSearchEngine
	 */
	public function setQuery(MathQueryObject $query) {
		$this->query = $query;
		return $this;
	}


    /**
     *
     * @param XQueryGenartorDB2 $query
     * @return \DB2
     */
    public function setDBQuery(XQueryGeneratorDB2 $query){
        $this->query = $query;
        return $this;
    }




		/**
	 * Posts the query to mwsd and evaluates the result data
	 * @return boolean
	 */
	function postQuery() {

        global $wgMathSearchMWSUrl, $wgMathDebug;
		global $wgMathSearchDB2ConnStr;
		if ( ! MathSearchHooks::isDB2Supported() ) {
			throw new error( 'DB2 php client is not installed.' );
		}
		$conn = db2_connect($wgMathSearchDB2ConnStr, '', '');
		$stmt = db2_exec($conn, $this->query->getXQuery() );

		$this->size = db2_num_rows ( $stmt );
		wfDebugLog( "MathSearch", $this->size . " results retrieved from $wgMathSearchMWSUrl." );
		if ($this->size ==-1) {
			return true;
		}

		$this->relevanceMap = array();
		$this->resultSet = array();

		$texResults = array();
		$moArray = array();
		while($row = db2_fetch_row( $stmt ) ){
			//FIXME: tex is not a really good key for lookup change to the md5 inputhash
			// than use $mo = MathObject::newFromMd5($theMD5) to get the MathObject
			$tex =   db2_result( $stmt, 0 );
			$tex = str_replace( '<?xml version="1.0" encoding="UTF-8" ?>' , '' , $tex );
			$texResults[] = $tex;


			$mo = new MathObject($tex);
            //$md = MathObject::newFromMd5($tex);
			$mo->readFromDatabase();

			$all  = $mo->getAllOccurences();

			array_push($moArray, $all);
		}
		//@var $mo MathObject
		foreach ($moArray as $mo) {

           $this->relevanceMap[$mo->getPageID()]=true;
			//$this->resultSet[(string) $mo->getPageID()][(string) $mo->getAnchorID()][] = array( "xpath" => '/', "mappings" => array() ); // ,"original"=>$page->asXML()
		}

		//$this->processMathResults( $xres );
		return true;

	}
}