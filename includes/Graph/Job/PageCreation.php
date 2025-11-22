<?php
namespace MediaWiki\Extension\MathSearch\Graph\Job;

use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Throwable;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\Repo\WikibaseRepo;

class PageCreation extends GraphJob {
	public function __construct( $params ) {
		parent::__construct( 'CreateProfilePages', $params );
	}

	public function run(): bool {
		$user = $this->getUser();

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
				$item = $lookup->getEntity( new ItemId( $qid ) );
				if ( $this->params['overwrite'] ?? false ) {
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
