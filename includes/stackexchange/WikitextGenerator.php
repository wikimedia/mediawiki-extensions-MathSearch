<?php

namespace MathSearch\StackExchange;

use Exception;

class WikitextGenerator {
	private $idGen;
	private $formulae = [];
	private $postQid = 0;

	/**
	 * WikitextGenerator constructor.
	 * @param IdMap|null $idGen
	 */
	public function __construct( IdMap $idGen = null ) {
		if ( $idGen === null ) {
			$idGen = IdMap::getInstance();
		}
		$this->idGen = $idGen;
	}

	/**
	 * @param \SimpleXMLElement $elem
	 * @param $matches
	 * @return string
	 */
	private function processElement( \SimpleXMLElement $elem, $postQId ): string {
		$tagText = (string)$elem;

		switch ( $elem->getName() ) {
			case 'a':
				if ( $elem['href'] ) {
					$href = (string)$elem['href'];
					if ( preg_match( '#http://en\.wikipedia\.org/wiki/(.*)#', $href, $matches ) ) {
						return "[[w:{$matches[1]}|$tagText]]";
					} else {
						return "[$href $tagText]";
					}
				} else {
					return '';
				}
				break;
			case 'span':
				if ( $elem['class'] == 'math-container' ) {
					$fid = (int)$elem['id'];
					$qid = $this->getQId( $fid );
					$this->formulae[] = new Formula( $fid, $qid, $tagText, $postQId );

					return "<math id='$fid' qid='$qid'>$tagText</math>";
				}
		}

		return $tagText;
	}

	public function toWikitext( $html, $postQId = null ) {
		// Ugly workaround to remove wrong pre-processing
		$html = preg_replace( '/([<>])\1/', '$1', $html );
		$text = strip_tags( $html, '<a><span>' );
		$text = preg_replace_callback( '/<(?:a|span)(?:[^>]*?)>.*?<\/(?:a|span)>/i', // span or a
			function ( $m ) use ( $postQId ) {
				set_error_handler( function ( $errno, $errstr, $errfile, $errline ) {
					throw new Exception( $errstr, $errno );
				} );
				$elem = new \SimpleXMLElement( $m[0] );
				restore_error_handler();

				return $this->processElement( $elem, $postQId );
			}, $text );

		return $text;
	}

	private function getQId( int $fid ) {
		return $this->idGen->addQid( $fid, 0 );
	}

	/**
	 * @return \ArrayIterator|Formula[]
	 */
	public function getFormulae() {
		return new \ArrayIterator( $this->formulae );
	}

}
