<?php

use MediaWiki\Extension\Math\MathLaTeXML;

class MwsDumpWriter {

	/** @var string */
	private $mwsns = 'mws:';
	private $XMLHead;
	private $XMLFooter;
	/** @var string */
	private $outBuffer = '';

	public function __construct( $ns = 'mws:' ) {
		$this->setMwsns( $ns );
		$this->InitializeHeader();
	}

	public function InitializeHeader() {
		$ns = $this->mwsns;
		$this->XMLHead = "<?xml version=\"1.0\"?>\n<" . $ns .
			'harvest xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML">';
		$this->XMLFooter = '</' . $ns . 'harvest>';
	}

	/**
	 * @param stdClass $row
	 *
	 * @return string
	 */
	public function generateIndexString( $row ) {
		$xml = simplexml_load_string( utf8_decode( $row->math_mathml ) );
		if ( !$xml ) {
			echo "ERROR while converting:\n " . var_export( $row->math_mathml, true ) . "\n";
			foreach ( libxml_get_errors() as $error ) {
				echo "\t", $error->message;
			}
			libxml_clear_errors();
			return '';
		}
		return $this->getMwsExpression( utf8_decode( $row->math_mathml ),
			$row->mathindex_revision_id, $row->mathindex_anchor
		);
	}

	public function getHead() {
		return $this->XMLHead;
	}

	public function getFooter() {
		return $this->XMLFooter;
	}

	public function getMwsExpression( $mathML, $revId, $eId ) {
		$out = "\n<" . $this->mwsns . "expr url=\"${revId}#${eId}\">\n\t";
		$out .= $mathML;
		$out .= "\n</" . $this->mwsns . "expr>\n";
		return $out;
	}

	public function addMwsExpression( $mathML, $revId, $eId ) {
		$this->outBuffer .= $this->getMwsExpression( $mathML, $revId, $eId );
		return true;
	}

	public function addRevision( $revId ) {
		$mathId = MathIdGenerator::newFromRevisionId( $revId );
		$this->addFromMathIdGenerator( $mathId );
	}

	/**
	 * @return string
	 */
	public function getMwsns() {
		return $this->mwsns;
	}

	/**
	 * @param string $mwsns
	 */
	public function setMwsns( $mwsns ) {
		$this->mwsns = $mwsns;
	}

	public function getOutput() {
		return $this->getHead() . $this->outBuffer . $this->getFooter();
	}

	/**
	 * @param MathIdGenerator $generator
	 */
	public function addFromMathIdGenerator( MathIdGenerator $generator ) {
		foreach ( $generator->getMathTags() as $key => $tag ) {
			$mml = new MathLaTeXML( $tag[MathIdGenerator::CONTENT_POS],
					$tag[MathIdGenerator::ATTRIB_POS] );
			$mml->render();
			$this->outBuffer .= $this->getMwsExpression( $mml->getMathml(),
				$generator->getRevisionId(),
				$generator->parserKey2fId( $key ) );
		}
	}
}
