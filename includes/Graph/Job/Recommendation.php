<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use MediaWiki\Extension\MathSearch\Graph\Map;
use MediaWiki\Extension\MathSearch\Graph\PidLookup;

class Recommendation extends GraphJob {

	private PidLookup $qid_cache;

	public function __construct( $params ) {
		parent::__construct( 'Recommendation', $params );
		$this->qid_cache = new PidLookup();
	}

	public function run() {
		$this->extractDes();
		$table = [];
		foreach ( $this->params['rows'] as $seed => $row ) {
			$qSeed = $this->qid_cache->getQ( $seed );
			if ( $qSeed === false ) {
				continue;
			}
			$newRow = [ 'qid' => $qSeed ];
			$pos = 0;
			foreach ( $row as $rs => $score ) {
				$qRs = $this->qid_cache->getQ( $rs );
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

	private function extractDes(): void {
		$this->qid_cache->warmupFromKeys( $this->params['rows'] );
	}

}
