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
		foreach ( $xmlRoot->run->result->children() as $page ) {
			$attrs = $page->attributes();
			$uri = explode( "#", $attrs["id"] );
			if ( sizeof($uri) != 2 ) {
				LoggerFactory::getInstance( 'MathSearch' )->error( 'Can not parse' . $attrs['id'] );
				continue;
			}
			$revisionID = $uri[0];
			$AnchorID = $uri[1];
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

	function update( $harvest = "", array $delte=array() ){
		global $wgMathSearchBaseXBackendUrl;
		$json_payload = json_encode( array( "harvest" => $harvest, "delete" => $delte) );
		$res = self::doPost( $wgMathSearchBaseXBackendUrl. 'api/update', $json_payload);
		if($res){
			$resJson = json_decode($res);
			if ($resJson->success==true){
				return true;
			} else {
				LoggerFactory::getInstance( 'MathSearch' )->warning( 'harvest update failed' . var_export( $resJson, true ) );
			}
		}
		return false;
	}
}
