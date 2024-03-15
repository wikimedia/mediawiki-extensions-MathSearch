<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use GenericParameterJob;
use Job;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Throwable;

class OpenAlex extends Job implements GenericParameterJob {
	public function __construct( $params ) {
		parent::__construct( 'OpenAlex', $params );
	}

	private static function getLog() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	public function run(): bool {
		global $wgMathOpenAlexQIdMap;
		$pDe = $wgMathOpenAlexQIdMap['document'];

		$user = MediaWikiServices::getInstance()->getUserFactory()
			->newFromName( $this->params['jobname'] );
		$exists = ( $user->idForName() !== 0 );
		if ( !$exists ) {
			MediaWikiServices::getInstance()->getAuthManager()->autoCreateUser(
				$user,
				AuthManager::AUTOCREATE_SOURCE_MAINT,
				false
			);
		}

		foreach ( $this->params['rows'] as $de => $row ) {
			try {
				self::getLog()->info( "Add OpenAlex data for Zbl $de." );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip processing Zbl $de.", [ $ex ] );
			}
		}

		return true;
	}
}
