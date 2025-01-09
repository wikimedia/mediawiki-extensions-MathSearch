<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\WikibaseRepo;

class QuickStatements extends GraphJob {
	private EntityStore $entityStore;
	private EntityLookup $entityLookup;
	private GuidGenerator $guidGenerator;

	/** @var array<string,NumericPropertyId> */
	private array $propertyIds = [];

	public function __construct( $params ) {
		parent::__construct( 'QuickStatements', $params );
		$this->entityStore = WikibaseRepo::getEntityStore();
		$this->entityLookup = WikibaseRepo::getEntityLookup();
		$this->guidGenerator = new GuidGenerator();
	}

	public function validateProperty( string $key ) {
		$propertyLookup = WikibaseRepo::getPropertyInfoLookup();
		return $propertyLookup->getPropertyInfo( $this->getNumericPropertyId( $key ) );
	}

	public function run() {
	}

	public function getNumericPropertyId( string $key ): NumericPropertyId {
		$this->propertyIds[$key] ??= new NumericPropertyId( $key );
		return $this->propertyIds[$key];
	}
}
