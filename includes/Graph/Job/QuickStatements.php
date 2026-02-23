<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use Exception;
use MediaWiki\Extension\MathSearch\Graph\PidLookup;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\Sparql\SparqlException;
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
	private readonly EntityStore $entityStore;
	private readonly EntityLookup $entityLookup;
	private readonly GuidGenerator $guidGenerator;

	/** @var array<string,NumericPropertyId> */
	private array $propertyIds = [];
	/** @var array<string,string> */
	private array $propertyTypes = [];
	private readonly PropertyDataTypeLookup $propertyDataTypeLookup;
	private readonly DataTypeFactory $dataTypeFactory;

	private readonly LanguageNameUtils $languageNameUtils;
	private array $qid_cache = [];

	public function __construct( $params ) {
		parent::__construct( 'QuickStatements', $params );
		$this->entityStore = WikibaseRepo::getEntityStore();
		$this->entityLookup = WikibaseRepo::getEntityLookup();
		$this->guidGenerator = new GuidGenerator();
		$this->propertyDataTypeLookup = WikibaseRepo::getPropertyDataTypeLookup();
		$this->dataTypeFactory = WikibaseRepo::getDataTypeFactory();
		$this->languageNameUtils = MediaWikiServices::getInstance()->getLanguageNameUtils();
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
				if ( $item !== null ) {
					$this->processRow( $row, $item );
				}
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip row: {$ex->getMessage()}", [ 'exception' => $ex, 'row' => $row ] );
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
		$currentStatementKey = 0;
		$newStatements = [];
		$optionalStatements = [];
		$textChanges = false;
		foreach ( $row as $P => $value ) {
			// ignore suffixes (in SPARQL one cannot use the same column header twice)
			$P = preg_replace( '/(.*)_(\d+)/i', '$1', $P );
			$optionalField = in_array( $P, $this->params['optional_fields'] ?? [], true );
			if ( str_starts_with( $P, 'P' ) ) {
				$currentStatementKey++;
			} elseif ( str_starts_with( $P, 'qal' ) ) {
				if ( !isset( $newStatements[$currentStatementKey] ) ) {
					self::getLog()->warning( "Skip qualifier without main statement.", [ $P ] );
					continue;
				}
				$newStatements[$currentStatementKey][1][] = $this->getSnak(
					'P' . substr( $P, 3 ),
					$value );
				continue;
			} elseif ( str_starts_with( $P, 'L' ) ) {
				$languageCode = substr( $P, 1 );
				if ( $this->languageNameUtils->isValidCode( $languageCode ) ) {
					if ( $optionalField ) {
						if ( !$item->getLabels()->hasTermForLanguage( $languageCode ) ) {
							$item->setLabel( $languageCode, $value );
						}
						continue;
					}
					$textChanges = true;
					$item->setLabel( $languageCode, $value );
				} else {
					self::getLog()->warning( "Skip invalid language code.", [ $P ] );
				}
				continue;
			} elseif ( str_starts_with( $P, 'D' ) ) {
				$languageCode = substr( $P, 1 );
				if ( $this->languageNameUtils->isValidCode( $languageCode ) ) {
					if ( !$optionalField ) {
						$textChanges = true;
					}
					$item->setDescription( $languageCode, $value );
				} else {
					self::getLog()->warning( "Skip invalid language code.", [ $P ] );
				}
				continue;
			} else {
				self::getLog()->warning( "Skip invalid field name.", [ $P ] );
				continue;
			}
			$matches = null;
			if ( preg_match( '/P(?P<p>\d+)q(?P<q>\d+)/i', $P, $matches ) ) {
				$transformed = $this->getPidCache( $matches['q'] )->getQ( $value );
				if ( $transformed === false ) {
					self::getLog()->info( "Reference $value could not be found. Skipping {P}.", [ 'P' => $P ] );
					continue;
				}
				$value = $transformed;
				$P = "P{$matches['p']}";
			}
			$propertyId = $this->getNumericPropertyId( $P );
			$currentStatements = $statements->getByPropertyId( $propertyId );
			if ( !$currentStatements->isEmpty() &&
				!$this->removeOldStatements( $currentStatements, $value, $statements ) ) {
				continue;
			}
			$newStatement = [
				$this->getSnak( $P, $value ),
				[],
				null,
				$this->guidGenerator->newGuid( $item->getId() )
			];
			if ( $optionalField ) {
				$optionalStatements[$currentStatementKey] = $newStatement;
			} else {
				$newStatements[$currentStatementKey] = $newStatement;
			}
		}
		if ( count( $newStatements ) === 0 && !$textChanges ) {
			self::getLog()->info( "Skip row (no change)." );
			return;
		}
		$newStatements += $optionalStatements;
		foreach ( $newStatements as $statement ) {
			$statements->addNewStatement( ...$statement );
		}
		$item->setStatements( $statements );
		$this->entityStore->saveEntity(
			$item,
			$this->getEditSummary(),
			$this->getUser(),
			EDIT_FORCE_BOT );
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
				if ( preg_match(
					'#^(?<date>[+-]\d{4,}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)/(?<precision>\d+)$#',
					$value,
					$m ) ) {
					$date = $m['date'];
					$precision = (int)$m['precision'];
				} else {
					$date = '+' . substr( $value, 0, -4 ) . 'Z';
					$precision = 11;
				}
				$value = [
					'time' => $date,
					'timezone' => 0,
					'before' => 0,
					'after' => 0,
					'precision' => $precision,
					'calendarmodel' => 'http://www.wikidata.org/entity/Q1985727',
				];
				break;
			case 'monolingualtext':
				$value = [ 'language' => 'en', 'text' => $value ];
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

	private function getPidCache( int $pid ): PidLookup {
		if ( !isset( $this->qid_cache[$pid] ) ) {
			$this->qid_cache[$pid] = new PidLookup( "P$pid" );
		}
		return $this->qid_cache[$pid];
	}

	private function getRowItem( array &$row ): ?Item {
		if ( !isset( $row['qid'] ) ) {
			return $this->getRowItemFromPID( $row );
		}
		if ( $row['qid'] === '' ) {
			$item = new Item();
			$this->entityStore->assignFreshId( $item );
			unset( $row['qid'] );
			return $item;
		}
		$qID = preg_replace( '/.*?(Q\d+)/i', '$1', $row['qid'] );
		$item = $this->entityLookup->getEntity( new ItemId( $qID ) );
		if ( !$item instanceof Item ) {
			throw new Exception( "Item $qID not found." );
		}
		unset( $row['qid'] );
		return $item;
	}

	private function removeOldStatements(
		StatementList $currentStatements,
		mixed $value,
		StatementList $statements ): bool {
		$overwrite = $this->params['overwrite'] ?? true;
		if ( $overwrite && count( $currentStatements ) > 1 ) {
			self::getLog()->warning( "Skip row (multiple statements)." );
			return false;
		}
		foreach ( $currentStatements as $statement ) {
			$snak = $statement->getMainSnak();
			if ( $snak instanceof PropertyValueSnak &&
				$snak->getDataValue()->getValue() == $value ) {
				self::getLog()->info( "Skip statement (no change)." );
				return false;
			}
			if ( $overwrite ) {
				// there is at most one statement that will be removed
				$statements->removeStatementsWithGuid( $statement->getGuid() );
			}
		}
		return true;
	}

	/**
	 * @throws SparqlException
	 * @throws Exception
	 */
	private function getRowItemFromPID( array &$row ): ?Item {
		foreach ( $row as $key => $value ) {
			$deleteItem = str_starts_with( $key, '-qP' );
			if ( str_starts_with( $key, 'qP' ) || $deleteItem ) {
				$offset = $deleteItem ? 3 : 2;
				$pidLookup = $this->getPidCache( substr( $key, $offset ) );
				if ( $pidLookup->count() === 0 ) {
					$values = $this->getValuesFromColumn( $key );
					$pidLookup->warmupFromValues( $values );
				}
				$q = $pidLookup->getQ( $value );
				if ( $q === false && ( $this->params['create_missing'] ?? false ) ) {
					$item = new Item();
					$this->entityStore->assignFreshId( $item );
					$statements = $item->getStatements();
					$statements->addNewStatement(
						$this->getSnak( substr( $key, 1 ), $value ),
						[],
						null,
						$this->guidGenerator->newGuid( $item->getId() )
					);
					$pidLookup->overwrite( $value, $item->getId() );
				} elseif ( $q !== false ) {
					$item = $this->entityLookup->getEntity( new ItemId( $q ) );
				} else {
					$item = false;
				}
				if ( !$item instanceof Item ) {
					throw new Exception( "Item Q$q not found." );
				}
				unset( $row[$key] );
				if ( $deleteItem ) {
					$this->entityStore->deleteEntity(
						$item->getId(),
						$this->getEditSummary(),
						$this->getUser() );
					return null;
				}
				return $item;
			}
		}
		throw new Exception( "No Item element not found." );
	}

	private function getValuesFromColumn( string $key ): array {
		$values = [];
		foreach ( $this->params['rows'] as $row ) {
			$values[] = $row[$key];
		}
		return $values;
	}

	/**
	 * @return mixed|string
	 */
	public function getEditSummary(): mixed {
		return $this->params['editsummary'] ?? 'job ' . $this->params['jobname'];
	}

}
