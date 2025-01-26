<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use Exception;
use MediaWiki\Extension\MathSearch\Graph\Map;
use MediaWiki\Extension\MathSearch\Graph\Query;
use MediaWiki\Sparql\SparqlException;

class Recommendation extends GraphJob {

	private array $qid_cache = [];

	public function __construct( $params ) {
		parent::__construct( 'Recommendation', $params );
	}

	public function run() {
		$this->extractDes();
		$this->requestDes();
		$table = [];
		foreach ( $this->params['rows'] as $seed => $row ) {
			$qSeed = $this->de2q( $seed );
			if ( $qSeed === false ) {
				continue;
			}
			$newRow = [ 'qid' => $qSeed ];
			$pos = 0;
			foreach ( $row as $rs => $score ) {
				$qRs = $this->de2q( $rs );
				if ( $qRs === false ) {
					continue;
				}
				$newRow['P1643_' . $pos]  = $qRs;
				$newRow['qal1659_' . $pos] = $score;
				$newRow['qal1660_' . $pos] = 'Q6534273';
				$pos++;
			}
			if ( $pos > 0 ) {
				$table[] = $newRow;
			}
		}
		if ( count( $table ) ) {
			( new Map() )->pushJob(
				$table,
				$this->params['segment'],
				'MediaWiki\Extension\MathSearch\Graph\Job\QuickStatements',
				$this->params );
		}
		return true;
	}

	/**
	 * @param string $de
	 * @param bool $onlyCache
	 * @return string|false
	 * @throws SparqlException
	 * @throws Exception
	 */
	private function de2q( string $de, bool $onlyCache = false ) {
		if ( !isset( $this->qid_cache[$de] ) ) {
			$this->qid_cache[$de] = false;
			if ( !$onlyCache ) {
				$this->requestDes();
			}
		}
		return $this->qid_cache[$de];
	}

	private function extractDes(): void {
		foreach ( $this->params['rows'] as $seed => $row ) {
			$this->de2q( $seed, true );
			foreach ( $row as $de => $value ) {
				$this->de2q( $de, true );
			}
		}
	}

	/**
	 * @throws SparqlException
	 */
	private function requestDes(): void {
		$rows = array_filter( $this->qid_cache, static function ( $qid ) {
			return $qid == false;
		} );
		$map = Query::getDeQIdMap( $rows );
		$this->qid_cache = $map + $this->qid_cache;
	}

}
