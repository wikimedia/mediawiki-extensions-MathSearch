<?php

namespace MediaWiki\Extension\MathSearch\Graph;

use MediaWiki\MediaWikiServices;
use ToolsParser;

class Map {
	private int $batch_size = 100000;
	private const PAGES_PER_JOB = 100;
	private \JobQueueGroup $jobQueueGroup;

	public function pushJob(
		array $table, int $segment, string $jobname, string $type, string $jobType
	): void {
		$this->jobQueueGroup->lazyPush( new $jobType( [
			'jobname' => $jobname,
			'rows' => $table,
			'segment' => $segment,
			'prefix' => $type,
		] ) );
	}

	public function getJobs( callable $output, int $batch_size, string $type, string $jobType ): void {
		$this->jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		$jobname = 'import' . date( 'ymdhms' );
		$configFactory =
			MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'wgLinkedWiki' );
		$configDefault = $configFactory->get( "SPARQLServiceByDefault" );
		$arrEndpoint = ToolsParser::newEndpoint( $configDefault, null );
		$sp = $arrEndpoint["endpoint"];
		$offset = 0;
		$table = [];
		$segment = 0;
		do {
			$output( 'Read from offset ' . $offset . ".\n" );
			$query = Query::getQueryFromConfig( $type, $offset, $batch_size );
			$rs = $sp->query( $query );
			if ( !$rs ) {
				$output( "No results retrieved!\n" );
				break;
			} else {
				$output( "Retrieved " . count( $rs['result']['rows'] ) . " results.\n" );
			}
			foreach ( $rs['result']['rows'] as $row ) {
				$qID = $row['qid'];

				$table[] = $qID;
				if ( count( $table ) > self::PAGES_PER_JOB ) {
					$this->pushJob( $table, $segment, $jobname, $type, $jobType );
					$output( "Pushed jobs to segment $segment.\n" );
					$segment++;
					$table = [];
				}
			}
			$offset += $this->batch_size;
		} while ( count( $rs['result']['rows'] ) == $this->batch_size );
		$this->pushJob( $table, $segment, $jobname, $type, $jobType );
		$output( "Pushed jobs to last segment $segment.\n" );
	}
}
