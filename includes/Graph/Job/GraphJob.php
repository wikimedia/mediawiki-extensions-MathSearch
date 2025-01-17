<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use GenericParameterJob;
use Job;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;

abstract class GraphJob extends Job implements GenericParameterJob {

	/** @var User */
	private $user;

	protected static function getLog(): LoggerInterface {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	public function getUser() {
		if ( !$this->user ) {
			$username = $this->params['username'] ?? $this->params['jobname'];
			$user = MediaWikiServices::getInstance()->getUserFactory()
				->newFromName( $username );
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
