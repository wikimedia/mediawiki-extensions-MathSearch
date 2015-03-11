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
abstract class MathEngineRest {
	/** @var MathQueryObject the query to be answered*/
	protected $query;
	protected $size = false;
	protected $resultSet = array();
	protected $relevanceMap = array();
	/** @type string */
	protected $backendUrl = "http://localhost:9090";

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
	 * 
	 * @return MathQueryObject
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * @param MathQueryObject $query
	 * @param bool|string     $url
	 */
	function __construct( $query = null, $url = false ) {
		$this->query = $query;
		if ( $url ){
			$this->setBackendUrl( $url );
		}
	}
	public function getSize() {
		return $this->size;
	}

	public function getResultSet() {
		return $this->resultSet;
	}

	public function getRelevanceMap() {
		return $this->relevanceMap;
	}

	/**
	 *
	 * @param MathQueryObject $query
	 * @return $this
	 */
	public function setQuery(MathQueryObject $query) {
		$this->query = $query;
		return $this;
	}

		/**
	 * Posts the query to mwsd and evaluates the result data
	 * @return boolean
	 */
	function postQuery() {
		global $wgMathDebug;
		$numProcess = 30000;
		if ( $this->query->getXQuery() ){
			$postData = $this->query->getXQuery();
		} else {
			$tmp = str_replace( "answsize=\"30\"", "answsize=\"$numProcess\" totalreq=\"yes\"", $this->getQuery()->getCQuery() );
			$postData = str_replace( "m:", "", $tmp );
			if ( $wgMathDebug ) { wfDebugLog( 'MathSearch', 'MWS query:' . $postData ); }
		}
		$res = Http::post( $this->backendUrl, array( "postData" => $postData, "timeout" => 60 ) );
		if ( $res == false ) {
			if ( function_exists( 'curl_init' ) ) {
				$handle = curl_init();
				$options = array(
					CURLOPT_URL => $this->backendUrl,
					CURLOPT_CUSTOMREQUEST => 'POST', // GET POST PUT PATCH DELETE HEAD OPTIONS
				);
				// TODO: Figure out how not to write the error in a message and not in top of the output page
				curl_setopt_array( $handle, $options );
				$details = curl_exec( $handle );
			} else {
				$details = "curl is not installed.";
			}
			wfDebugLog( "MathSearch", "Nothing retreived from $this->backendUrl. Check if mwsd is running. Error:" .
					var_export( $details, true ) );
			return false;
		}
		try{
			$xres = new SimpleXMLElement( $res );
		} catch (Exception $e){
			wfDebugLog( 'MathSearch', "No valid XMLRESUSLT" . $res);
			return false;
		}

		$this->size = (int) $xres["total"];
		wfDebugLog( "MathSearch", $this->size . " results retreived from $this->backendUrl." );
		if ($this->size == 0) {
			return true;
		}
		$this->relevanceMap = array();
		$this->resultSet = array();
		$this->processMathResults( $xres );
		if ( $this->size >= $numProcess ) {
			ini_set( 'memory_limit', '256M' );
			for ( $i = $numProcess; $i <= $this->size; $i += $numProcess ) {
				$query = str_replace( "limitmin=\"0\" ", "limitmin=\"$i\" ", $postData );
				$res = Http::post( $this->backendUrl, array( "postData" => $query, "timeout" => 60 ) );
				wfDebugLog( 'mathsearch', 'MWS query:' . $query );
				if ( $res == false ) {
					wfDebugLog( "MathSearch", "Nothing retreived from $this->backendUrl. check if mwsd is running there" );
					return false;
				}
				$xres = new SimpleXMLElement( $res );
				$this->processMathResults( $xres );
			}
		}
		return true;
	}

	/**
	 * @param SimpleXMLElement $xmlRoot
	 */
	abstract function processMathResults( $xmlRoot ) ;

}