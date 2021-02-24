<?php

namespace MathSearch\StackExchange;

use XMLReader;

class WikitextGenerator {

	/** @var IdMap */
	private $idGen;
	/** @var Formula[] */
	private $formulae = [];
	private $postQid = 0;

	/**
	 * @param IdMap|null $idGen
	 */
	public function __construct( IdMap $idGen = null ) {
		if ( $idGen === null ) {
			$idGen = IdMap::getInstance();
		}
		$this->idGen = $idGen;
	}

	public function getNextElement( XMLReader $xml, $postQId ): string {
		if ( $xml->nodeType == XMLReader::ELEMENT ) {
			if ( $xml->name === 'a' ) {
				$href = $xml->getAttribute( 'href' );
				if ( $href ) {
					$xml->read();
					if ( preg_match( '#http://en\.wikipedia\.org/wiki/(.*)#', $href, $matches ) ) {
						return "[[w:{$matches[1]}|$xml->value]]";
					} else {
						return "[$href $xml->value]";
					}
				}
			}
			if ( $xml->name === 'span' && $xml->getAttribute( 'class' ) === 'math-container' ) {
				$fid = (int)$xml->getAttribute( 'id' );
				$qid = $this->getQId( $fid );
				$xml->read();
				$tagText = $xml->value;
				$this->formulae[] = new Formula( $fid, $qid, $tagText, $postQId );
				return "<math id='$fid' qid='$qid'>$tagText</math>";
			}
		}

		return $xml->value;
	}

	public function toWikitext( $html, $postQId = null ) {
		$xml = new \XMLReader();
		$xml->XML( "<text>$html</text>" );
		$out = "";
		while ( $xml->read() ) {
			$out .= $this->getNextElement( $xml, $postQId );
		}
		return $out;
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
