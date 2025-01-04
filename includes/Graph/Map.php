<?php

namespace MediaWiki\Extension\MathSearch\Graph;

use JobQueueGroup;
use MediaWiki\Extension\MathSearch\Graph\Job\FetchIdsFromWd;
use MediaWiki\Extension\MathSearch\Graph\Job\NormalizeDoi;
use MediaWiki\Extension\MathSearch\Graph\Job\SetProfileType;
use MediaWiki\MediaWikiServices;
use MediaWiki\Sparql\SparqlException;

class Map {
	private int $batch_size;
	private const PAGES_PER_JOB = 100;
	private JobQueueGroup $jobQueueGroup;

	public function __construct( ?JobQueueGroup $jobQueueGroup = null, $batch_size = 100000 ) {
		$this->jobQueueGroup = $jobQueueGroup ?? MediaWikiServices::getInstance()->getJobQueueGroup();
		$this->batch_size = $batch_size;
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
		$table = [];
		$segment = 0;
		do {
			$output( 'Read from offset ' . $offset . ".\n" );
			switch ( $jobType ) {
				case SetProfileType::class:
					$query = Query::getQueryFromConfig( $type, $offset, $batch_size );
					break;
				case NormalizeDoi::class:
					$query = Query::getQueryForDoi( $offset, $batch_size );
					break;
				case FetchIdsFromWd::class:
					$jobOptions[ 'batch_size' ] = $batch_size;
					$this->pushJob( $table, $segment, $jobType, $jobOptions );
					$output( "Pushed job.\n" );
					return;
				default:
					$query = Query::getQueryFromProfileType( $type, $offset, $batch_size );
			}
			$rs = Query::getResults( $query );
			$output( "Retrieved " . count( $rs ) . " results.\n" );
			foreach ( $rs as $row ) {
				$qID = $row['qid'];
				if ( $jobType === NormalizeDoi::class ) {
					$table[$qID] = $row['doi'];
				} else {
					$table[] = $qID;
				}
				if ( count( $table ) > self::PAGES_PER_JOB ) {
					$this->pushJob( $table, $segment, $jobType, $jobOptions );
					$output( "Pushed jobs to segment $segment.\n" );
					$segment++;
					$table = [];
				}
			}
			$offset += $this->batch_size;
		} while ( count( $rs ) === $this->batch_size );
		$this->pushJob( $table, $segment, $jobType, $jobOptions );
		$output( "Pushed jobs to last segment $segment.\n" );
	}

}
