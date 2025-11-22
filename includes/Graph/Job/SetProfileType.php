<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use Throwable;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\WikibaseRepo;

class SetProfileType extends GraphJob {
	public function __construct( $params ) {
		parent::__construct( 'SetProfileType', $params );
	}

	public function run(): bool {
		global $wgMathSearchPropertyProfileType;
		$user = $this->getUser();
		$store = WikibaseRepo::getEntityStore();
		$lookup = WikibaseRepo::getEntityLookup();
		$guidGenerator = new GuidGenerator();
		$pProfileType = new NumericPropertyId( "P$wgMathSearchPropertyProfileType" );
		$mainSnak = new PropertyValueSnak(
			$pProfileType,
			new EntityIdValue( new ItemId( $this->params['qType'] ) ) );
		foreach ( $this->params['rows'] as $qid ) {
			try {
				self::getLog()->info( "Add profile type for $qid." );
				$item = $lookup->getEntity( new ItemId( $qid ) );
				if ( $item === null ) {
					self::getLog()->error( "Item $qid not found." );
					continue;
				}
				/** @var StatementList $statements */
				$statements = $item->getStatements();
				$profileTypeStatements = $statements->getByPropertyId( $pProfileType );
				if ( !$profileTypeStatements->isEmpty() ) {
					if ( $this->params['overwrite'] ) {
						foreach ( $profileTypeStatements->getIterator() as $snak ) {
							$statements->removeStatementsWithGuid( $snak->getGuid() );
						}
					} else {
						self::getLog()->info( "Skip page $qid." );
						continue;
					}
				}
				$statements->addNewStatement(
					$mainSnak,
					[],
					null,
					$guidGenerator->newGuid( $item->getId() ) );
				$item->setStatements( $statements );
				$store->saveEntity( $item, "Set profile property.", $user,
					EDIT_FORCE_BOT );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip page processing page $qid.", [ $ex ] );
			}
		}

		return true;
	}
}
