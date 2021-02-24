<?php

namespace MathSearch\StackExchange;

use InvalidArgumentException;
use MediaWiki\Logger\LoggerFactory;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\WikibaseRepo;

class Field {

	private $seName;
	private $propertyId = null;
	private $references = false;
	private $known = false;
	private $excludeFromWb = null;
	private $content;
	private $normFileName;
	private $externalIdType = null;

	// TODO: read this from a config file
	private const FIELD_MAP = [
		'posts' => [
			'Id' => [
				'propertyId' => 'P5',
				'externalIdType' => 1,
			],
			'PostTypeId' => [
				'propertyId' => 'P9',
				'map' => [
					'1' => '887',
					'2' => '888',
				],
			],
			'ParentId' => [
				'propertyId' => 'P16',
				'references' => 'posts.Id',
			],
			'Score' => [
				'propertyId' => 'P6',
			],
			'ViewCount' => [
				'propertyId' => 'P17',
			],
			'AnswerCount' => [
				'propertyId' => 'P18',
			],
			'CommentCount' => [
				'propertyId' => 'P19',
			],
			'OwnerUserId' => [
				'propertyId' => 'P20',
				'references' => 'users.Id',
			],
			'AcceptedAnswerId' => [
				'propertyId' => 'P14',
				'references' => 'posts.Id',
			],
			'CreationDate' => [
				'propertyId' => 'P21',
			],
			'LastActivityDate' => [
				'propertyId' => 'P22',

			],
			'OwnerDisplayName' => [
				'propertyId' => 'P23',
			],
			'Body' => [
				'excludeFromWb' => true,
			],
			'Title' => [
				'excludeFromWb' => true,
			],
			'Tags' => [
				'separator' => '/(&[lg]t;|<|>|,)/',
				'propertyId' => 'P10',
			],
		],
		'users' => [
			'Id' => [
				'propertyId' => 'P7',
				'externalIdType' => 2,
			],
			'Reputation' => [
				'propertyId' => 'P24',
			],
			'CreationDate' => [
				'propertyId' => 'P20',
			],
			'LastAccessDate' => [
				'propertyId' => 'P21',
			],
			'DisplayName' => [
				'propertyId' => 'P23',
			],
			'Views' => [
				'propertyId' => 'P17',
			],
			'UpVotes' => [
				'propertyId' => 'P25',
			],
			'DownVotes' => [
				'propertyId' => 'P26',
			],
			'AccountId' => [
				'propertyId' => 'P27',
			],
			'AboutMe' => [
				'excludeFromWb' => true,
			],
			'WebsiteUrl' => [
				'propertyId' => 'P28',
			],
			'Location' => [
				'excludeFromWb' => true,
			],
		],
	];

	private static function getLog() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	/**
	 * @param string $seName
	 * @param string $content
	 * @param string $normFileName
	 */
	public function __construct( $seName, $content, $normFileName ) {
		$this->seName = $seName;
		$this->content = $content;
		$this->normFileName = $normFileName;
		// some posts file from arq20 math task were modified with additional version
		// information by appending either .V1.0 or _V1_0
		$fileparts = preg_split( "/[\._]/", $normFileName );
		$normalized_fn = strtolower( $fileparts[0] );
		self::getLog()->debug( "'$normFileName' is normalized to '$normalized_fn'." );
		if ( array_key_exists( $normalized_fn, self::FIELD_MAP ) ) {
			if ( array_key_exists( $seName, self::FIELD_MAP[$normalized_fn] ) ) {
				$this->propagateFieldInfo( self::FIELD_MAP[$normalized_fn][$seName] );
			} else {
				self::getLog()->warning( "Field {$seName} unknown in {$normalized_fn}-file." );
			}
		} else {
			self::getLog()->warning( "Unsupported file name, {$normFileName}." );
		}
	}

	/**
	 * @return bool
	 */
	public function isKnown(): bool {
		return $this->known;
	}

	/**
	 * @return int|string
	 */
	public function getContent() {
		return $this->content;
	}

	/**
	 * @return PropertyValueSnak
	 */
	public function getSnaks() {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$sf = $wikibaseRepo->getSnakFactory();
		$propertyId = new PropertyId( $this->propertyId );
		$type = $wikibaseRepo->getPropertyDataTypeLookup()->getDataTypeIdForProperty( $propertyId );
		$content = $this->content;
		if ( !is_array( $content ) ) {
			$content = [ $content ];
		}
		$result = [];
		foreach ( $content as $c ) {
			switch ( $type ) {
				case 'wikibase-item':
					$c = [ 'entity-type' => 'item', 'numeric-id' => (int)$c ];
					break;
				case 'quantity':
					$c = [ 'unit' => '1', 'amount' => $c ];
					break;
				case 'time':
					$c = [
						'time' => '+' . substr( $c, 0, -4 ) . 'Z',
						'timezone' => 0,
						'before' => 0,
						'after' => 0,
						'precision' => 11,
						'calendarmodel' => 'http://www.wikidata.org/entity/Q1985727',
					];
					break;
				case 'string':
				case 'external-id':
					break;
				default:
					$this->getLog()->warning( "Unexpected type" . $type );
			}
			try {
				$result[] = $sf->newSnak( $propertyId, 'value', $c );
			}
			catch ( InvalidArgumentException $e ) {
				$c_ser = var_export( $c, false );
				$this->getLog()
					->error( "Cannot create value '$c_ser' for property {$this->propertyId}",
						[ $e ] );
			}
		}

		return $result;
	}

	private function setIfDefined( $key, $field ) {
		if ( array_key_exists( $key, $field ) ) {
			$this->$key = $field[$key];

			return true;
		} else {
			return false;
		}
	}

	private function propagateFieldInfo( $fieldInfo ) {
		$hasKey = function ( $key ) use ( $fieldInfo ) {
			return array_key_exists( $key, $fieldInfo );
		};
		$this->known = true;
		$isExcluded = $this->setIfDefined( 'excludeFromWb', $fieldInfo );
		if ( $isExcluded ) {
// assert( count( $fieldInfo ) === 1,
//				'key-map cannot contain excludeFromWb and other fields.' );

			return;
		}
		$hasProperty = $this->setIfDefined( 'propertyId', $fieldInfo );
// assert( $hasProperty === true, "key-map for {$this->seName} must contain property" );
		$isReference = $this->setIfDefined( 'references', $fieldInfo );
		if ( $isReference ) {
// assert( count( $fieldInfo ) === 2,
//				'references can only specify propertyID and referenced field.' );
			$ref_path = explode( '.', $fieldInfo['references'] );
			$externalIdType = self::FIELD_MAP[$ref_path[0]][$ref_path[1]]['externalIdType'];
			$this->content = IdMap::getInstance()->addQid( $this->content, $externalIdType );

			return;
		}
		if ( $hasKey( 'map' ) ) {
// assert( array_key_exists( $this->content, $fieldInfo['map'] ),
//				"key-map {$this->seName} does not contain value {$this->content}" );
			$this->content = $fieldInfo['map'][$this->content];

			return;
		}
		if ( $hasKey( 'separator' ) ) {
			$this->content =
				preg_split( $fieldInfo['separator'], $this->content, -1, PREG_SPLIT_NO_EMPTY );
		}
		$this->setIfDefined( 'externalIdType', $fieldInfo );

		if ( $hasKey( 'external_id_type' ) ) {
			$this->externalIdType = $fieldInfo['external_id_type'];
		}
	}

	/**
	 * @return null
	 */
	public function isExcludedFromWb() {
		return $this->excludeFromWb;
	}

	/**
	 * @return null
	 */
	public function getExternalIdType() {
		return $this->externalIdType;
	}

}
