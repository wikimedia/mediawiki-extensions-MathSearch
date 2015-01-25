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
class MathEngineMws extends MathEngineRest {

	function __construct( MathQueryObject $query ) {
		global $wgMathSearchMWSUrl;
		parent::__construct( $query, $wgMathSearchMWSUrl );
	}

	/**
	 * @param SimpleXMLElement $xmlRoot
	 */
	function processMathResults( $xmlRoot ) {
		wfProfileIn( __METHOD__ );
		foreach ( $xmlRoot->children( "mws", TRUE ) as $page ) {
			$attrs = $page->attributes();
			$uri = explode( ".", $attrs["uri"] );
			$revisionID = $uri[1];
			$AnchorID = $uri[2];
			$this->relevanceMap[$revisionID] = true;
			$substarr = array();
			// $this->mathResults[(string) $pageID][(string) $AnchorID][]=$page->asXML();
			foreach ( $page->children( "mws", TRUE ) as $substpair ) {
				$substattrs = $substpair->attributes();
				$substarr[] = array( "qvar" => (string) $substattrs["qvar"], "xpath" => (string) $substattrs["xpath"] );
			}
			$this->resultSet[(string) $revisionID][(string) $AnchorID][] = array( "xpath" => (string) $attrs["xpath"], "mappings" => $substarr ); // ,"original"=>$page->asXML()
		}
		wfProfileOut( __METHOD__ );
	}

}
