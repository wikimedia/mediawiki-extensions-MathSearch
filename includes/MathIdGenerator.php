<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;

class MathIdGenerator {

	public const CONTENT_POS = 1;
	public const ATTRIB_POS = 2;
	private const TAGS = [ 'math', 'chem', 'ce' ];
	/**
	 * Tags whose content the parser shows as text. This only approximates the parser,
	 * which leaves the content of every registered tag to its extension (T440805).
	 */
	private const VERBATIM_TAGS = [ 'nowiki', 'pre', 'source', 'syntaxhighlight' ];
	/** Of mathindex_anchor; a longer id would be cut off there */
	private const MAX_ANCHOR_BYTES = 50;

	private readonly string $wikiText;
	/**
	 * @var array<string,array{0:string,1:string|null,2:array,3:string}> Filtered result from
	 *  {@see Parser::extractTagsAndParams} with only <math> tags
	 */
	private readonly array $mathTags;
	/** @var array<string,string> key => id attribute, for the first tag with that id */
	private readonly array $customIds;
	private string $format = "math.%d.%d";
	/** @var int[]|null */
	private $keys;

	/** Code never renders math tags */
	public static function indexesModel( string $contentModel ): bool {
		return !in_array( $contentModel,
			[ CONTENT_MODEL_JAVASCRIPT, CONTENT_MODEL_CSS, CONTENT_MODEL_JSON, 'sanitized-css' ], true );
	}

	public static function newFromRevisionRecord( RevisionRecord $revisionRecord ): MathIdGenerator {
		$contentModel = $revisionRecord
			->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )
			->getModel();
		if ( !self::indexesModel( $contentModel ) ) {
			throw new RuntimeException( "MathIdGenerator does not index $contentModel" );
		}
		return new self(
			$revisionRecord->getContent( SlotRecord::MAIN )->serialize(),
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
		$strippedText = Parser::extractTagsAndParams( [ ...self::VERBATIM_TAGS, ...self::TAGS ], $wikiText,
			$tags );
		$this->mathTags = array_filter( $tags, static function ( $v ) {
			// The parser accepts the tag names in any case, but returns them as written
			return in_array( strtolower( $v[0] ), self::TAGS, true );
		} );
		$customIds = [];
		foreach ( $this->mathTags as $key => $tag ) {
			$id = $tag[self::ATTRIB_POS]['id'] ?? '';
			if ( $id !== '' && strlen( $id ) <= self::MAX_ANCHOR_BYTES && !in_array( $id, $customIds, true ) ) {
				$customIds[$key] = $id;
			}
		}
		$this->customIds = $customIds;
		$replacements = [];
		foreach ( $tags as $key => $tag ) {
			$replacements[$key] = isset( $this->mathTags[$key] ) ? $this->getAnchoredTag( $key ) : $tag[3];
		}
		$this->wikiText = strtr( $strippedText, $replacements );
	}

	public function getAnchoredTag( string $key ): string {
		[ $element, , $attributes, $text ] = $this->mathTags[$key];
		if ( isset( $attributes['id'] ) ) {
			return $text;
		}
		$nameEnd = strlen( $element ) + 1;
		return substr( $text, 0, $nameEnd ) . ' id="' . $this->formatKey( $key ) . '"' . substr( $text, $nameEnd );
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

	public function getIdList(): array {
		return $this->formatIds( $this->mathTags );
	}

	/**
	 * @return string[]
	 */
	public function formatIds( array $mathTags ): array {
		return array_map( $this->getAnchor( ... ), array_keys( $mathTags ) );
	}

	public function getAnchor( string $key ): string {
		return $this->customIds[$key] ?? $this->formatKey( $key );
	}

	public function getMathTags(): array {
		return $this->mathTags;
	}

	public function addAnchorsTo(): string {
		return $this->wikiText;
	}

	public function getRevisionId(): int {
		return $this->revisionId;
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

	/**
	 * Keep in line with ParserHooksHandler::chemTagHook() in Math, which is private.
	 *
	 * @param array{0:string,1:string,2:array} $tag
	 * @return array{0:string,1:array}
	 */
	public function getRendererInput( array $tag ): array {
		$tex = $tag[self::CONTENT_POS] ?? '';
		$attributes = $tag[self::ATTRIB_POS];
		if ( strtolower( $tag[0] ) !== 'math' ) {
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
