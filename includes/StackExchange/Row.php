<?php

namespace MediaWiki\Extension\MathSearch\StackExchange;

use Exception;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\User;
use SimpleXMLElement;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\WikibaseRepo;

class Row {

	/** @var Field[] */
	private $fields = [];
	/** @var int */
	private $ignoredFieldCount = 0;
	/** @var string */
	private $normFileName;

	public function __construct( $line, $fileName ) {
		$this->normFileName = $fileName;
		set_error_handler( static function ( $errno, $errstr, $errfile, $errline ) {
			throw new Exception( $errstr, $errno );
		} );
		if ( is_array( $line ) ) {
			foreach ( $line as $key => $value ) {
				$this->addField( $fileName, $key, $value );
			}
		} else {
			$row = new SimpleXMLElement( $line );
			restore_error_handler();
			foreach ( $row->attributes() as $key => $value ) {
				$this->addField( $fileName, $key, (string)$value );
			}
		}
	}

	private static function getLog(): \Psr\Log\LoggerInterface {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	/**
	 * @return Field[]
	 */
	public function getFields(): array {
		return $this->fields;
	}

	/**
	 * @return int
	 */
	public function getIgnoredFieldCount(): int {
		return $this->ignoredFieldCount;
	}

	public function createWbItem() {
		$store = WikibaseRepo::getEntityStore();
		$user = User::newFromName( 'Maintenance script' );
		$item = $this->getItem();
		$id = $this->fields['Id']->getContent();
		$item->setLabel( 'en', "{$this->normFileName} {$id}" );
		if ( array_key_exists( 'Title', $this->fields ) ) {
			$item->setDescription( 'en', $this->fields['Title']->getContent() );
		}
		$item->setStatements( $this->getStatementList() );
		$store->saveEntity( $item, "Imported item from StackExchange.", $user );
	}

	public function getItem() {
		$qid = $this->getQid();
		$item = new Item();
		$item->setId( ItemId::newFromNumber( $qid ) );

		return $item;
	}

	/**
	 * @return int|string
	 */
	public function getQid() {
		$id = $this->getField( 'Id' );
		return IdMap::getInstance()->addQid( $id->getContent(), $id->getExternalIdType() );
	}

	public function getField( $name ): Field {
		return $this->fields[$name];
	}

	public function getStatementList() {
		$guidGenerator = new GuidGenerator();
		$statements = new StatementList();
		foreach ( $this->fields as $field ) {
			if ( !$field->isExcludedFromWb() ) {
				$snaks = $field->getSnaks();
				foreach ( $snaks as $snak ) {
					$guid = $guidGenerator->newGuid( $this->getItem()->getId() );
					$statements->addNewStatement( $snak, null, null, $guid );
				}
			}
		}

		return $statements;
	}

	public function processBody() {
		$body = $this->getField( 'Body' )->getContent();
		$wtGen = new WikitextGenerator();
		$qid = $this->getQid();
		try {
			$wt = $wtGen->toWikitext( $body, $qid );
			foreach ( $wtGen->getFormulae() as $f ) {
				// $f->createWbItem();
				$f->updateSearchIndex();
			}
		} catch ( \Throwable $e ) {
			$wt = $body;
			self::getLog()->error( "Problem while concerting {body} to wikitext: {e}", [
				'body' => $body,
				'e' => $e->getMessage(),
			] );
		}
		$type = $this->getQIdFromField( 'PostTypeId' );
		$parent = $this->getQIdFromField( 'ParentId' );
		IdMap::getInstance()->addWikiText( $qid, $parent, $type, $wt );
	}

	public function getQIdFromField( $fieldName ) {
		if ( array_key_exists( $fieldName, $this->fields ) ) {
			return $this->getField( $fieldName )->getContent();
		} else {
			return null;
		}
	}

	/**
	 * @param string $fileName
	 * @param string $key
	 * @param string $value
	 */
	private function addField( $fileName, $key, $value ): void {
		$field = new Field( $key, $value, $fileName );
		if ( $field->isKnown() ) {
			$this->fields[$key] = $field;
		} else {
			$this->ignoredFieldCount++;
		}
	}
}
