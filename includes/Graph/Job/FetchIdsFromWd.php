<?php

namespace MediaWiki\Extension\MathSearch\Graph\Job;

use DataValues\StringValue;
use MediaWiki\Extension\MathSearch\Graph\Query;
use MediaWiki\MediaWikiServices;
use Throwable;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\WikibaseRepo;

class FetchIdsFromWd extends GraphJob {
	public function __construct( $params ) {
		parent::__construct( 'FetchIdsFromWd', $params );
	}

	public function run(): bool {
		$query = Query::getQueryForWdId();
		$rs = Query::getQueryEndpoint()->query( $query );
		if ( !$rs ) {
			self::getLog()->info( "No results retrieved!\n" );
			return false;
		}
		self::getLog()->info( "Retrieved " . count( $rs['result']['rows'] ) . " results.\n" );

		$user = $this->getUser();
		$store = WikibaseRepo::getEntityStore();
		$lookup = WikibaseRepo::getEntityLookup();
		$guidGenerator = new GuidGenerator();
		foreach ( $rs['result']['rows'] as $row ) {
			try {
				$qid = $row['qid'];
				unset( $row['qid'] );
				self::getLog()->info( "Update Properties for Q$qid." );
				$item = $lookup->getEntity( ItemId::newFromNumber( $qid ) );
				if ( $item === null ) {
					self::getLog()->error( "Item Q$qid not found." );
					continue;
				}
				/** @var StatementList $statements */
				$statements = $item->getStatements();
				$changed = false;
				foreach ( $row as $colname => $colval ) {
					if ( !str_starts_with( $colname, 'P' ) || !is_numeric( substr( $colname, 1 ) ) ) {
						self::getLog()
							->warning( "Result column '$colname' does not start with P followed by a number." );
						continue;
					}
					$pid = NumericPropertyId::newFromNumber( substr( $colname, 1 ) );
					$pidStatements = $statements->getByPropertyId( $pid );
					if ( !$pidStatements->isEmpty() ) {
						self::getLog()->info( "$colname of Zbl Q$qid already set." );
						continue;
					}
					$changed = true;
					$mainSnak = new PropertyValueSnak(
						$pid,
						new StringValue( $colval ) );
					$statements->addNewStatement(
						$mainSnak,
						[],
						null,
						$guidGenerator->newGuid( $item->getId() ) );
				}
				if ( !$changed ) {
					self::getLog()->info( "No changes for Zbl Q$qid." );
					continue;
				}
				$item->setStatements( $statements );

				$store->saveEntity( $item, "Add wikidata reference.", $user,
					EDIT_FORCE_BOT );
			} catch ( Throwable $ex ) {
				self::getLog()->error( "Skip page processing page Q$qid.", [ $ex ] );
				self::getLog()->info( $ex->getMessage() );
				self::getLog()->info( $ex->getTraceAsString() );
			}
		}
		// reschedule job (maybe there are more results)
		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		$jobQueueGroup->lazyPush( new FetchIdsFromWd( $this->params ) );
		return true;
	}
}
