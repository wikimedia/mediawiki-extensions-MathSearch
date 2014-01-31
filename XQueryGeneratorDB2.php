<?php
/**
 * MediaWiki MathSearch extension
 *
 * (c) 2014 Moritz Schubotz
 * GPLv2 license; info in main package.
 * 
 * @file
 * @ingroup extensions
 */
class XQueryGeneratorDB2 extends XQueryGenerator {
	/**
	 * 
	 * @global type $wgMathSearchDB2Table
	 * @return string
	 */
	protected function getHeader(){
		global $wgMathSearchDB2Table;
		return 'xquery declare default element namespace "http://www.w3.org/1998/Math/MathML";'.
			"\n for \$m in db2-fn:xmlcolumn(\"$wgMathSearchDB2Table.math_mathml\") return \n";
	}

	/**
	 * 
	 * @return string
	 */
	protected function getFooter(){
		return 'then
data($m/*[1]/@alttext)
 else \'\' ';
	}
}