<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use MediaWiki\Extension\MathSearch\Graph\Map;
use MediaWiki\Extension\MathSearch\Graph\PidLookup;

class Recommendation extends GraphJob {

	private readonly PidLookup $qid_cache;

	public function __construct( $params ) {
		parent::__construct( 'Recommendation', $params );
		$this->qid_cache = new PidLookup();
	}

	public function run() {
		$this->extractDes();
		$runid = $this->params['runid'];
		$this->params['optional_fields'] = [ 'Len' ];
		$table = [];
		foreach ( $this->params['rows'] as $seed => $row ) {
			$qSeed = $this->qid_cache->getQ( $seed );
			if ( $qSeed === false ) {
				continue;
			}
			$newRow = [ 'qid' => $qSeed ];
			if ( isset( $row['Len'] ) ) {
				$newRow['Len'] = $row['Len'];
				unset( $row['Len'] );
			}
			$pos = 0;
			foreach ( $row as $rs => $score ) {
				$qRs = $this->qid_cache->getQ( $rs );
				if ( $qRs === false ) {
					continue;
				}
				$newRow['P1643_' . $pos]  = $qRs;
				$newRow['qal1659_' . $pos] = $score;
				$newRow['qal1660_' . $pos] = $runid;
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
