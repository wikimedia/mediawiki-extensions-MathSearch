<?php

use MediaWiki\Extension\Math\MathSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;

class MathIdGenerator {

	public const CONTENT_POS = 1;
	public const ATTRIB_POS = 2;

	private string $wikiText;
	private array $mathTags;
	private int $revisionId;
	/** @var int[] */
	private $contentAccessStats = [];
	private string $format = "math.%d.%d";
	private bool $useCustomIds = false;
	/** @var int[]|null */
	private $keys;
	/** @var string[][]|null */
	private $contentIdMap;

	public static function newFromRevisionRecord( RevisionRecord $revisionRecord ): MathIdGenerator {
		$contentModel = $revisionRecord
			->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )
			->getModel();
		if ( $contentModel !== CONTENT_MODEL_WIKITEXT ) {
			throw new RuntimeException( "MathIdGenerator supports only CONTENT_MODEL_WIKITEXT" );
		}
		$content = $revisionRecord->getContent( SlotRecord::MAIN );
		if ( !$content instanceof TextContent ) {
			throw new RuntimeException( "MathIdGenerator supports only TextContent" );
		}
		return new self(
			$content->getText(),
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

	public function __construct( string $wikiText, int $revisionId = 0 ) {
		$wikiText = Sanitizer::removeHTMLcomments( $wikiText );
		$this->wikiText =
			Parser::extractTagsAndParams( [ 'nowki', 'syntaxhighlight', 'math' ], $wikiText,
				$tags );
		$this->mathTags = array_filter( $tags, static function ( $v ) {
			return $v[0] === 'math';
		} );
		$this->revisionId = $revisionId;
	}

	public static function newFromRevisionId( int $revId ): MathIdGenerator {
		$revisionRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionById( $revId );

		return self::newFromRevisionRecord( $revisionRecord );
	}

	public static function newFromTitle( Title $title ): MathIdGenerator {
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
	 * @return string[][]
	 */
	public function getContentIdMap() {
		if ( !$this->contentIdMap ) {
			$this->contentIdMap = [];
			foreach ( $this->mathTags as $key => $tag ) {
				$userInputTex = $this->getUserInputTex( $tag );
				if ( !array_key_exists( $userInputTex,
						$this->contentIdMap )
				) {
					$this->contentIdMap[$userInputTex] = [];
				}
				$this->contentIdMap[$userInputTex][] =
					$this->parserKey2fId( $key );
			}
		}
		return $this->contentIdMap;
	}

	public function guessIdFromContent( string $content ): ?string {
		$allIds = $this->getIdsFromContent( $content );
		$size = count( $allIds );
		if ( $size == 0 ) {
			return null;
		}
		if ( $size == 1 ) {
			return $allIds[0];
		}
		if ( array_key_exists( $content, $this->contentAccessStats ) ) {
			$this->contentAccessStats[$content]++;
		} else {
			$this->contentAccessStats[$content] = 0;
		}
		$currentIndex = $this->contentAccessStats[$content] % $size;
		return $allIds[$currentIndex];
	}

	public function getMathTags(): array {
		return $this->mathTags;
	}

	public function getWikiText(): string {
		return $this->wikiText;
	}

	public function getRevisionId(): int {
		return $this->revisionId;
	}

	public function setUseCustomIds( bool $useCustomIds ): void {
		$this->useCustomIds = $useCustomIds;
	}

	public function getTagFromId( string $eid ): ?array {
		foreach ( $this->mathTags as $key => $mathTag ) {
			if ( $eid == $this->formatKey( $key ) ) {
				return $mathTag;
			}
			if ( isset( $mathTag[self::ATTRIB_POS]['id'] ) && $eid == $mathTag[self::ATTRIB_POS]['id'] ) {
				return $mathTag;
			}
		}
		return null;
	}

	public function getUniqueFromId( string $eid ): ?string {
		foreach ( $this->mathTags as $key => $mathTag ) {
			if ( $eid == $this->formatKey( $key ) ) {
				return $key;
			}
			if ( isset( $mathTag[self::ATTRIB_POS]['id'] ) && $eid == $mathTag[self::ATTRIB_POS]['id'] ) {
				return $key;
			}
		}
		return null;
	}

	private function formatKey( string $key ): string {
		$keys = $this->getKeys();
		return sprintf( $this->format, $this->revisionId, $keys[$key] );
	}

	public function getUserInputTex( array $tag ): string {
		return ( new MathSource( $tag[self::CONTENT_POS], $tag[self::ATTRIB_POS] ) )->getUserInputTex();
	}
}
