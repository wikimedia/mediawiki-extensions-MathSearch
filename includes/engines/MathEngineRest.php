<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2014 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @file
 * @ingroup extensions
 */
abstract class MathEngineRest {
	/** @var MathQueryObject the query to be answered */
	protected $query;
	/** @var string */
	protected $type = "mws";
	/** @var int|false */
	protected $size = false;
	/** @var array<int,array<string,array[]>> */
	protected $resultSet = [];
	/** @var int[] */
	protected $relevanceMap = [];
	/** @var string */
	protected $backendUrl = "http://localhost:9090";

	/**
	 * @param string $url
	 * @param array|string $postData
	 * @return string|false
	 */
	protected static function doPost( string $url, $postData ) {
		$res = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->post( $url, [ "postData" => $postData, "timeout" => 60 ], __METHOD__ );
		if ( $res === null ) {
			if ( function_exists( 'curl_init' ) ) {
				$handle = curl_init();
				$options = [
					CURLOPT_URL => $url,
					CURLOPT_CUSTOMREQUEST => 'POST', // GET POST PUT PATCH DELETE HEAD OPTIONS
				];
				// TODO: Figure out how not to write the error in a message and not in top of the output page
				curl_setopt_array( $handle, $options );
				$details = curl_exec( $handle );
			} else {
				$details = "curl is not installed.";
			}
			LoggerFactory::getInstance(
				'MathSearch'
			)->error( 'Nothing retrieved from $url. Check if server is running. Error:' .
				var_export( $details, true ) );
			return false;
		} else {
			return $res;
		}
	}

	/**
	 * @return string
	 */
	public function getBackendUrl() {
		return $this->backendUrl;
	}

	/**
	 * @param string $backendUrl
	 */
	public function setBackendUrl( $backendUrl ) {
		$this->backendUrl = $backendUrl;
	}

	/**
	 * @return MathQueryObject
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * @param MathQueryObject|null $query
	 * @param bool|string $url
	 */
	public function __construct( $query = null, $url = false ) {
		$this->query = $query;
		if ( $url ) {
			$this->setBackendUrl( $url );
		}
	}

	/**
	 * @return int|false
	 */
	public function getSize() {
		return $this->size;
	}

	/**
	 * @return array<int,array<string,array[]>>
	 */
	public function getResultSet() {
		return $this->resultSet;
	}

	/**
	 * @return int[]
	 */
	public function getRelevanceMap() {
		return $this->relevanceMap;
	}

	/**
	 * @param MathQueryObject $query
	 * @return $this
	 */
	public function setQuery( MathQueryObject $query ) {
		$this->query = $query;
		return $this;
	}

	/**
	 * Posts the query to mwsd and evaluates the result data
	 * @return bool
	 */
	public function postQuery() {
		$numProcess = 30000;
		$postData = $this->getPostData( $numProcess );
		$res = self::doPost( $this->backendUrl, $postData );
		if ( $res === false ) {
			return false;
		} else {
			return $this->processResults( $res, $numProcess );
		}
	}

	/**
	 * @param SimpleXMLElement $xmlRoot
	 */
	abstract protected function processMathResults( $xmlRoot );

	/**
	 * @param int $numProcess
	 * @return string
	 */
	protected function getPostData( $numProcess ) {
		global $wgMathDebug;
		if ( $this->query->getXQuery() ) {
			return $this->query->getXQuery();
		} else {
			$tmp =
				str_replace( "answsize=\"30\"", "answsize=\"$numProcess\" totalreq=\"yes\"",
					$this->getQuery()->getCQuery() );
			$postData = str_replace( "m:", "", $tmp );
			if ( $wgMathDebug ) {
				LoggerFactory::getInstance( 'MathSearch' )->debug( 'MWS query:' . $postData );
				return $postData;
			}
			return $postData;
		}
	}

	/**
	 * @param string $res
	 * @param int $numProcess
	 * @return bool
	 */
	protected function processResults( $res, $numProcess ) {
		try {
			$xres = new SimpleXMLElement( $res );
		} catch ( Exception $e ) {
			LoggerFactory::getInstance( 'MathSearch' )->error( 'No valid XMLRESUSLT' . $res );
			return false;
		}

		$this->size = (int)$xres["total"];
		LoggerFactory::getInstance(
			'MathSearch'
		)->warning( $this->size . " results retrieved from $this->backendUrl." );
		if ( $this->size == 0 ) {
			return true;
		}
		$this->relevanceMap = [];
		$this->resultSet = [];
		$this->processMathResults( $xres );
		if ( $this->size >= $numProcess ) {
			ini_set( 'memory_limit', '256M' );
			for ( $i = $numProcess; $i <= $this->size; $i += $numProcess ) {
				$query = str_replace( "limitmin=\"0\" ", "limitmin=\"$i\" ", $this->postData );
				$res = MediaWikiServices::getInstance()->getHttpRequestFactory()
					->post( $this->backendUrl, [ "postData" => $query, "timeout" => 60 ], __METHOD__ );
				LoggerFactory::getInstance( 'mathsearch' )->debug( 'MWS query:' . $query );
				if ( $res == null ) {
					LoggerFactory::getInstance(
						'MathSearch'
					)->error( "Nothing retrieved from $this->backendUrl. Check if mwsd is running there" );
					return false;
				}
				$xres = new SimpleXMLElement( $res );
				$this->processMathResults( $xres );
			}
		}
		return true;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param string $type
	 */
	public function setType( $type ) {
		$this->type = $type;
	}

	public function resetResults() {
		$this->size = false;
		$this->resultSet = [];
		$this->relevanceMap = [];
	}

}
