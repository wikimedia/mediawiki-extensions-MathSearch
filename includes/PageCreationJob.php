<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\Repo\WikibaseRepo;

class PageCreationJob extends \Job {

	public function __construct( $title, $params ) {
		parent::__construct( 'CreateProfilePages', $title, $params );
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

		foreach ( $this->params['rows'] as $row ) {
			$qid = $row['qID'];
			$name = $row['title'];
			$prefix = $row['prefix'];
			self::getLog()->info( "Creating page $name." );
			$title = Title::newFromText( $prefix . $name );
			$pageContent = ContentHandler::makeContent( '{{Formula}}', $title );
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title )
				->doUserEditContent( $pageContent, $user,
					'Created automatically from ' . $this->params['jobname'] );
			$store = WikibaseRepo::getEntityStore();
			$lookup = WikibaseRepo::getEntityLookup();
			$item = $lookup->getEntity( ItemId::newFromNumber( $qid ) );
			$siteLink = new SiteLink( 'mardi', $title->getBaseText() );
			$item->addSiteLink( $siteLink );
			self::getLog()->info( "Linking page $name to $qid." );
			$store->saveEntity( $item, "Added link to MaRDI item.",
				$user );
		}

		return true;
	}

}
