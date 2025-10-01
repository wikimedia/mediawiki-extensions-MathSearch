<?php

namespace MediaWiki\Extension\MathSearch\Graph;

use MediaWiki\Config\Config;
use MediaWiki\Extension\MathSearch\Graph\Job\PageCreation;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Sparql\SparqlException;
use MWStake\MediaWiki\Component\RunJobsTrigger\IHandler;
use MWStake\MediaWiki\Component\RunJobsTrigger\Interval;
use MWStake\MediaWiki\Component\RunJobsTrigger\Interval\OnceADay;

class AutoCreateProfilePages implements IHandler {
	private Config $config;
	private JobQueueGroup $jobQueueGroup;

	public function __construct( Config $config, JobQueueGroup $jobQueueGroup ) {
		$this->config = $config;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	public function getKey(): string {
		return str_replace( '\\', '-', strtolower( static::class ) );
	}

	/**
	 * @throws SparqlException
	 */
	public function run(): void {
		foreach ( array_keys( $this->config->get( 'MathProfileQueries' ) ) as  $type ) {
			( new Map( $this->jobQueueGroup ) )->getJobs(
				$this->output( ... ),
				100000,
				$type,
				PageCreation::class
			);
		}
	}

	public function getInterval(): Interval {
		return new OnceADay();
	}

	public function output( string $out, ?string $channel = 'MathSearch' ): void {
		LoggerFactory::getInstance( $channel )->info( $out );
	}
}
