<?php
class MathHighlighter {
	/**
	 *
	 */
	const WINDOW_SIZE = 1200;
	private $wikiText;

	/**
	 * MathHighlighter constructor.
	 * @param $fId
	 * @param $revId
	 */
	public function __construct( $fId, $revId ) {
		$gen = MathIdGenerator::newFromRevisionId( $revId );
		$unique = $gen->getUniqueFromId( $fId );
		$wikiText = $gen->getWikiText();
		$tagPos = strpos( $wikiText, $unique );
		$startPos = $this->getStartPos( $tagPos, $wikiText );
		$length = $this->getEndPos( $tagPos, $wikiText ) - $startPos;
		$wikiText = substr( $wikiText, $startPos, $length );
		$tag = $gen->getTagFromId( $fId );
		$wikiText = str_replace( $unique,
			'<span id="theelement" style="background-color: yellow">' . $tag[3] . '</span>',
			$wikiText );
		foreach ( $gen->getMathTags() as $key => $content ) {
			$wikiText = str_replace( $key, $content[3], $wikiText );
		}
		$this->wikiText = "== Extract ==\nStart of the extract...\n\n$wikiText\n\n...end of the extract";
	}

	/**
	 * @param $tagPos
	 * @param $wikiText
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
	 * @param $wikiText
	 * @param $tagPos
	 * @return bool|int|mixed
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