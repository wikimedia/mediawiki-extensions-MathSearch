<?php

namespace MediaWiki\Extension\MathSearch\XQuery;

use DOMDocument;

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

	private array $qvar = [];
	private string $relativeXPath = '';
	private string $lengthConstraint = '';
	/** @var DOMDocument the MWS XML */
	private $xml;

	/**
	 *
	 * @param string $cmmlQueryString that contains the MathML query expression
	 */
	public function __construct( $cmmlQueryString ) {
		$this->xml = new DOMDocument();
		$this->xml->preserveWhiteSpace = false;
		$this->xml->loadXML( $cmmlQueryString );
	}

	/**
	 * @return string the XQueryExpression.
	 */
	public function getXQuery() {
		$fixedConstraints = $this->generateConstraint( $this->xml->getElementsByTagName( 'expr' )->item( 0 ), true );
		$qvarConstraintString = '';
		foreach ( $this->qvar as $key => $value ) {
			$addstr = '';
			$newContent = false;
			if ( count( $value ) > 1 ) {
				$first = $value[0];
				if ( $qvarConstraintString ) {
					$addstr .= "\n  and ";
				}
				$lastSecond = '';
				foreach ( $value as $second ) {
					if ( $second != $first ) {
						if ( $lastSecond ) {
							$addstr .= ' and ';
						}
						$addstr .= '$x' . $first . ' = $x' . $second;
						$lastSecond = $second;
						$newContent = true;
					}
				}
			}
			if ( $newContent ) {
				$qvarConstraintString .= $addstr;
			}
		}
		$query = 'for $x in $m//*:' .
			$this->xml->getElementsByTagName( 'expr' )->item( 0 )->firstChild->localName . PHP_EOL .
			$fixedConstraints . PHP_EOL .
			' where' . PHP_EOL .
			$this->lengthConstraint .
			( ( ( $qvarConstraintString && $this->lengthConstraint ) ? ' and ' : '' ) ) .
			$qvarConstraintString . PHP_EOL .
			' return' . PHP_EOL;
		return $this->getHeader() . $query . $this->getFooter();
	}

	/**
	 *
	 * @param \DOMNode $node
	 * @param bool $isRoot
	 * @return string
	 */
	private function generateConstraint( $node, $isRoot = false ) {
		$i = 0;
		$out = "";
		$hastext = false;
		foreach ( $node->childNodes as $child ) {
			if ( $child->nodeName == "mws:qvar" ) {
				$i++;
				$qvarname = (string)$child->textContent;
				if ( array_key_exists( $qvarname, $this->qvar ) ) {
					$this->qvar[$qvarname][] = $this->relativeXPath . "/*[" . $i . "]";
				} else {
					$this->qvar[$qvarname] = [ $this->relativeXPath . "/*[" . $i . "]" ];
				}
			} else {
				if ( $child->nodeType == XML_ELEMENT_NODE ) {
					$i++;
					// $out .= './text() = \''.$node->nodeValue . '\'';
					if ( $hastext ) {
						$out .= ' and ';
					}
					if ( !$isRoot ) {
						$out .= '*[' . $i . ']/name() =\'' . $child->localName . '\'';
					}
					$hastext = true;
					if ( $child->hasChildNodes() ) {
						if ( !$isRoot ) {
							$this->relativeXPath .= "/*[" . $i . "]";
							$out .= ' and *[' . $i . "]";
						}
						$out .= '[' . $this->generateConstraint( $child ) . ']';
					}
				} elseif ( $child->nodeType == XML_TEXT_NODE ) {
					$out .= './text() = \'' . $child->nodeValue . '\'';
				}
			}
		}
		if ( !$isRoot ) {
			if ( $this->lengthConstraint == '' ) {
				$this->lengthConstraint .= 'fn:count($x' . $this->relativeXPath . '/*) = ' . $i . "\n";
			} else {
				$this->lengthConstraint .= ' and fn:count($x' . $this->relativeXPath . '/*) = ' . $i . "\n";
			}
		}
		if ( $this->relativeXPath ) {
			$this->relativeXPath = substr( $this->relativeXPath, 0, strrpos( $this->relativeXPath, "/" ) );
		}
		/*if ($out != ""){
			$out = '['. $out . ']';
		}*/

		return $out;
	}

	/**
	 * @param mixed $p
	 * @return bool
	 */
	private function hasChild( $p ): bool {
		if ( $p->hasChildNodes() ) {
			foreach ( $p->childNodes as $c ) {
				if ( $c->nodeType == XML_ELEMENT_NODE ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Returns the XQUERY_HEADER string
	 *
	 * @return string
	 */
	abstract protected function getHeader();

	/**
	 * Returns the XQUERY_FOOTER string
	 *
	 * @return string
	 */
	abstract protected function getFooter();
}
