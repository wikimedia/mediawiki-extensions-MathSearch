<?php

namespace MediaWiki\Extension\MathSearch\Tests\WikiTexVC;

/**
 * Algorithm to make a simple, but customizable comparison of two MathML-Strings for automated testing.
 * It compares all element keys in the tree without order and calculates an F-score based on similarity.
 * Also, it gives information the elements which differ both trees.
 *
 * Moved from the Math extension; see T121100.
 *
 * @author Johannes Stegmüller
 */
class MMLComparator {

	/**
	 * These keys for mathml-elements get not
	 * considered for similarity comparison.
	 */
	private const IGNOREDELEMENTKEYS = [
		"semantics",
		"annotation",
		"annotation-xml",
		"csymbol",
		"mrow",
		"mstyle"
	];

	/**
	 * Although the keys mentioned here may be on the ignore list.
	 * Keys containing one of the specific attribute get checked.
	 */
	private const CHECKEXPLICITLY = [
		"mstyle" => [ "mathcolor" ]
	];

	/**
	 * Attributes which are not considered for similarity checks.
	 * Attributes are always defined for a specific mathml key.
	 */
	private const IGNOREDATTRIBUTES = [
		"mrow" => [ "class", "data-mjx-texclass" ],
		"math" => [ "alttext", "display" ],
		"mi"   => [ "class", "data-mjx-variant", "mathvariant", "data-mjx-texclass", "data-mjx-alternate" ],
		"mo"   => [ "data-mjx-pseudoscript", "stretchy", "fence", "data-mjx-texclass", "texClass", "class",
			"data-mjx-alternate", "form", "accent", "lspace", "rspace", "xref", "id" ],
		"mtext" => [ "texClass", "class" ],
		"mspace" => [ "width" ]
	];

	/**
	 * FIXME: unreachable — convertToBracketFormat() declares \SimpleXMLElement but
	 * gets the XMLNode from xmlToNode(). Repair or delete, see T121100.
	 */
	public static function functionObtainTreeInBrackets( string $mml ): string {
		$xml = simplexml_load_string( $mml );
		$nodes = self::xmlToNode( $xml );
		return self::convertToBracketFormat( $nodes );
	}

	/**
	 * Compares the base to the comparison MathML.
	 * The base is considered the reference MathML. Calculates an F-Score for similarity of elements.
	 * MathML elements which are not considered for similarity checks can be specified within this class.
	 * Gives back the differing elements relative to the base.
	 * If any issue with the input, the returned F-Score has value '-0.0'
	 *
	 * @param string $mmlBase MathML base element as string
	 * @param string $mmlComp MathML comparison element as string
	 * @return array with 'similarityF': Calculated F-Score for similarity, 'more/less': diffs relative to the base.
	 */
	public function compareMathML( $mmlBase, $mmlComp ): array {
		$compRes = [
			"similarityF" => -0.0,
			"more" => [],
			"less" => []
		];

		if ( !$mmlBase || !$mmlComp || trim( $mmlBase ) == "" || trim( $mmlComp ) == "" ) {
			return $compRes;
		}

		$xmlBase = simplexml_load_string( $mmlBase );
		$xmlComp = simplexml_load_string( $mmlComp );

		$mbase = $this->createComparisonArray( $xmlBase );
		$mcomp = $this->createComparisonArray( $xmlComp );

		$countsBase = $this->countArraySize( $mbase );
		$countsComp = $this->countArraySize( $mcomp );
		$overallRelevantSum = $countsBase[0];
		$overallRetrievedSum = $countsComp[0];

		$ctrSame = $this->compareMathMLKeyArrays( $compRes, $mbase, $mcomp );

		$compRes['similarityF'] = $this->calculateFscore( $ctrSame, $overallRelevantSum, $overallRetrievedSum );
		return $compRes;
	}

	public static function xmlToNode( \SimpleXMLElement $xml ): XMLNode {
		$node = new XMLNode( $xml->getName() );
		foreach ( $xml->children() as $child ) {
			$node->children[] = self::xmlToNode( $child );
		}
		return $node;
	}

	public static function convertToBracketFormat( \SimpleXMLElement $root ): string {
		if ( empty( $root->children ) ) {
			return "{" . $root->value . "}";
		}
		$result = $root->value . '{';
		foreach ( $root->children as $child ) {
			$result .= self::convertToBracketFormat( $child );
		}
		$result .= '}';
		return $result;
	}

	private function compareMathMLKeyArrays( array &$compRes, array $mbase, array $mcomp ): int {
		$intersections = 0;
		foreach ( $mbase as $key => $baseElement ) {
			$compElement = $mcomp[$key] ?? null;
			if ( $compElement == null ) {
				continue;
			}
			$compRet = $this->compareArrays( $baseElement, $compElement );
			$intersections += $compRet['sameCtr'];
			if ( count( $compRet['more'] ) > 0 ) {
				$compRes['more'][$key] = $compRet['more'];
			}
			if ( count( $compRet['less'] ) > 0 ) {
				$compRes['less'][$key] = $compRet['less'];
			}
		}
		return $intersections;
	}

	private function calculateFscore( int $intersection, int $sumRelevant, int $sumRetrieved ): float {
		if ( $sumRelevant == 0 && $sumRetrieved == 0 ) {
			return 1.0;
		}
		if ( $sumRetrieved == 0 && $sumRelevant != 0 ) {
			return 0;
		}
		if ( $sumRelevant == 0 && $sumRetrieved != 0 ) {
			$recall = 1;
		} else {
			$recall = $intersection / $sumRelevant;
		}
		$prec = $intersection / $sumRetrieved;
		if ( ( $prec + $recall ) == 0 ) {
			return 0;
		}
		return 2 * ( ( $prec * $recall ) / ( $prec + $recall ) );
	}

	private function countArraySize( array $arr ): array {
		$overallSize = 0;
		$overallAttrs = 0;
		foreach ( $arr as $element ) {
			foreach ( $element as $attrs ) {
				$overallSize++;
				$overallAttrs += count( $attrs );
			}
		}
		return [ $overallSize, $overallAttrs ];
	}

	private function compareArrays( array $base, array $comp ): array {
		$compRet = [ "sameCtr" => 0, "less" => [], "more" => [] ];
		$sameCtr = 0;
		foreach ( $base as $keyBase => $baseEl ) {
			foreach ( $comp as $keyComp => $compEl ) {
				if ( $compEl == -1 ) {
					continue;
				}
				if ( $this->compareTwoBaseElements( $compEl, $baseEl ) ) {
					$base[$keyBase] = -1;
					$comp[$keyComp] = -1;
					$sameCtr += 1;
					break;
				}
			}
		}
		$compRet['sameCtr'] = $sameCtr;
		$compRet = $this->addRemainingElements( $compRet, 'less', $base );
		return $this->addRemainingElements( $compRet, 'more', $comp );
	}

	private function addRemainingElements( array $compRet, string $key, array $data ): array {
		foreach ( $data as $el ) {
			if ( $el !== null && $el != -1 ) {
				$compRet[$key][] = $el;
			}
		}
		return $compRet;
	}

	private function compareTwoBaseElements( array $compEl, array $baseEl ): bool {
		return $compEl === $baseEl ||
			( !array_diff( $compEl, $baseEl ) && !array_diff( $baseEl, $compEl ) );
	}

	private function createComparisonArray( \SimpleXMLElement $xml ): array {
		$allKeys = [];
		$this->prepareMMLElements( $xml, $allKeys );
		return $this->filterKeys( $allKeys );
	}

	private function filterKeys( array $allMMLElements ): array {
		$finalKeys = [];
		foreach ( $allMMLElements as $key => $element ) {
			if ( in_array( $key, self::IGNOREDELEMENTKEYS, true ) ) {
				$finalKeys = $this->checkExplicitKeys( $key, $element, $finalKeys );
				continue;
			}
			$finalKeys[$key] = $element;
		}
		return $finalKeys;
	}

	private function prepareMMLElements( \SimpleXMLElement $xml, array &$finalArray, bool $alwaysSetVal = false ) {
		foreach ( $xml as $key => $element ) {
			if ( $element instanceof \SimpleXMLElement ) {
				$el = $this->filterAttributes( $key, $element->attributes() );
				$value = trim( (string)$element );
				if ( $alwaysSetVal || $value !== "" ) {
					$el["val"] = $value;
				}
				$finalArray[$key][] = $el ?? [];
			} else {
				$finalArray[$key][] = $element;
			}
			$this->prepareMMLElements( $element, $finalArray );
		}
	}

	private function filterAttributes( string $elementKey, \SimpleXMLElement $attributes ): array {
		$ignoredAttrs = self::IGNOREDATTRIBUTES[$elementKey] ?? [];
		$finalAttributes = [];
		foreach ( $attributes as $akey => $aval ) {
			if ( in_array( $akey, $ignoredAttrs, true ) ) {
				continue;
			}
			$finalAttributes[$akey] = $aval[0];
		}
		return $finalAttributes;
	}

	private function checkExplicitKeys( string $key, array $element, array $finalKeys ): array {
		$check = self::CHECKEXPLICITLY[$key] ?? null;
		if ( $check ) {
			foreach ( $element as $added ) {
				foreach ( $check as $checkEl ) {
					if ( array_key_exists( $checkEl, $added ) ) {
						$finalKeys[$key] = $element;
					}
				}
			}
		}
		return $finalKeys;
	}
}
