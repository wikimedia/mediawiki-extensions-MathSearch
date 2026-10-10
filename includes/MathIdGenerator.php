<?php

use MediaWiki\Content\TextContent;
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
	private const TAGS = [ 'math', 'chem', 'ce' ];
	/** Tags whose content the parser shows as text, so math tags inside are not rendered */
	private const VERBATIM_TAGS = [ 'nowiki', 'pre', 'source', 'syntaxhighlight' ];

	private readonly string $wikiText;
	/**
	 * @var array<string,array{0:string,1:string|null,2:array,3:string}> Filtered result from
	 *  {@see Parser::extractTagsAndParams} with only <math> tags
	 */
	private readonly array $mathTags;
	/** @var int[] */
	private $contentAccessStats = [];
	private string $format = "math.%d.%d";
	private bool $useCustomIds = false;
	/** @var int[]|null */
	private $keys;
	/** @var array<string,?string[]>|null */
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
	 * @return array<string,int> Array mapping key names to their position
	 */
	public function getKeys() {
		$this->keys ??= array_flip( array_keys( $this->mathTags ) );
		return $this->keys;
	}

	public function __construct(
		string $wikiText,
		private readonly int $revisionId = 0,
	) {
		$wikiText = Sanitizer::removeHTMLcomments( $wikiText );
		$this->wikiText =
			Parser::extractTagsAndParams( [ ...self::VERBATIM_TAGS, ...self::TAGS ], $wikiText,
				$tags );
		$this->mathTags = array_filter( $tags, static function ( $v ) {
			return in_array( $v[0], self::TAGS, true );
		} );
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
	 * @param array<string,mixed> $mathTags
	 *
	 * @return string[]
	 */
	public function formatIds( $mathTags ) {
		return array_map( $this->parserKey2fId( ... ), array_keys( $mathTags ) );
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

	/**
	 * @param string $content
	 *
	 * @return ?string[]
	 */
	public function getIdsFromContent( $content ) {
		$contentIdMap = $this->getContentIdMap();
		if ( array_key_exists( $content, $contentIdMap ) ) {
			return $contentIdMap[$content];
		}
		return [];
	}

	/**
	 * @return array<string,?string[]>
	 */
	public function getContentIdMap() {
		if ( !$this->contentIdMap ) {
			$this->contentIdMap = [];
			foreach ( $this->mathTags as $key => $tag ) {
				$userInputTex = $this->getUserInputTex( $tag );
				$this->contentIdMap[$userInputTex][] = $this->parserKey2fId( $key );
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

	/**
	 * @param string $eid
	 * @return array|null
	 */
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

	/**
	 * The key of the id map: what MathRenderer::getUserInputTex() returns for this tag, display wrap included
	 *
	 * @param array{0:string,1:string,2:array} $tag under unknown circumstances the content might be null T391163
	 * @return string
	 */
	public function getUserInputTex( array $tag ): string {
		[ $tex, $attributes ] = $this->getRendererInput( $tag );
		return ( new MathSource( $tex, $attributes ) )->getUserInputTex();
	}

	/**
	 * The TeX and attributes as the tag hooks of Math pass them to the renderer, chem wrapped in \\ce{}
	 *
	 * @param array{0:string,1:string,2:array} $tag
	 * @return array{0:string,1:array}
	 */
	public function getRendererInput( array $tag ): array {
		$tex = $tag[self::CONTENT_POS] ?? '';
		$attributes = $tag[self::ATTRIB_POS];
		if ( $tag[0] !== 'math' ) {
			$tex = '\\ce{' . $tex . '}';
			$attributes['chem'] = true;
		}
		return [ $tex, $attributes ];
	}

	/**
	 * Without a closing tag, the parser takes the rest of the page as the content of the tag
	 *
	 * @param array{0:string,1:?string,2:array,3:string} $tag
	 */
	public function isClosed( array $tag ): bool {
		return $tag[self::CONTENT_POS] === null ||
			preg_match( '/<\/' . preg_quote( $tag[0], '/' ) . '\s*>$/i', $tag[3] );
	}
}
