<?php
namespace MediaWiki\Extension\MathSearch\Graph\Job;

use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use RuntimeException;
use Throwable;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Statement\V4GuidGenerator;
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
				$item = $lookup->getEntity( new ItemId( $qid ) );
				if ( $item === null ) {
					self::getLog()->error( "Item Q$qid not found." );
					continue;
				}
				$newTitle = $this->makeTitle( $item );
				if ( $item->hasLinkToSite( 'mardi' ) ) {
					self::getLog()->info( "Page for $qid already exists." );
					if ( $item->getSiteLink( 'mardi' )->getPageName() === $newTitle->getPrefixedText() ) {
						continue;
					}
					self::getLog()->info( "Moving existing page to " . $newTitle->getText() . "." );
					$status = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage(
						Title::newFromText( $item->getSiteLink( 'mardi' )->getPageName() ),
						$this->makeTitle( $item )
					)->move( $user,
						'Move profile page according to new naming schema. Job ' . $this->params['jobname'] );
					if ( !$status->isOK() ) {
						self::getLog()->error( "Could not move page for $qid: " . $status->getMessage()->text() );
						continue;
					}
					$item->removeSiteLink( 'mardi' );
				} else {
					self::getLog()->info( "Creating new page for $qid." );
					$pageContent = ContentHandler::makeContent(
						$this->getTemplateContent( $item ), $newTitle );
					$pageFactory->newFromTitle( $newTitle )->doUserEditContent( $pageContent, $user,
						'Created automatically from ' . $this->params['jobname'] );
				}
				$siteLink = new SiteLink( 'mardi', $newTitle->getPrefixedText() );
				$item->addSiteLink( $siteLink );
				self::getLog()->info( "Linking page $qid." );
				$store->saveEntity( $item, "Added link to MaRDI item.", $user, EDIT_FORCE_BOT );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip page processing page $qid.", [ $ex ] );
			}
		}

		return true;
	}

	private function makeTitle( Item $item ): Title {
		$label = $item->getLabels()->hasTermForLanguage( 'en' ) ?
			$item->getLabels()->getByLanguage( 'en' )->getText() :
			'';
		$description = $item->getDescriptions()->hasTermForLanguage( 'en' ) ?
			$item->getDescriptions()->getByLanguage( 'en' )->getText() :
			'';
		$id = $item->getId()->getSerialization();
		$titleOptions = [
			$label ?: $description,
			$label . '_(' . $description . ')',
			$label . '_' . $id,
			$this->params['prefix'] . ':' . str_replace( 'Q', '', $id ),
			( new V4GuidGenerator() )->newGuid()
		];

		foreach ( $titleOptions as $titleOption ) {
			$t = $this->checkTitle( $titleOption );
			if ( $t !== null ) {
				return $t;
			}
		}
		throw new RuntimeException( "Could not create unique title for item " . $id );
	}

	private function checkTitle( string $title ): ?Title {
		if ( preg_match( TitleParser::getTitleInvalidRegex(), $title ) === 1 ) {
			self::getLog()->info( "Title for $title contains invalid chars.", [ $title ] );
		}
		$t = Title::newFromText( $title );
		if ( $t === null || $t->exists() ) {
			return null;
		}
		return $t;
	}

	public function getTemplateContent( Item $item ): string {
		global $wgMathString2QMap, $wgMathSearchPropertyProfileType;
		$prefix = $this->params['prefix'];
		if ( !$prefix ) {
			try {
				$p = new NumericPropertyId( "P$wgMathSearchPropertyProfileType" );
				$profileTypeStatements = $item->getStatements()->getByPropertyId( $p )->getMainSnaks();
				$profileType = $profileTypeStatements[0]->getDataValue()->getValue()->getEntityId()->getSerialization();
				$prefix = array_search( $profileType, $wgMathString2QMap["P" . $wgMathSearchPropertyProfileType] );
			} catch ( Throwable $e ) {
				// ignore
			}

		}
		return '{{' . $prefix . '}}';
	}

}
