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
	function __construct( $query = null ) {
		global $wgMathSearchBaseXBackendUrl;
		parent::__construct( $query, $wgMathSearchBaseXBackendUrl );
	}

	/**
	 * @param SimpleXMLElement $xmlRoot
	 */
	function processMathResults( $xmlRoot ) {
		foreach ( $xmlRoot->children( )->children() as $page ) {
			$attrs = $page->attributes();
			$uri = explode( ".", $attrs["id"] );
			$revisionID = $uri[1];
			$AnchorID = $uri[2];
			$this->relevanceMap[] = $revisionID;
			$substarr = array();
			//TODO: Add hit support.
			$this->resultSet[(string) $revisionID][(string) $AnchorID][] = array( "xpath" => (string) $attrs["xpath"], "mappings" => $substarr ); // ,"original"=>$page->asXML()
		}
		$this->relevanceMap = array_unique( $this->relevanceMap );
	}

	/**
	 *
	 *
	 */
	function getPostData( $numProcess ){
		return json_encode( array( "type" => $this->type, "query" => $this->query ) );
	}
}