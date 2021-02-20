<?php

use MediaWiki\Logger\LoggerFactory;

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

	function __construct( $query ) {
		global $wgMathSearchMWSUrl;
		parent::__construct( $query, $wgMathSearchMWSUrl );
	}

	/**
	 * @param SimpleXMLElement $xmlRoot
	 */
	function processMathResults( $xmlRoot ) {
		foreach ( $xmlRoot->children( "mws", true ) as $page ) {
			$attrs = $page->attributes();
			if ( strpos( $attrs["uri"], '#' ) ) {
				$uri = explode( "#", $attrs["uri"] );
				$revisionID = $uri[0];
				$AnchorID = $uri[1];
			} else {
				$uri = explode( ".", $attrs["uri"] );
				if ( count( $uri ) > 2 ) {
					$revisionID = $uri[1];
					$AnchorID = $uri[2];
				} else {
					LoggerFactory::getInstance( 'MathSearch' )->error( $attrs["uri"] .
						' has an invalid result format.' );
					continue;
				}
			}
			$this->relevanceMap[] = $revisionID;
			$substarr = [];
			foreach ( $page->children( "mws", true ) as $substpair ) {
				$substattrs = $substpair->attributes();
				$substarr[] =
					[
						"qvar"  => (string)$substattrs["qvar"],
						"xpath" => (string)$substattrs["xpath"]
					];
			}
			$this->resultSet[(string)$revisionID][(string)$AnchorID][] =
				[
					"xpath"    => (string)$attrs["xpath"],
					"mappings" => $substarr
				]; // ,"original"=>$page->asXML()
		}
		$this->relevanceMap = array_unique( $this->relevanceMap );
	}
}
