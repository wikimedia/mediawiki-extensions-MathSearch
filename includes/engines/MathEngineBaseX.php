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

	/** @var string */
	protected $type = "mws";

	public function __construct( $query = null ) {
		global $wgMathSearchBaseXBackendUrl;
		parent::__construct( $query, $wgMathSearchBaseXBackendUrl . 'mwsquery' );
	}

	/**
	 * TODO: Add error handling.
	 * @param string $res
	 * @param int $numProcess
	 * @return bool
	 */
	protected function processResults( $res, $numProcess ) {
		$jsonResult = json_decode( $res ?? '' );
		if ( $jsonResult && json_last_error() === JSON_ERROR_NONE ) {
			if ( $jsonResult->success && $jsonResult->response ) {
				// $xmlObject = new XmlTypeCheck( $jsonResult->response, null, false );
				try {
					$xRes = new SimpleXMLElement( $jsonResult->response );
				} catch ( Exception $e ) {
					global $wgOut;
					$wgOut->addWikiTextAsInterface( "invalid XML <code>{$jsonResult->response}</code>" );
					return false;
				}
				if ( $xRes->run->result ) {
					$this->processMathResults( $xRes );
					return true;
				} else {
					global $wgOut;
					$wgOut->addWikiTextAsInterface( "Result was empty." );
					return false;
				}
			} else {
				global $wgOut;
				$wgOut->addWikiTextAsInterface( "<code>{$jsonResult->response}</code>" );
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param SimpleXMLElement $xmlRoot
	 */
	protected function processMathResults( $xmlRoot ) {
		foreach ( $xmlRoot->run->result->children() as $page ) {
			$attrs = $page->attributes();
			$uri = explode( "#", $attrs["id"] );
			if ( count( $uri ) != 2 ) {
				LoggerFactory::getInstance( 'MathSearch' )->error( 'Can not parse' . $attrs['id'] );
				continue;
			}
			$revisionID = $uri[0];
			$AnchorID = $uri[1];
			$this->relevanceMap[] = $revisionID;
			$substarr = [];
			// TODO: Add hit support.
			$this->resultSet[(string)$revisionID][(string)$AnchorID][] =
				[ "xpath" => (string)$attrs["xpath"], "mappings" => $substarr ];
		}
		$this->relevanceMap = array_unique( $this->relevanceMap );
	}

	public function getPostData( $numProcess ) {
		return json_encode( [ "type" => $this->type, "query" => $this->query->getCQuery() ] );
	}

	public function update( $harvest = "", array $delte = [] ) {
		global $wgMathSearchBaseXBackendUrl;
		$json_payload = json_encode( [ "harvest" => $harvest, "delete" => $delte ] );
		try {
			$res = self::doPost( $wgMathSearchBaseXBackendUrl . 'update', $json_payload );
			if ( $res ) {
				$resJson = json_decode( $res );
				if ( $resJson->success == true ) {
					return true;
				} else {
					LoggerFactory::getInstance( 'MathSearch' )->warning( 'harvest update failed' .
						var_export( $resJson, true ) );
				}
			}
		} catch ( Exception $e ) {
			LoggerFactory::getInstance( 'MathSearch' )
				->warning( 'Harvest update failed: {exception}',
					[ 'exception' => $e->getMessage() ] );
		}
		return false;
	}
}
