<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use DataValues\MonolingualTextValue;
use DataValues\StringValue;
use MediaWiki\Extension\MathSearch\Graph\Query;
use Throwable;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\WikibaseRepo;

class OpenAlex extends GraphJob {
	private EntityStore $entityStore;
	private EntityLookup $entityLookup;
	private GuidGenerator $guidGenerator;

	/** @var array<string,NumericPropertyId> */
	private array $propertyIds = [];

	public function __construct( $params ) {
		parent::__construct( 'OpenAlex', $params );
		$this->entityStore = WikibaseRepo::getEntityStore();
		$this->entityLookup = WikibaseRepo::getEntityLookup();
		$this->guidGenerator = new GuidGenerator();
	}

	public function run(): bool {
		$qIdMap = Query::getDeQIdMap( $this->params['rows'] );
		foreach ( $this->params['rows'] as $de => $row ) {
			try {
				if ( !isset( $qIdMap[(string)$de] ) ) {
					self::getLog()->error( "No Qid found for Zbl $de." );
					continue;
				}
				$this->processRow( $de, $qIdMap[$de], $row );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip processing Zbl $de", [ 'error' => $ex ] );
			}
		}

		return true;
	}

	private function processRow( string $de, string $qid, \stdClass $row ) {
		global $wgMathOpenAlexQIdMap;
		$pDe = $this->getNumericPropertyId( $wgMathOpenAlexQIdMap['document'] );
		self::getLog()->info( "Add OpenAlex data for Zbl $de to $qid." );
		$item = $this->entityLookup->getEntity( new ItemId( $qid ) );
		if ( !$item instanceof Item ) {
			self::getLog()->error( "Item Q$qid not found." );
			return;
		}
		$statements = $item->getStatements();
		$profileTypeStatements = $statements->getByPropertyId( $pDe )->getMainSnaks();
		if ( count( $profileTypeStatements ) !== 1 ) {
			self::getLog()->error( "No or multiple statements found for Zbl $de." );
			return;
		}
		$mainSnak = $profileTypeStatements[0];
		if ( !$mainSnak->getDataValue()->getValue() == $de ) {
			self::getLog()->error( "Wrong statement found for Zbl $de." );
			return;
		}
		$changed = false;
		foreach ( $row as $key => $value ) {
			$propertyId = $this->getNumericPropertyId( $key );
			$profileTypeStatements = $statements->getByPropertyId( $propertyId );
			if ( !$profileTypeStatements->isEmpty() ) {
				self::getLog()->debug( "$key of Zbl $de already set." );
				continue;
			}
			if ( $key === $wgMathOpenAlexQIdMap['openalex_title'] && $item->getLabels()->isEmpty() ) {
				$item->setLabel( 'en', $value );
			}

			$changed = true;

			$mainSnak = new PropertyValueSnak(
				$propertyId,
				$key === $wgMathOpenAlexQIdMap['openalex_title'] ?
					new MonolingualTextValue( 'en', $value )
					: new StringValue( $value
				) );
			$statements->addNewStatement( $mainSnak, [], null,
				$this->guidGenerator->newGuid( $item->getId() ) );
		}
		if ( $changed === false ) {
			self::getLog()->info( "Skip Zbl $de  (no change)." );
			return;
		}
		$item->setStatements( $statements );
		$this->entityStore->saveEntity( $item, "Set OpenAlex properties.", $this->getUser(),
			EDIT_FORCE_BOT );
	}

	public function getNumericPropertyId( string $key ): NumericPropertyId {
		$this->propertyIds[$key] ??= new NumericPropertyId( $key );
		return $this->propertyIds[$key];
	}
}
