<?php

use MediaWiki\Extension\MathSearch\Engine\BaseX;
use MediaWiki\MediaWikiServices;

class MathSearchTerm {

	public const TYPE_TEXT = 0;
	public const TYPE_MATH = 1;
	public const TYPE_XMATH = 2;
	public const REL_AND = 0;
	public const REL_OR = 1;
	public const REL_NAND = 2;
	public const REL_NOR = 3;

	/** @var int */
	private $key = 0;
	/** @var int */
	private $rel = 0;
	/** @var int */
	private $type = 0;
	/** @var string */
	private $expr = '';
	/** @var int[] */
	private $relevanceMap = [];
	/** @var array<int,SearchResult|array<string,array[]>> */
	private $resultSet = [];

	/**
	 * @param int $i
	 * @param int $rel
	 * @param int $type
	 * @param string $expr
	 */
	public function __construct( $i, $rel, $type, $expr ) {
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

	public function doSearch( BaseX $backend ) {
		$backend->resetResults();
		switch ( $this->getType() ) {
			case self::TYPE_TEXT:
				$search = MediaWikiServices::getInstance()->getSearchEngineFactory()->create( "CirrusSearch" );
				$search->setLimitOffset( 10000 );
				$sres = $search->searchText( $this->getExpr() );
				if ( $sres ) {
					/** @var SearchResult $tres */
					foreach ( $sres as $tres ) {
						$revisionID = $tres->getTitle()->getLatestRevID();
						$this->resultSet[(string)$revisionID] = $tres;
						$this->relevanceMap[] = $revisionID;
					}
					return true;
				}

				return false;

			case self::TYPE_MATH:
				$query = new MathQueryObject( $this->getExpr() );
				$backend->setQuery( $query );
				$backend->postQuery();
				$this->relevanceMap = $backend->getRelevanceMap();
				$this->resultSet = $backend->getResultSet();
				break;
			case self::TYPE_XMATH:
				$query = new MathQueryObject( '' );
				$query->setXQuery( $this->getExpr() );
				$backend = new BaseX( $query );
				$backend->setType( 'xquery' );
				if ( !$backend->postQuery() ) {
					return false;
				}
				$this->relevanceMap = $backend->getRelevanceMap();
				$this->resultSet = $backend->getResultSet();
		}
	}

	/**
	 * @return array<int,SearchResult|array<string,array[]>>
	 */
	public function getResultSet() {
		return $this->resultSet;
	}

	/**
	 * @return int[]
	 */
	public function getRelevanceMap() {
		return $this->relevanceMap;
	}

	/**
	 * @param int $revisionId
	 * @return SearchResult|array<string,array[]>
	 */
	public function getRevisionResult( $revisionId ) {
		return $this->resultSet[$revisionId] ?? [];
	}

}
