<?php

class MathIdGenerator {

	const CONTENT_POS = 1;
	const ATTRIB_POS = 2;
	private $wikiText;
	private $mathTags;
	private $revisionId;
	private $contentAccessStats = [];
	private $format = "math.%d.%d";
	private $useCustomIds = false;
	private $keys;
	private $contentIdMap;

	/**
	 * @param $revision
	 * @return MathIdGenerator
	 * @throws MWException
	 */
	public static function newFromRevision( Revision $revision ) {
		if ( $revision->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			throw new MWException( "MathIdGenerator supports only CONTENT_MODEL_WIKITEXT" );
		}
		return new MathIdGenerator( ContentHandler::getContentText( $revision->getContent() ),
			$revision->getId() );
	}

	/**
	 * @return mixed
	 */
	public function getKeys() {
		if ( !isset( $this->keys ) ) {
			$this->keys = array_flip( array_keys( $this->mathTags ) );
		}
		return $this->keys;
	}

	/**
	 * MathIdGenerator constructor.
	 * @param $wikiText
	 * @param $revisionId
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

	public static function newFromRevisionId( $revId ) {
		$revision = Revision::newFromId( $revId );
		return self::newFromRevision( $revision );
	}

	public static function newFromTitle( Title $title ) {
		return self::newFromRevisionId( $title->getLatestRevID() );
	}
	public function getIdList() {
		return $this->formatIds( $this->mathTags );
	}

	public function formatIds( $mathTags ) {
		return array_map( [ $this, 'parserKey2fId' ], array_keys( $mathTags ) );
	}

	public function parserKey2fId( $key ) {
		if ( $this->useCustomIds ) {
			if ( isset( $this->mathTags[$key][self::ATTRIB_POS]['id'] ) ) {
				return $this->mathTags[$key][self::ATTRIB_POS]['id'];
			}
		}
		if ( isset( $this->mathTags[$key] ) ) {
			return $this->formatKey( $key );
		};
	}

	public function getInputHash( $inputTex ) {
		return pack( "H32", md5( $inputTex ) );
	}

	public function getIdsFromContent( $content ) {
		$contentIdMap = $this->getContentIdMap();
		if ( array_key_exists( $content, $contentIdMap ) ) {
			return $contentIdMap[$content];
		}
		return [];
	}

	public function getContentIdMap() {
		if ( !$this->contentIdMap ) {
			$this->contentIdMap = [];
			foreach ( $this->mathTags as $key => $tag ) {
				if ( !array_key_exists( $tag[MathIdGenerator::CONTENT_POS],
						$this->contentIdMap )
				) {
					$this->contentIdMap[$tag[MathIdGenerator::CONTENT_POS]] = [];
				}
				$this->contentIdMap[$tag[MathIdGenerator::CONTENT_POS]][] =
					$this->parserKey2fId( $key );
			}
		}
		return $this->contentIdMap;
	}

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
	 * @return mixed
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
	 * @param boolean $useCustomIds
	 */
	public function setUseCustomIds( $useCustomIds ) {
		$this->useCustomIds = $useCustomIds;
	}

	public function getTagFromId( $eid ) {
		foreach ( $this->mathTags as $key => $mathTag ) {
			if ( $eid == $this->formatKey( $key ) ){
				return $mathTag;
			}
			if ( isset( $mathTag[self::ATTRIB_POS]['id'] ) && $eid == $mathTag[self::ATTRIB_POS]['id'] ){
				return $mathTag;
			}
		}
	}
	public function getUniqueFromId( $eid ) {
		foreach ( $this->mathTags as $key => $mathTag ) {
			if ( $eid == $this->formatKey( $key ) ){
				return $key;
			}
			if ( isset( $mathTag[self::ATTRIB_POS]['id'] ) && $eid == $mathTag[self::ATTRIB_POS]['id'] ){
				return $key;
			}
		}
	}

	/**
	 * @param $key
	 * @return string
	 */
	private function formatKey( $key ) {
		$keys = $this->getKeys();
		return sprintf( $this->format, $this->revisionId, $keys[$key] );
	}
}
