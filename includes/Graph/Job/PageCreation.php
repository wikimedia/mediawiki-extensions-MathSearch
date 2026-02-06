<?php
namespace MediaWiki\Extension\MathSearch\Graph\Job;

use MediaWiki\Content\ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use RuntimeException;
use Throwable;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Statement\V4GuidGenerator;
use Wikibase\DataModel\SiteLink;
use Wikibase\Repo\WikibaseRepo;

class PageCreation extends GraphJob {
	private readonly WikiPageFactory $wikiPageFactory;
	private readonly string $siteId;

	public function __construct( $params ) {
		parent::__construct( 'CreateProfilePages', $params );
		$this->wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$this->siteId = 'mardi';
	}

	public function run(): bool {
		$user = $this->getUser();
		$store = WikibaseRepo::getEntityStore();
		$lookup = WikibaseRepo::getEntityLookup();
		foreach ( $this->params['rows'] as $qid ) {
			try {
				self::getLog()->debug( "Processing page $qid." );
				$item = $lookup->getEntity( new ItemId( $qid ) );
				if ( !$item instanceof Item ) {
					self::getLog()->error( "Item $qid not found, or not an item." );
					continue;
				}
				$templateContent = $this->getTemplateContent( $item );
				$hasLinkToSite = $item->hasLinkToSite( $this->siteId );
				if ( $hasLinkToSite ) {
					self::getLog()->debug( "Page for $qid already exists." );
					$currentName = $item->getSiteLink( $this->siteId )->getPageName();
					$newTitle = $this->makeBetterTitle( $item, $currentName );
					if ( !$newTitle ) {
						self::getLog()->debug( "Current title for $qid is already optimal." );
						continue;
					}
					$newName = $newTitle->getText();
					self::getLog()->info( "Moving existing page " . $currentName . " to " . $newName . "." );
					$status = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage(
						Title::newFromText( $currentName ),
						$this->makeBetterTitle( $item )
					)->move( $user,
						'Move profile page according to new naming schema `' . $currentName . '`->`' . $newName
						. '` ' . $this->getJobname() );
					if ( !$status->isOK() ) {
						self::getLog()->error( "Could not move page for $qid: " . $status->getMessage()->text() );
						continue;
					}
					$item->removeSiteLink( $this->siteId );
				} else {
					self::getLog()->info( "Creating new page for $qid." );
					$newTitle = $this->makeBetterTitle( $item );
					$pageContent = ContentHandler::makeContent(
						$templateContent, $newTitle );
					$this->wikiPageFactory->newFromTitle( $newTitle )->doUserEditContent( $pageContent, $user,
						'Created automatically from ' . $this->getJobname() );
				}
				$siteLink = new SiteLink( $this->siteId, $newTitle->getPrefixedText() );
				$item->addSiteLink( $siteLink );
				self::getLog()->info( "Linking page $qid." );
				$store->saveEntity( $item, "Added link to MaRDI item.", $user, EDIT_FORCE_BOT );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip page processing page $qid.", [ $ex ] );
			}
		}

		return true;
	}

	private function makeBetterTitle( Item $item, string $currentName = '' ): ?Title {
		$label = str_replace( '#', '', $item->getLabels()->hasTermForLanguage( 'en' ) ?
			$item->getLabels()->getByLanguage( 'en' )->getText() :
			'' );
		$description = str_replace( '#', '', $item->getDescriptions()->hasTermForLanguage( 'en' ) ?
			$item->getDescriptions()->getByLanguage( 'en' )->getText() :
			'' );
		$id = $item->getId()->getSerialization();
		$titleOptions = [];
		$labelOrDescription = $label ?: $description;
		if ( $labelOrDescription ) {
			$titleOptions[] = $labelOrDescription;
		}
		if ( $label && $description ) {
			$titleOptions[] = $label . ' (' . $description . ')';
		}
		$titleOptions[] = $label . ' ' . $id;
		$titleOptions[] = $this->getPrefix( $item ) . ':' . str_replace( 'Q', '', $id );
		if ( $currentName === '' ) {
			$titleOptions[] = ( new V4GuidGenerator() )->newGuid();
		} else {
			// Normalize current name
			$t = Title::newFromText( $currentName );
			if ( $t !== null ) {
				$currentName = $t->getFullText();
			} else {
				self::getLog()->notice( "Current name $currentName cannot be parsed as title." );
			}
		}
		foreach ( $titleOptions as $titleOption ) {
			if ( $currentName === $titleOption ) {
				return null;
			}
			$t = Title::newFromText( $titleOption );
			if ( $t === null ) {
				continue;
			}
			if ( $t->getFullText() === $currentName ) {
				return null;
			}
			if ( !$t->exists() ) {
				return $t;
			}
			// treat redirects as non-existing pages
			if ( $t->isRedirect() ) {
				return $t;
			}
		}
		throw new RuntimeException( "Could not create unique title for item " . $id );
	}

	private function getPrefix( Item $item ): string {
		global $wgMathString2QMap, $wgMathSearchPropertyProfileType;
		$prefix = $this->params['prefix'] ?? false;
		if ( $prefix === false ) {
			try {
				$p = new NumericPropertyId( $wgMathSearchPropertyProfileType );
				$profileTypeStatements = $item->getStatements()->getByPropertyId( $p )->getMainSnaks();
				$profileType = $profileTypeStatements[0]->getDataValue()->getValue()->getEntityId()->getSerialization();
				$prefix = array_search( $profileType, $wgMathString2QMap[$wgMathSearchPropertyProfileType] );
			} catch ( Throwable $e ) {
				$prefix = '';
			}
			$this->params['prefix'] = $prefix;
		}
		return $prefix;
	}

	private function getJobname(): string {
		$jobname = $this->params['jobname'] ?? false;
		if ( $jobname === false ) {
			$this->params['jobname'] = date( 'ymdhms' );
		}
		return $jobname;
	}

	public function getTemplateContent( Item $item ): string {
		return '{{' . $this->getPrefix( $item ) . '}}';
	}

}
