<?php

namespace MathSearch\StackExchange;

use Wikibase\DataModel\Entity\Item;
use Wikibase\Repo\WikibaseRepo;

class IdMap {

	/** @var \Wikimedia\Rdbms\IDatabase */
	private $dbw;
	/** @var Item */
	private $item;
	/** @var \Wikibase\Lib\Store\EntityStore */
	private $store;
	/**
	 * @var \HashBagOStuff
	 */
	private $cache;
	private static $instance;

	private function __construct() {
		$this->dbw = wfGetDB( DB_MASTER );
		$this->item = new Item();
		$this->store = WikibaseRepo::getStore()->getEntityStore();
		// don't store too many keys
		$this->cache = new \HashBagOStuff( [ 'maxKeys' => 10000 ] );
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

	private function getNewQid() {
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

		$qid = $this->dbw->selectField( 'math_wbs_entity_map', 'math_local_qid', $fields );
		if ( !$qid ) {
			$qid = $this->getNewQid()->getNumericId();
			$fields['math_local_qid'] = $qid;
			$this->dbw->insert( 'math_wbs_entity_map', $fields );
		}
		$this->cache->set( $cacheKey, $qid );

		return $qid;
	}

	public function addWikiText( int $qid, $parent, int $type, string $text ) {
		$cond = [
			'math_local_qid' => $qid,
		];
		$table = 'math_wbs_text_store';
		$entryExists = $this->dbw->selectRowCount( $table, 'math_local_qid', $cond );

		$fields = [
			'math_local_qid' => $qid,
			'math_reply_to' => $parent,
			'math_post_type' => $type,
			'math_body' => $text,
		];

		if ( $entryExists ) {
			$this->dbw->update( $table, $fields, $cond );
		} else {
			$this->dbw->insert( $table, $fields );
		}
	}
}
