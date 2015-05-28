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
		foreach ( $xmlRoot->children( "mws", TRUE ) as $page ) {
			$attrs = $page->attributes();
			$uri = explode( ".", $attrs["uri"] );
			if ( sizeof( $uri ) > 2 ) {
				$revisionID = $uri[1];
				$AnchorID = $uri[2];
				$this->relevanceMap[] = $revisionID;
				$substarr = array();
				// $this->mathResults[(string) $pageID][(string) $AnchorID][]=$page->asXML();
				foreach ( $page->children( "mws", TRUE ) as $substpair ) {
					$substattrs = $substpair->attributes();
					$substarr[] =
						array(
							"qvar"  => (string)$substattrs["qvar"],
							"xpath" => (string)$substattrs["xpath"]
						);
				}
				$this->resultSet[(string)$revisionID][(string)$AnchorID][] =
					array(
						"xpath"    => (string)$attrs["xpath"],
						"mappings" => $substarr
					); // ,"original"=>$page->asXML()
			} else {
				LoggerFactory::getInstance( 'MathSeach' )->error( $attrs["uri"] .
																  ' has an invalid result format.' );
			}
		}
		$this->relevanceMap = array_unique( $this->relevanceMap );
	}

}
