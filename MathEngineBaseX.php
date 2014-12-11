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
class MathEngineBaseX {
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
	 * Posts the query to BaseX and evaluates the results
	 * @throws BaseXError
	 * @throws MWException
	 * @return boolean
	 */
	function postQuery() {
        global $wgMathSearchBaseXSupport, $wgMathSearchBaseXDatabaseName;
		if ( ! $wgMathSearchBaseXSupport) {
			throw new MWException( 'BaseX support is disabled.' );
		}
		$session = new BaseXSession();
		$session->execute("open $wgMathSearchBaseXDatabaseName");
		$res = $session->execute( "xquery ".$this->query->getXQuery() );
		$this->relevanceMap = array();
		$this->resultSet = array();
		$size = 0;
		if( $res ){
			//TODO: ReEvaluate the regexp.
			$baseXRegExp = "/<a .*? href=\"http.*?curid=math.(\d+).math(\d+)\"/";
			preg_match_all( $baseXRegExp , $res, $matches,PREG_SET_ORDER);
			foreach($matches as $match){
				$mo = MathObject::constructformpage($match[1],$match[2]);
				if ( $mo ) {
					$size++;
					$this->relevanceMap[(string)$mo->getPageID()] = true;
					$this->resultSet[(string)$mo->getPageID()][(string)$mo->getAnchorID()][] =
						array(
							"xpath" => '/',
							"mappings" => array()
						);
				} else {
					wfDebugLog( 'MathSearch', "Warning: Entry ${match[1]}, ${match[2]} not fund in database. Index might be out of date." );
				}
			}
		}
		$this->size = $size;
		return true;
	}
}