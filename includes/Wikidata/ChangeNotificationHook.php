<?php

namespace MediaWiki\Extension\MathSearch\Wikidata;

use MediaWiki\Config\Config;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Logger\LoggerFactory;
use Throwable;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Changes\Change;
use Wikibase\Lib\Changes\EntityChange;
use Wikibase\Repo\Hooks\WikibaseChangeNotificationHook;

class ChangeNotificationHook implements WikibaseChangeNotificationHook {

	public function __construct(
		private readonly Config $config,
		private readonly JobQueueGroup $jobQueueGroup,
	) {
	}

	public function onWikibaseChangeNotification( Change $change ): void {
		// Runs on every Wikibase edit; a failure here must not break the save.
		try {
			$this->handleChange( $change );
		} catch ( Throwable $e ) {
			LoggerFactory::getInstance( 'MathSearch' )->error(
				'Profile page update failed for {id}', [ 'id' => $change->getObjectId(), 'exception' => $e ]
			);
		}
	}

	private function handleChange( Change $change ): void {
		// PageCreation only handles items.
		if ( !$change instanceof EntityChange || !$change->getEntityId() instanceof ItemId ) {
			return;
		}
		$diff = $change->getCompactDiff();
		$profileTypeChanged = in_array( $this->config->get( 'MathSearchPropertyProfileType' ),
			$diff->getStatementChanges(), true );
		$labelChanged = in_array( 'en', $diff->getLabelChanges(), true );
		if ( !$profileTypeChanged && !$labelChanged ) {
			return;
		}
		$params = [
			'rows' => [ $change->getObjectId() ],
			'username' => $change->getMetadata()['user_text'],
			'jobname' => $change->getObjectId()
		];
		if ( !$profileTypeChanged ) {
			// Moves the profile page to the new label; skips items without a profile type.
			$params['variable_prefix'] = true;
		}
		$this->jobQueueGroup->lazyPush( new JobSpecification( 'CreateProfilePages', $params ) );
	}
}
