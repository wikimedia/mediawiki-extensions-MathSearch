<?php

namespace MediaWiki\Extension\MathSearch\StackExchange;

use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Rdbms\IDatabase;

class IdMap {

	private readonly IDatabase $dbw;
	private readonly Item $item;
	private readonly EntityStore $store;
	private readonly HashBagOStuff $cache;
	/** @var self|null */
	private static $instance;

	private function __construct() {
		$this->dbw = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getPrimaryDatabase();

		$this->item = new Item();
		$this->store = WikibaseRepo::getEntityStore();
		// don't store too many keys
		$this->cache = new HashBagOStuff( [ 'maxKeys' => 10000 ] );
	}

	/**
	 * @return IdMap
	 */
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new IdMap();
		}

		return self::$instance;
	}

	private function getNewQid(): ?ItemId {
		$this->item->setId( null );
		$this->store->assignFreshId( $this->item );

		return $this->item->getId();
	}

	public function addQid( int $extId, int $extIdType ) {
		$cacheKey = "$extIdType.$extId";
		if ( $this->cache->hasKey( $cacheKey ) ) {
			return $this->cache->get( $cacheKey );
		}
		$fields = [
			'math_external_id' => $extId,
			'math_external_id_type' => $extIdType,
		];

		$qid = $this->dbw->selectField( 'math_wbs_entity_map', 'math_local_qid', $fields, __METHOD__ );
		if ( !$qid ) {
			$qid = $this->getNewQid()->getNumericId();
			$fields['math_local_qid'] = $qid;
			$this->dbw->insert( 'math_wbs_entity_map', $fields, __METHOD__ );
		}
		$this->cache->set( $cacheKey, $qid );

		return $qid;
	}

	public function addWikiText( int $qid, $parent, int $type, string $text ) {
		$cond = [
			'math_local_qid' => $qid,
		];
		$table = 'math_wbs_text_store';
		$entryExists = $this->dbw->selectRowCount( $table, 'math_local_qid', $cond, __METHOD__ );

		$fields = [
			'math_local_qid' => $qid,
			'math_reply_to' => $parent,
			'math_post_type' => $type,
			'math_body' => $text,
		];

		if ( $entryExists ) {
			$this->dbw->update( $table, $fields, $cond, __METHOD__ );
		} else {
			$this->dbw->insert( $table, $fields, __METHOD__ );
		}
	}
}
