<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use Exception;
use Throwable;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\DataTypeFactory;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\WikibaseRepo;

class QuickStatements extends GraphJob {
	private EntityStore $entityStore;
	private EntityLookup $entityLookup;
	private GuidGenerator $guidGenerator;

	/** @var array<string,NumericPropertyId> */
	private array $propertyIds = [];
	/** @var array<string,string> */
	private array $propertyTypes = [];
	private PropertyDataTypeLookup $propertyDataTypeLookup;
	private DataTypeFactory $dataTypeFactory;

	public function __construct( $params ) {
		parent::__construct( 'QuickStatements', $params );
		$this->entityStore = WikibaseRepo::getEntityStore();
		$this->entityLookup = WikibaseRepo::getEntityLookup();
		$this->guidGenerator = new GuidGenerator();
		$this->propertyDataTypeLookup = WikibaseRepo::getPropertyDataTypeLookup();
		$this->dataTypeFactory = WikibaseRepo::getDataTypeFactory();
	}

	public function validateProperty( string $key ) {
		$propertyInfo = [];
		$propertyId = $this->getNumericPropertyId( $key );
		$termLookup = WikibaseRepo::getTermLookup();
		$label = $termLookup->getLabel( $propertyId, 'en' );
		if ( $label !== null ) {
			$propertyInfo['label'] = $label;
		} else {
			$propertyInfo['label'] = $propertyId->getSerialization();
		}
		$propertyInfo['type'] = $this->getPropertyType( $key );

		return $propertyInfo;
	}

	public function testSnak( string $key, mixed $value ) {
		try {
			$example = $this->getSnak( $key, $value );
			return $example->serialize();
		} catch ( Exception $e ) {
			return "Serialization failed: " . $e->getMessage();
		}
	}

	public function run() {
		foreach ( $this->params['rows'] as $row ) {
			try {
				$item = $this->getRowItem( $row );
				$this->processRow( $row, $item );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip row", [ 'error' => $ex, 'row' => $row ] );
			}
		}
		return true;
	}

	public function getNumericPropertyId( string $key ): NumericPropertyId {
		$this->propertyIds[$key] ??= new NumericPropertyId( $key );
		return $this->propertyIds[$key];
	}

	public function getPropertyType( string $key ): string {
		$this->propertyTypes[$key] ??= $this->propertyDataTypeLookup->getDataTypeIdForProperty(
			$this->getNumericPropertyId( $key )
		);
		return $this->propertyTypes[$key];
	}

	private function processRow( array $row, Item $item ) {
		$statements = $item->getStatements();
		$currentProperty = null;
		$newStatements = [];
		foreach ( $row as $P => $value ) {
			if ( str_starts_with( $P, 'P' ) ) {
				$currentProperty = $P;
			} elseif ( str_starts_with( $P, 'qal' ) ) {
				$newStatements[$currentProperty][1][] = $this->getSnak(
					'P' . substr( $P, 3 ),
					$value );
				continue;
			}
			$propertyId = $this->getNumericPropertyId( $P );
			$currentStatements = $statements->getByPropertyId( $propertyId );
			if ( !$currentStatements->isEmpty() &&
				!$this->removeOldStatements( $currentStatements, $value, $statements ) ) {
				continue;
			}
			$newStatements[ $currentProperty ] = [
				$this->getSnak( $P, $value ),
				[],
				null,
				$this->guidGenerator->newGuid( $item->getId() )
				];

		}
		if ( count( $newStatements ) === 0 ) {
			self::getLog()->info( "Skip row (no change)." );
			return;
		}
		foreach ( $newStatements as $statement ) {
			$statements->addNewStatement( ...$statement );
		}
		$item->setStatements( $statements );
		$this->entityStore->saveEntity( $item, $this->params['editsummary'], $this->getUser(), EDIT_FORCE_BOT );
	}

	private function getSnak( string $propertyKey, mixed $value ): Snak {
		$propertyId = $this->getNumericPropertyId( $propertyKey );
		$type = $this->getPropertyType( $propertyKey );
		switch ( $type ) {
			case 'wikibase-item':
				if ( str_starts_with( $value, 'Q' ) ) {
					$value = substr( $value, 1 );
				}
				$value = [ 'entity-type' => 'item', 'numeric-id' => (int)$value ];
				break;
			case 'quantity':
				$value = [ 'unit' => '1', 'amount' => $value ];
				break;
			case 'time':
				$value = [
					'time' => '+' . substr( $value, 0, -4 ) . 'Z',
					'timezone' => 0,
					'before' => 0,
					'after' => 0,
					'precision' => 11,
					'calendarmodel' => 'http://www.wikidata.org/entity/Q1985727',
				];
				break;
			case 'string':
			case 'external-id':
				break;
			default:
				self::getLog()->warning( "Unexpected type" . $type );
		}
		$this->dataTypeFactory->getType( $type )->getDataValueType();
		$value = [ 'type' => $this->dataTypeFactory->getType( $type )->getDataValueType(), 'value' => $value ];
		return new PropertyValueSnak( $propertyId,
			WikibaseRepo::getDataValueDeserializer()->deserialize( $value ) );
	}

	private function getRowItem( array &$row ): Item {
		$qID = preg_replace( '/.*?Q?(\d+)/i', '$1', $row['qid'] );
		$item = $this->entityLookup->getEntity( ItemId::newFromNumber( $qID ) );
		if ( !$item instanceof Item ) {
			throw new Exception( "Item Q$qID not found." );
		}
		unset( $row['qid'] );
		return $item;
	}

	private function removeOldStatements(
		StatementList $currentStatements,
		mixed $value,
		StatementList $statements ): bool {
		if ( count( $currentStatements ) > 1 ) {
			self::getLog()->warning( "Skip row (multiple statements)." );
			return false;
		}
		$statement = $currentStatements->toArray()[0];
		$snak = $statement->getMainSnak();
		if ( $snak instanceof PropertyValueSnak &&
			$snak->getDataValue()->getValue() == $value ) {
			self::getLog()->info( "Skip row (no change)." );
			return false;
		}
		$statements->removeStatementsWithGuid( $statement->getGuid() );
		return true;
	}
}
