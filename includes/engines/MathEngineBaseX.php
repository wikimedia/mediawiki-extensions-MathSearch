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
	protected $type = "mws";
	function __construct( $query = null ) {
		global $wgMathSearchBaseXBackendUrl;
		parent::__construct( $query, $wgMathSearchBaseXBackendUrl . 'api/mwsquery' );
	}

	/**
	 * TODO: Add error handling.
	 * @param $res
	 * @param $numProcess
	 * @return bool
	 */
	protected function processResults( $res, $numProcess ) {
		$jsonResult = json_decode( $res );
		if ( $jsonResult && json_last_error() === JSON_ERROR_NONE ) {
			if ( $jsonResult->success && $jsonResult->response ) {
				// $xmlObject = new XmlTypeCheck( $jsonResult->response, null, false );
				try {
					$xRes = new SimpleXMLElement( $jsonResult->response );
				} catch (Exception $e){
					global $wgOut;
					$wgOut->addWikiText("invalid XML <code>{$jsonResult->response}</code>");
					return false;
				}
				$this->processMathResults( $xRes );
				return true;
			} else {
				global $wgOut;
				$wgOut->addWikiText("<code>{$jsonResult->response}</code>");
				return false;
			}
		} else {
			return false;
		}
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
		return json_encode( array( "type" => $this->type, "query" => $this->query->getCQuery()) );
	}
}