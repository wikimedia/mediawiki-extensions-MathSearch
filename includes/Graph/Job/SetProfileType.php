<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use GenericParameterJob;
use Job;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Throwable;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\WikibaseRepo;

class SetProfileType extends Job implements GenericParameterJob {
	public function __construct( $params ) {
		parent::__construct( 'SetProfileType', $params );
	}

	private static function getLog() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	public function run(): bool {
		global $wgMathSearchPropertyProfileType;
		$user = MediaWikiServices::getInstance()->getUserFactory()
			->newFromName( $this->params['jobname'] );
		$exists = ( $user->idForName() !== 0 );
		if ( !$exists ) {
			MediaWikiServices::getInstance()->getAuthManager()->autoCreateUser(
				$user,
				AuthManager::AUTOCREATE_SOURCE_MAINT,
				false
			);
		}
		$store = WikibaseRepo::getEntityStore();
		$lookup = WikibaseRepo::getEntityLookup();
		$guidGenerator = new GuidGenerator();
		$mainSnak = new PropertyValueSnak(
			NumericPropertyId::newFromNumber( $wgMathSearchPropertyProfileType ),
			new EntityIdValue( new ItemId( $this->params['qType'] ) ) );
		foreach ( $this->params['rows'] as $qid ) {
			try {
				self::getLog()->info( "Add profile type for $qid." );
				$item = $lookup->getEntity( ItemId::newFromNumber( $qid ) );
				if ( $item === null ) {
					self::getLog()->error( "Item Q$qid not found." );
					continue;
				}
				/** @var StatementList $statements */
				$statements = $item->getStatements();
				$statements->addNewStatement(
					$mainSnak,
					[],
					null,
					$guidGenerator->newGuid( $item->getId() ) );
				$item->setStatements( $statements );
				$store->saveEntity( $item, "Added link to MaRDI item.", $user );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip page processing page Q$qid.", [ $ex ] );
			}
		}

		return true;
	}
}
