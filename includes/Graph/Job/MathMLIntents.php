<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use DataValues\QuantityValue;
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

class MathMLIntents extends GraphJob {
	private EntityStore $entityStore;
	private EntityLookup $entityLookup;
	private GuidGenerator $guidGenerator;

	/** @var array<string,NumericPropertyId> */
	private array $propertyIds = [];

	public function __construct( $params ) {
		parent::__construct( 'MathMLIntents', $params );
		$this->entityStore = WikibaseRepo::getEntityStore();
		$this->entityLookup = WikibaseRepo::getEntityLookup();
		$this->guidGenerator = new GuidGenerator();
	}

	public function run(): bool {
		$qIdMap = $this->getConceptIdMap();
		foreach ( $this->params['rows'] as $concept => $row ) {
			try {
				$qid = $qIdMap[$concept] ?? false;
				if ( $qid === false ) {
					self::getLog()->debug( "No Qid found for $concept." );
				}
				$this->processRow( $concept, $qid, $row );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip processing $concept", [ 'error' => $ex ] );
			}
		}

		return true;
	}

	private function getConceptIdMap(): array {
		$concepts = '"' . implode( '" "', array_keys( $this->params['rows'] ) ) . '"';
		$query = Query::getQidFromConcept( $concepts );
		$rs = Query::getResults( $query );
		$qIdMap = [];
		foreach ( $rs as $row ) {
			$de = $row['de'];
			if ( isset( $qIdMap[$de] ) ) {
				self::getLog()->error( "Multiple Qid found for Zbl $de." );
				unset( $this->params['rows'][$de] );
				continue;
			}
			$qIdMap[$de] = $row['qid'];
		}

		return $qIdMap;
	}

	private function processRow( string $concept, int $qid, \stdClass $row ) {
		global $wgMathIntentsQIdMap;
		$pDe = $this->getNumericPropertyId( $wgMathIntentsQIdMap['concept'] );
		self::getLog()->info( "Add OpenAlex data for Zbl $concept to $qid." );
		if ( $qid ) {
			$item = $this->entityLookup->getEntity( ItemId::newFromNumber( $qid ) );
			if ( !$item instanceof Item ) {
				self::getLog()->error( "Item Q$qid not found." );
				return;
			}
			$statements = $item->getStatements();
			$profileTypeStatements = $statements->getByPropertyId( $pDe )->getMainSnaks();
			if ( count( $profileTypeStatements ) !== 1 ) {
				self::getLog()->error( "No or multiple statements found for concept $concept." );
				return;
			}
			$mainSnak = $profileTypeStatements[0];
			if ( !$mainSnak->getDataValue()->getValue() == $concept ) {
				self::getLog()->error( "Wrong statement found for concept $concept." );
				return;
			}
			$changed = false;
		} else {
			$item = new Item();
			$this->entityStore->assignFreshId( $item );
			$statements = $item->getStatements();
			$changed = true;
		}
		foreach ( $row['content'] as $k => $value ) {
			if ( !array_key_exists( $k, $wgMathIntentsQIdMap ) ) {
				continue;
			}
			$key = $wgMathIntentsQIdMap[$k];
			$propertyId = $this->getNumericPropertyId( $key );
			$profileTypeStatements = $statements->getByPropertyId( $propertyId );
			if ( !$profileTypeStatements->isEmpty() ) {
				self::getLog()->debug( "$key of concept $concept already set." );
				continue;
			}

			$changed = true;

			$mainSnak = new PropertyValueSnak(
				$propertyId,
				$key === $wgMathIntentsQIdMap['arity'] ?
					QuantityValue::newFromNumber( $value )
					: new StringValue( $value )
			);
			$statements->addNewStatement( $mainSnak, [], null,
				$this->guidGenerator->newGuid( $item->getId() ) );
		}
		if ( $changed === false ) {
			self::getLog()->info( "Skip Zbl $concept  (no change)." );
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
