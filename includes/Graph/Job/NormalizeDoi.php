<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use DataValues\StringValue;
use Throwable;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\WikibaseRepo;

class NormalizeDoi extends GraphJob {
	private int $pDoi;

	public function __construct( $params ) {
		global $wgMathSearchPropertyDoi;
		parent::__construct( 'NormalizeDoi', $params );
		$this->pDoi = $this->params['pDoi'] ?? $wgMathSearchPropertyDoi;
	}

	public function run(): bool {
		$user = $this->getUser();
		$store = WikibaseRepo::getEntityStore();
		$lookup = WikibaseRepo::getEntityLookup();
		$guidGenerator = new GuidGenerator();
		$pDOI = NumericPropertyId::newFromNumber( $this->pDoi );
		foreach ( $this->params['rows'] as $qid => $doi ) {
			try {
				self::getLog()->info( "Update DOI for $qid." );
				$item = $lookup->getEntity( ItemId::newFromNumber( $qid ) );
				if ( $item === null ) {
					self::getLog()->error( "Item Q$qid not found." );
					continue;
				}
				/** @var StatementList $statements */
				$statements = $item->getStatements();
				$doiStatements = $statements->getByPropertyId( $pDOI );
				if ( $doiStatements->count() === 0 ) {
					self::getLog()->error( "Skip page processing page Q$qid. Only 1 ore more DOI are supported" );
					continue;
				}
				$changed = false;
				foreach ( $doiStatements->getIterator() as $snak ) {
					$val = $snak->getMainSnak()->getDataValue()->getValue();
					$upper = strtoupper( $val );
					if ( $val === $upper ) {
						continue;
					}
					$changed = true;
					$statements->removeStatementsWithGuid( $snak->getGuid() );
					$mainSnak = new PropertyValueSnak(
						$pDOI,
						new StringValue( $upper ) );
					$statements->addNewStatement(
						$mainSnak,
						[],
						null,
						$guidGenerator->newGuid( $item->getId() ) );
				}
				if ( !$changed ) {
					self::getLog()->warning( "Nothing changed for Q$qid." );
					continue;
				}
				$item->setStatements( $statements );
				$store->saveEntity( $item, "Normalize DOI.", $user,
					EDIT_FORCE_BOT );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip page processing page Q$qid.", [ $ex ] );
			}
		}

		return true;
	}
}
