<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use GenericParameterJob;
use Job;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

abstract class GraphJob extends Job implements GenericParameterJob {

	private $user;

	protected static function getLog() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	public function getUser() {
		if ( !$this->user ) {
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
			$this->user = $user;
		}
		return $this->user;
	}
}
