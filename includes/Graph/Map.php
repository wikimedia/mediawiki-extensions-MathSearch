<?php

namespace MediaWiki\Extension\MathSearch\Graph;

use JobQueueGroup;
use MediaWiki\Extension\MathSearch\Graph\Job\FetchIdsFromWd;
use MediaWiki\Extension\MathSearch\Graph\Job\NormalizeDoi;
use MediaWiki\Extension\MathSearch\Graph\Job\QuickStatements;
use MediaWiki\Extension\MathSearch\Graph\Job\SetProfileType;
use MediaWiki\MediaWikiServices;
use MediaWiki\Sparql\SparqlException;

class Map {
	private const ROWS_PER_JOB = 100;
	private JobQueueGroup $jobQueueGroup;

	public function __construct( ?JobQueueGroup $jobQueueGroup = null ) {
		$this->jobQueueGroup = $jobQueueGroup ?? MediaWikiServices::getInstance()->getJobQueueGroup();
	}

	public function pushJob(
		array $table, int $segment, string $jobType, array $options
	): void {
		$options[ 'rows' ] = $table;
		$options[ 'segment' ] = $segment;
		$this->jobQueueGroup->lazyPush( new $jobType( $options ) );
	}

	/**
	 * @throws SparqlException
	 */
	public function getJobs(
		callable $output, int $batch_size, string $type, string $jobType, array $jobOptions = []
	): void {
		$jobOptions[ 'jobname' ] = 'import' . date( 'ymdhms' );
		$jobOptions[ 'prefix' ] = $type;

		$offset = 0;
		$rows = [];
		$segment = 0;
		do {
			$output( 'Read from offset ' . $offset . ".\n" );
			switch ( $jobType ) {
				case QuickStatements::class:
					$query = $jobOptions[ 'query' ] . "\nLIMIT $batch_size OFFSET $offset";
					break;
				case SetProfileType::class:
					$query = Query::getQueryFromConfig( $type, $offset, $batch_size );
					break;
				case NormalizeDoi::class:
					$query = Query::getQueryForDoi( $offset, $batch_size );
					break;
				case FetchIdsFromWd::class:
					$jobOptions[ 'batch_size' ] = $batch_size;
					$this->pushJob( $rows, $segment, $jobType, $jobOptions );
					$output( "Pushed job.\n" );
					return;
				default:
					$query = Query::getQueryFromProfileType( $type, $offset, $batch_size );
			}
			$rs = Query::getResults( $query );
			$output( "Retrieved " . count( $rs ) . " results.\n" );
			foreach ( $rs as $row ) {
				if ( $jobType === NormalizeDoi::class ) {
					$rows[$row['qid']] = $row['doi'];
				} elseif ( $jobType === QuickStatements::class ) {
					$rows[] = $row;
				} else {
					$rows[] = $row['qid'];
				}
				$cntRows = count( $rows );
				if ( $cntRows > self::ROWS_PER_JOB ) {
					$this->pushJob( $rows, $segment, $jobType, $jobOptions );
					$output( "Pushed $cntRows rows to segment $segment.\n" );
					$segment++;
					$rows = [];
				}
			}
			$offset += $batch_size;
		} while ( count( $rs ) === $batch_size );
		$this->pushJob( $rows, $segment, $jobType, $jobOptions );
		$cntRows = count( $rows );
		$output( "Pushed $cntRows rows to last segment $segment.\n" );
	}

}
