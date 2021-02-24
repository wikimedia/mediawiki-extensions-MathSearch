<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

class MathIdGenerator {

	public const CONTENT_POS = 1;
	public const ATTRIB_POS = 2;

	/** @var string */
	private $wikiText;
	private $mathTags;
	/** @var int */
	private $revisionId;
	private $contentAccessStats = [];
	/** @var string */
	private $format = "math.%d.%d";
	/** @var bool */
	private $useCustomIds = false;
	private $keys;
	private $contentIdMap;

	/**
	 * @param RevisionRecord $revisionRecord
	 * @return self
	 * @throws MWException
	 */
	public static function newFromRevisionRecord( RevisionRecord $revisionRecord ) {
		$contentModel = $revisionRecord
			->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )
			->getModel();
		if ( $contentModel !== CONTENT_MODEL_WIKITEXT ) {
			throw new MWException( "MathIdGenerator supports only CONTENT_MODEL_WIKITEXT" );
		}
		return new self(
			ContentHandler::getContentText( $revisionRecord->getContent( SlotRecord::MAIN ) ),
			$revisionRecord->getId()
		);
	}

	/**
	 * @return int[] Array mapping key names to their position
	 */
	public function getKeys() {
		if ( !isset( $this->keys ) ) {
			$this->keys = array_flip( array_keys( $this->mathTags ) );
		}
		return $this->keys;
	}

	/**
	 * @param string $wikiText
	 * @param int $revisionId
	 */
	public function __construct( $wikiText, $revisionId = 0 ) {
		$wikiText = Sanitizer::removeHTMLcomments( $wikiText );
		$this->wikiText =
			Parser::extractTagsAndParams( [ 'nowki', 'syntaxhighlight', 'math' ], $wikiText,
				$tags );
		$this->mathTags = array_filter( $tags, function ( $v ) {
			return $v[0] === 'math';
		} );
		$this->revisionId = $revisionId;
	}

	/**
	 * @param int $revId
	 *
	 * @return self
	 */
	public static function newFromRevisionId( $revId ) {
		$revisionRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionById( $revId );

		return self::newFromRevisionRecord( $revisionRecord );
	}

	/**
	 * @param Title $title
	 *
	 * @return self
	 */
	public static function newFromTitle( Title $title ) {
		return self::newFromRevisionId( $title->getLatestRevID() );
	}

	public function getIdList() {
		return $this->formatIds( $this->mathTags );
	}

	/**
	 * @param array $mathTags
	 *
	 * @return string[]
	 */
	public function formatIds( $mathTags ) {
		return array_map( [ $this, 'parserKey2fId' ], array_keys( $mathTags ) );
	}

	/**
	 * @param string $key
	 *
	 * @return string|null
	 */
	public function parserKey2fId( $key ) {
		if ( $this->useCustomIds ) {
			if ( isset( $this->mathTags[$key][self::ATTRIB_POS]['id'] ) ) {
				return $this->mathTags[$key][self::ATTRIB_POS]['id'];
			}
		}
		if ( isset( $this->mathTags[$key] ) ) {
			return $this->formatKey( $key );
		}
	}

	public function getInputHash( $inputTex ) {
		return pack( "H32", md5( $inputTex ) );
	}

	/**
	 * @param string $content
	 *
	 * @return string[]
	 */
	public function getIdsFromContent( $content ) {
		$contentIdMap = $this->getContentIdMap();
		if ( array_key_exists( $content, $contentIdMap ) ) {
			return $contentIdMap[$content];
		}
		return [];
	}

	/**
	 * @return array[]
	 */
	public function getContentIdMap() {
		if ( !$this->contentIdMap ) {
			$this->contentIdMap = [];
			foreach ( $this->mathTags as $key => $tag ) {
				if ( !array_key_exists( $tag[self::CONTENT_POS],
						$this->contentIdMap )
				) {
					$this->contentIdMap[$tag[self::CONTENT_POS]] = [];
				}
				$this->contentIdMap[$tag[self::CONTENT_POS]][] =
					$this->parserKey2fId( $key );
			}
		}
		return $this->contentIdMap;
	}

	/**
	 * @param string $content
	 *
	 * @return string
	 */
	public function guessIdFromContent( $content ) {
		$allIds = $this->getIdsFromContent( $content );
		$size = count( $allIds );
		if ( $size == 0 ) {
			return null;
		}
		if ( $size == 1 ) {
			return $allIds[0];
		}
		if ( array_key_exists( $content, $this->contentAccessStats ) ) {
			$this->contentAccessStats[$content] ++;
		} else {
			$this->contentAccessStats[$content] = 0;
		}
		$currentIndex = $this->contentAccessStats[$content] % $size;
		return $allIds[$currentIndex];
	}

	/**
	 * @return array
	 */
	public function getMathTags() {
		return $this->mathTags;
	}

	/**
	 * @return string
	 */
	public function getWikiText() {
		return $this->wikiText;
	}

	/**
	 * @return int
	 */
	public function getRevisionId() {
		return $this->revisionId;
	}

	/**
	 * @param bool $useCustomIds
	 */
	public function setUseCustomIds( $useCustomIds ) {
		$this->useCustomIds = $useCustomIds;
	}

	/**
	 * @param string $eid
	 *
	 * @return array|null
	 */
	public function getTagFromId( $eid ) {
		foreach ( $this->mathTags as $key => $mathTag ) {
			if ( $eid == $this->formatKey( $key ) ) {
				return $mathTag;
			}
			if ( isset( $mathTag[self::ATTRIB_POS]['id'] ) && $eid == $mathTag[self::ATTRIB_POS]['id'] ) {
				return $mathTag;
			}
		}
	}

	/**
	 * @param string $eid
	 *
	 * @return string|null
	 */
	public function getUniqueFromId( $eid ) {
		foreach ( $this->mathTags as $key => $mathTag ) {
			if ( $eid == $this->formatKey( $key ) ) {
				return $key;
			}
			if ( isset( $mathTag[self::ATTRIB_POS]['id'] ) && $eid == $mathTag[self::ATTRIB_POS]['id'] ) {
				return $key;
			}
		}
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function formatKey( $key ) {
		$keys = $this->getKeys();
		return sprintf( $this->format, $this->revisionId, $keys[$key] );
	}
}
