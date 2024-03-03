<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\Repo\WikibaseRepo;

class PageCreationJob extends Job implements GenericParameterJob {
	public function __construct( $params ) {
		parent::__construct( 'CreateProfilePages', $params );
	}

	private static function getLog() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	public function run(): bool {
		$user = MediaWikiServices::getInstance()->getUserFactory()
			->newFromName( $this->params['jobname'] );
		$exists = ( $user->idForName() !== 0 );
		if ( !$exists ) {
			MediaWikiServices::getInstance()->getAuthManager()->autoCreateUser(
				$user,
				MediaWiki\Auth\AuthManager::AUTOCREATE_SOURCE_MAINT,
				false
			);
		}
		$store = WikibaseRepo::getEntityStore();
		$lookup = WikibaseRepo::getEntityLookup();
		$pageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		foreach ( $this->params['rows'] as $qid ) {
			try {
				self::getLog()->info( "Creating page $qid." );
				$title = Title::newFromText( $this->params['prefix'] . ':' . $qid );
				$pageContent = ContentHandler::makeContent(
					'{{' . $this->params['prefix'] . '}}', $title );
				$pageFactory->newFromTitle( $title )
					->doUserEditContent( $pageContent, $user,
						'Created automatically from ' . $this->params['jobname'] );
				$item = $lookup->getEntity( ItemId::newFromNumber( $qid ) );
				if ( $this->params['overwrite'] ) {
					$item->removeSiteLink( 'mardi' );
				}
				$siteLink = new SiteLink( 'mardi', $title->getPrefixedText() );
				$item->addSiteLink( $siteLink );
				self::getLog()->info( "Linking page $qid." );
				$store->saveEntity( $item, "Added link to MaRDI item.", $user, EDIT_FORCE_BOT );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip page processing page Q$qid.", [ $ex ] );
			}
		}

		return true;
	}

}
