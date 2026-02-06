<?php

namespace MediaWiki\Extension\MathSearch\Wikidata;

use MediaWiki\Config\Config;
use MediaWiki\Extension\MathSearch\Graph\Job\PageCreation;
use MediaWiki\JobQueue\JobQueueGroup;
use Wikibase\Lib\Changes\Change;
use Wikibase\Repo\Hooks\WikibaseChangeNotificationHook;

class ChangeNotificationHook implements WikibaseChangeNotificationHook {

	public function __construct(
		private readonly Config $config,
		private readonly JobQueueGroup $jobQueueGroup,
	) {
	}

	public function onWikibaseChangeNotification( Change $change ): void {
		if ( in_array( $this->config->get( 'MathSearchPropertyProfileType' ),
			$change->getCompactDiff()->getStatementChanges(),
			true ) ) {
			$this->jobQueueGroup->lazyPush(
				( new PageCreation( [
						'rows' => [ $change->getObjectId() ],
						'username' => $change->getMetadata()['user_text'] ]
				) ) );
		}
	}
}
