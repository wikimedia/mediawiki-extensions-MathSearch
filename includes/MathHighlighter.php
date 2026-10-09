<?php

class MathHighlighter {

	private const WINDOW_SIZE = 1200;

	private readonly string $wikiText;

	/**
	 * @param string $fId
	 * @param int $revId
	 * @param bool $highlight
	 */
	public function __construct( $fId, $revId, $highlight = true ) {
		$gen = MathIdGenerator::newFromRevisionId( $revId );
		$tag = $gen->getAnchoredTag( $gen->getUniqueFromId( $fId ) );
		$wikiText = $gen->addAnchorsTo();
		$tagPos = strpos( $wikiText, $tag );
		$startPos = $this->getStartPos( $tagPos, $wikiText );
		$length = $this->getEndPos( $tagPos, $wikiText ) - $startPos;
		$wikiText = substr( $wikiText, $startPos, $length );
		if ( $highlight ) {
			$wikiText = str_replace( $tag, '<span id="mathhighlight" style="background-color: yellow">'
				. $tag . '</span>', $wikiText );
		}
		$this->wikiText = $wikiText;
	}

	/**
	 * @param int $tagPos
	 * @param string $wikiText
	 * @return int
	 */
	private function getStartPos( $tagPos, $wikiText ) {
		$startPos = max( $tagPos - round( self::WINDOW_SIZE / 2 ), 0 );
		if ( $startPos > 0 ) {
			// Heuristics to find a reasonable cutting point
			$newPos = strpos( $wikiText, "\n", $startPos );
			if ( $newPos !== false && ( $newPos - $startPos ) < round( self::WINDOW_SIZE / 4 ) ) {
				// only change startPos, if it seems reasonable
				$startPos = $newPos;
			}
		}
		return $startPos;
	}

	/**
	 * @param int $tagPos
	 * @param string $wikiText
	 * @return int
	 */
	private function getEndPos( $tagPos, $wikiText ) {
		$halfWindow = round( self::WINDOW_SIZE / 2 );
		$distance2End = strlen( $wikiText ) - $tagPos;
		if ( $distance2End > $halfWindow ) {
			$newPos = strpos( $wikiText, "\n", $tagPos + $halfWindow );
			if ( $newPos !== false && ( $newPos - $tagPos ) < round( 3 / 4 * self::WINDOW_SIZE ) ) {
				// only change startPos, if it seems reasonable
				return $newPos;
			} else {
				return $tagPos + $halfWindow;
			}
		} else {
			return strlen( $wikiText );
		}
	}

	/**
	 * @return string
	 */
	public function getWikiText() {
		return $this->wikiText;
	}

}
