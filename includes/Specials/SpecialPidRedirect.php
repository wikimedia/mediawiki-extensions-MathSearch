<?php

namespace MediaWiki\Extension\MathSearch\Specials;

use Exception;
use MediaWiki\Extension\MathSearch\Graph\Query;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\Repo\WikibaseRepo;

/**
 * Redirect to a page on the wiki regarding a persistent identifier (PID)
 *
 * @ingroup SpecialPage
 */
class SpecialPidRedirect extends SpecialPage {

	public function __construct() {
		parent::__construct( 'PidRedirect' );
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );
		$pid = $this->getRequest()->getInt( 'propertyId', 0 );
		$val = $this->getRequest()->getText( 'value' );
		$linkToItem = $this->getRequest()->getBool( 'item' );
		$siteId = $this->getRequest()->getText( 'siteId', 'mardi' );
		if ( $pid === 0 || $val === '' ) {
			$this->setHeaders();
			$this->getOutput()->addWikiTextAsContent( 'propertyId and value are required' );
			return;
		}
		$propertyId = NumericPropertyId::newFromNumber( $pid );
		$dataType = WikibaseRepo::getPropertyDataTypeLookup()->getDataTypeIdForProperty( $propertyId );
		if ( !in_array( $dataType, [ 'string', 'external-id' ] ) ) {
			$this->getOutput()->addWikiTextAsContent( 'Invalid property type: ' . $dataType );
			return;
		}
		$query = Query::getQidFromPid( '"' . $val . '"', 'P' . $pid );
		$results = Query::getResults( $query );
		foreach ( $results as $row ) {
			$qid = $row['qid'];
			if ( $linkToItem ) {
				$title = Title::makeTitle( 120, "Q$qid" );
			} else {
				$item = WikibaseRepo::getEntityLookup()->getEntity( ItemId::newFromNumber( $qid ) );

				if ( !$item instanceof Item ) {
					throw new Exception( "Item Q$qid not found." );
				}
				$link = $item->getSiteLink( $siteId );
				$title = Title::newFromText( $link->getPageName() );

			}
			$this->getOutput()->redirect( $title->getFullURL() );

		}
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}
