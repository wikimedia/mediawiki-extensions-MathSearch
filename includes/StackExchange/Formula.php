<?php

namespace MediaWiki\Extension\MathSearch\StackExchange;

use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\User\User;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\WikibaseRepo;

class Formula {

	private $id;
	private $qid;
	private $text;
	private $postQId;

	/**
	 * @param int $id
	 * @param int $qid
	 * @param string $text
	 * @param int $postQId
	 */
	public function __construct( $id, $qid, $text, $postQId ) {
		$this->id = $id;
		$this->qid = $qid;
		$this->text = $text;
		$this->postQId = $postQId;
	}

	public function updateSearchIndex() {
		$renderer = new MathMathML( $this->text, [ 'display' => 'block' ] );
		$hash = $renderer->getInputHash();
		$renderer->writeToCache();
		// TODO: Fix fake revision ID
		\MathSearchHooks::writeMathIndex( 1, $this->id, $hash, $this->text );
	}

	public function createWbItem() {
		$sf = WikibaseRepo::getSnakFactory();
		$store = WikibaseRepo::getEntityStore();
		$user = User::newFromName( 'Maintenance script' );
		$item = new Item();
		$item->setId( ItemId::newFromNumber( $this->qid ) );
		$item->setLabel( 'en', "Formula {$this->id}" );
		$guidGenerator = new GuidGenerator();
		$statements = new StatementList();
		$guid = $guidGenerator->newGuid( $item->getId() );
		// TODO: get from settings
		$snak = $sf->newSnak( new NumericPropertyId( 'P1' ), 'value', $this->text );
		$statements->addNewStatement( $snak, null, null, $guid );
		$guid = $guidGenerator->newGuid( $item->getId() );
		$snak = $sf->newSnak( new NumericPropertyId( 'P8' ), 'value', (string)$this->id );
		$statements->addNewStatement( $snak, null, null, $guid );
		$guid = $guidGenerator->newGuid( $item->getId() );
		$snak =
			$sf->newSnak( new NumericPropertyId( 'P16' ), 'value',
				[ 'entity-type' => 'item', 'numeric-id' => (int)$this->postQId ] );
		$statements->addNewStatement( $snak, null, null, $guid );
		$item->setStatements( $statements );
		$store->saveEntity( $item, "Imported formula from StackExchange.", $user );
	}
}
