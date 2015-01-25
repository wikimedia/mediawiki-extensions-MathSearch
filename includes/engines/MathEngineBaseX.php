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
class MathEngineBaseX extends MathEngineRest {
	function __construct(MathQueryObject $query) {
		global $wgMathSearchBaseXBackendUrl;
		parent::__construct( $query, $wgMathSearchBaseXBackendUrl );
	}


	/**
	 * @param SimpleXMLElement $xmlRoot
	 */
	function processMathResults( $xmlRoot ) {
		wfProfileIn( __METHOD__ );
		foreach ( $xmlRoot->children( )->children() as $page ) {
			$attrs = $page->attributes();
			$uri = explode( ".", $attrs["id"] );
			$revisionID = $uri[1];
			$AnchorID = $uri[2];
			$this->relevanceMap[$revisionID] = true;
			$substarr = array();
			//TODO: Add hit support.
			$this->resultSet[(string) $revisionID][(string) $AnchorID][] = array( "xpath" => (string) $attrs["xpath"], "mappings" => $substarr ); // ,"original"=>$page->asXML()
		}
		wfProfileOut( __METHOD__ );
	}
}