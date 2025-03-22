<?php
namespace MediaWiki\Extension\MathSearch\XQuery;

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

	// Define the XQUERY_HEADER and XQUERY_FOOTER constants inside the class
	private const FN_PATH_FROM_ROOT = "declare namespace functx = \"http://www.functx.com\";\n"
	. "declare function functx:path-to-node\n"
	. "  ( \$nodes as node()* )  as xs:string* {\n"
	. "\n"
	. "\$nodes/string-join(ancestor-or-self::*/name(.), '/')\n"
	. " } ;";

	private const XQUERY_HEADER = "declare default element namespace \"http://www.w3.org/1998/Math/MathML\";\n"
	. self::FN_PATH_FROM_ROOT . "<result>{\nlet \$m := .";

	private const XQUERY_FOOTER = "<element><x>{\$x}</x><p>{data(functx:path-to-node(\$x))}</p></element>}\n"
	. "</result>";

	/**
	 * Returns the XQUERY_HEADER string
	 *
	 * @return string
	 */
	protected function getHeader() {
		return self::XQUERY_HEADER;
	}

	/**
	 * Returns the XQUERY_FOOTER string
	 *
	 * @return string
	 */
	protected function getFooter() {
		return self::XQUERY_FOOTER;
	}
}
