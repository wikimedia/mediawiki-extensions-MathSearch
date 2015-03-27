<?php

class MathSearchTerm {
	const TYPE_TEXT = 0;
	const TYPE_MATH = 1;
	const TYPE_XMATH =2;
	const REL_AND = 0;
	const REL_OR = 1;
	const REL_NAND = 2;
	const REL_NOR = 3;
	private $key = 0;
	private $rel = 0;
	private $type = 0;
	private $expr = '';
	private $relevanceMap = array();
	private $resultSet =array();

	/**
	 * @param int $i
	 * @param int $rel
	 * @param int $type
	 * @param string $expr
	 */
	function __construct( $i, $rel, $type, $expr ) {
		$this->key  = $i;
		$this->rel  = $rel;
		$this->type = $type;
		$this->expr = $expr;
	}

	/**
	 * @return int
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * @param int $key
	 */
	public function setKey( $key ) {
		$this->key = $key;
	}

	/**
	 * @return int
	 */
	public function getRel() {
		return $this->rel;
	}

	/**
	 * @param int $rel
	 */
	public function setRel( $rel ) {
		$this->rel = $rel;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param int $type
	 */
	public function setType( $type ) {
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getExpr() {
		return $this->expr;
	}

	/**
	 * @param string $expr
	 */
	public function setExpr( $expr ) {
		$this->expr = $expr;
	}

	public function doSearch(MathEngineRest $backend){
		$backend->resetResults();
		switch ($this->getType() ) {
			case self::TYPE_TEXT:
				$search = SearchEngine::create( "CirrusSearch" );
				$search->setLimitOffset( 10000 );
				$sres = $search->searchText( $this->getExpr() );
				if ( $sres ) {
					while ( $tres = $sres->next() ) {
						$revisionID = $tres->getTitle()->getLatestRevID();
						$this->resultSet[(string)$revisionID] = $tres;
						$this->relevanceMap[] = $revisionID;
					}
					return true;
				} else {
					return false;
				}
			case self::TYPE_MATH:
				$query = new MathQueryObject( $this->getExpr() );
				$cQuery = $query->getCQuery();
				if ( $cQuery ) {
					$backend->setQuery( $query );
					if ( !$backend->postQuery() ) {
						return false;
					}
					$this->relevanceMap = $backend->getRelevanceMap();
					$this->resultSet = $backend->getResultSet();
				} else {
					return false;
				}
				break;
			case self::TYPE_XMATH:
				$query = new MathQueryObject( '' );
				$query->setXQuery( $this->getExpr() );
				$backend = new MathEngineBaseX( $query );
				$backend->setType( 'xquery' );
				if ( !$backend->postQuery() ) {
					return false;
				}
				$this->relevanceMap = $backend->getRelevanceMap();
				$this->resultSet = $backend->getResultSet();
		}
	}


	/**
	 * @return array
	 */
	public function getResultSet() {
		return $this->resultSet;
	}

	/**
	 * @return array
	 */
	public function getRelevanceMap() {
		return $this->relevanceMap;
	}

	public function getRevisionResult( $revisionId ){
		if ( array_key_exists((string) $revisionId, $this->resultSet ) ) {
			return $this->resultSet[(string) $revisionId];
		} else {
			return array();
		}

	}

}