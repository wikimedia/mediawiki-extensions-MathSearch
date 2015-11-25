<?php

class MathIdGenerator {

	const CONTENT_POS = 1;
	const ATTRIB_POS = 2;
	private $parserRegexp;
	private $wikiText;
	private $mathTags;
	private $revisionId;
	private $contentAccessStats = array();
	private $format = "math.%d.%d";
	private $useCustomIds = false;
	private $keys;

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
		$this->parserRegexp = Parser::MARKER_PREFIX . "-math-([\\dA-F]{8})" . Parser::MARKER_SUFFIX;
		$wikiText = Sanitizer::removeHTMLcomments( $wikiText );
		$wikiText = preg_replace( '#<nowiki>(.*)</nowiki>#', '', $wikiText );
		$this->wikiText =
			Parser::extractTagsAndParams( array( 'math' ), $wikiText, $this->mathTags );
		$this->revisionId = $revisionId;
	}

	public static function newFromRevisionId( $revId ) {
		$revision = Revision::newFromId( $revId );
		if ( $revision->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			throw new MWException( "MathIdGenerator supports only CONTENT_MODEL_WIKITEXT" );
		}
		return new MathIdGenerator( ContentHandler::getContentText( $revision->getContent() ),
			$revId );
	}

	public static function newFromTitle( Title $title ) {
		return self::newFromRevisionId( $title->getLatestRevID() );
	}
	public function getIdList() {
		return $this->formatIds( $this->mathTags );
	}

	public function formatIds( $mathTags ) {
		return array_map( array( $this, 'parserKey2fId' ), array_keys( $mathTags ) );
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


	/**
	 * Decode binary packed hash from the database to md5 of input_tex
	 * @param string $hash (binary)
	 * @return string md5
	 */
	private static function dbHash2md5( $hash ) {
		$xhash = unpack( 'H32md5', $hash . "                " );
		return $xhash['md5'];
	}

	public function getIdsFromContent( $content ) {
		return $this->formatIds( array_filter( $this->mathTags,
			function ( $v ) use ( $content ) {
				return $content == $v[MathIdGenerator::CONTENT_POS];
			} ) );
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
		return sprintf( $this->format, $this->revisionId, $keys[$key] + 1 );
	}
}
