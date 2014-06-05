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
class XQueryGeneratorBaseX extends XQueryGenerator {
	/**
	 * 
	 * @return string
	 */
	protected function getHeader(){
		return 'declare default element namespace "http://www.w3.org/1998/Math/MathML";
for $m in //*:expr return
';
	}

	/**
	 * 
	 * @return string
	 */
	protected function getFooter(){
		return ' <a href="http://demo.formulasearchengine.com/index.php?curid={$m/@url}">result</a>';
	}
}