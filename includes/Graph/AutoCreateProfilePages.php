<?php

namespace MediaWiki\Extension\MathSearch\Graph;

use MediaWiki\Config\Config;
use MediaWiki\Extension\MathSearch\Graph\Job\PageCreation;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Output\OutputPage;
use MediaWiki\Sparql\SparqlException;

class AutoCreateProfilePages {
	public function __construct(
		private readonly Config $config,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly OutputPage $outputPage,
	) {
	}

	/**
	 * @throws SparqlException
	 */
	public function run(): void {
		$map = new Map( $this->jobQueueGroup );
		foreach ( array_keys( $this->config->get( 'MathProfileQueries' ) ) as  $type ) {
			$this->output( "Scheduling jobs for profile type: $type" );
			$map->scheduleJobs(
				static fn ( string $msg ) => LoggerFactory::getInstance( 'MathSearch' )->info( $msg ),
				100000,
				$type,
				PageCreation::class
			);
		}
		$this->output( 'All types are scheduled. Done!' );
	}

	public function output( string $out, ?string $channel = 'MathSearch' ): void {
		$this->outputPage->addWikiTextAsContent( $out );
	}
}
