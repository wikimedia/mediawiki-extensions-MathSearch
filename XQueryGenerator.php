<?php

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2012 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * 2012/04/25 Changed LaTeXML for the MathML rendering which is passed to MathJAX
 * @file
 * @ingroup extensions
 */
abstract class XQueryGenerator {

	private $qvar = array();
	private $relativeXPath = '';
	private $lengthConstraint = '';
	/** @var DOMDocument the MWS XML */
	private $xml ;
	
	/**
	 * 
	 * @param String $cmmlQueryString that contains the MathML query expression
	 */
	public function __construct( $cmmlQueryString ){
		$this->xml = new DOMDocument(); 
		$this->xml->preserveWhiteSpace = false;
		$this->xml->loadXML($cmmlQueryString);
	}

	/**
	 * @return String the XQueryExpression.
	 */
	public function getXQuery() {
		$fixedConstraints = $this->generateConstraint( $this->xml->getElementsByTagName('expr')->item(0) );
		$qvarConstraintString = '';
		foreach ($this->qvar as $key => $value) {
			$first = $value[0];
			if ($qvarConstraintString) {
				$qvarConstraintString .= ' and ';
			}
			$qvarConstraintString .= '$x' . $first;
			$second = '';
			foreach ($value as $second) {
				if ($second) {
					$second.=' and $x' . $first;
				}
				$qvarConstraintString .= ' = $x' . $second;
			}
		}
		$query = 'for $x in $m//*:' . $this->xml->getElementsByTagName('expr')->item(0)->firstChild->nodeName . '[' .
				$fixedConstraints . '] return
			if( ' . $qvarConstraintString . $this->lengthConstraint . ')';
		return $this->getHeader().$query.$this->getFooter();
	}
	/**
	 * 
	 * @param DOMNode $node
	 * @return string
	 */
	private function generateConstraint($node) {
		$i = 0;
		$out = "";
		$hastext = false;
		foreach ($node->childNodes as $child) {
			$i++;
			if ($child->nodeName == "mws:qvar") {
				$qvarname = (string) $child->textContent;
				if (array_key_exists($qvarname, $this->qvar)) {
					$this->qvar[$qvarname][] = $this->relativeXPath . "/*[" . $i . "]";
				} else {
					$this->qvar[$qvarname] = array($this->relativeXPath . "/*[" . $i . "]");
				}
			} else {
				if ($hastext) {
					$out .= ' and ';
				}
				$out .= '*[' . $i . ']/name() =\'' . $child->nodeName . '\'';
				$hastext = true;
				if ($child->hasChildNodes()) {
					$this->relativeXPath.="/*[" . $i . "]";
					$out .= ' and *[' . $i . "][\n\t" . $this->generateConstraint($child) . "\n]";
				} else {
					$out .= ' and *[' . $i . "]/text()='" . $child->nodeName . "'";
				}
			}
		}
		$this->lengthConstraint .=' and fn:count($x' . $this->relativeXPath . '/*) = ' . $i;
		if ($this->relativeXPath) {
			$this->relativeXPath = substr($this->relativeXPath, 0, strrpos($this->relativeXPath, "/"));
		}
		return $out;
	}

	abstract protected function getHeader();
	abstract protected function getFooter();
}
