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
class MathEngineMws {
	/** @var MathQueryObject the query to be answered*/
	protected $query;
	protected $size = false;
	protected $resultSet;
	protected $relevanceMap;
	/**
	 * 
	 * @return MathQueryObject
	 */
	public function getQuery() {
		return $this->query;
	}
	function __construct(MathQueryObject $query) {
		$this->query = $query;
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
	 * @return \MathSearchEngine
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
		global $wgMathSearchMWSUrl, $wgMathDebug;

		$numProcess = 30000;
		$tmp = str_replace( "answsize=\"30\"", "answsize=\"$numProcess\" totalreq=\"yes\"", $this->getQuery()->getCQuery() );
		$mwsExpr = str_replace( "m:", "", $tmp );
		wfDebugLog( 'mathsearch', 'MWS query:' . $mwsExpr );
		$res = Http::post( $wgMathSearchMWSUrl, array( "postData" => $mwsExpr, "timeout" => 60 ) );
		if ( $res == false ) {
			if ( function_exists( 'curl_init' ) ) {
				$handle = curl_init();
				$options = array(
					CURLOPT_URL => $wgMathSearchMWSUrl,
					CURLOPT_CUSTOMREQUEST => 'POST', // GET POST PUT PATCH DELETE HEAD OPTIONS
				);
				// TODO: Figure out how not to write the error in a message and not in top of the output page
				curl_setopt_array( $handle, $options );
				$details = curl_exec( $handle );
			} else {
				$details = "curl is not installed.";
			}
			wfDebugLog( "MathSearch", "Nothing retreived from $wgMathSearchMWSUrl. Check if mwsd is running. Error:" .
					var_export( $details, true ) );
			return false;
		}
		$xres = new SimpleXMLElement( $res );
		$this->size = (int) $xres["total"];
		wfDebugLog( "MathSearch", $this->size . " results retreived from $wgMathSearchMWSUrl." );
		if ($this->size == 0) {
			return true;
		}
		$this->relevanceMap = array();
		$this->resultSet = array();
		$this->processMathResults( $xres );
		if ( $this->size >= $numProcess ) {
			ini_set( 'memory_limit', '256M' );
			for ( $i = $numProcess; $i <= $this->size; $i += $numProcess ) {
				$query = str_replace( "limitmin=\"0\" ", "limitmin=\"$i\" ", $mwsExpr );
				$res = Http::post( $wgMathSearchMWSUrl, array( "postData" => $query, "timeout" => 60 ) );
				wfDebugLog( 'mathsearch', 'MWS query:' . $query );
				if ( $res == false ) {
					wfDebugLog( "MathSearch", "Nothing retreived from $wgMathSearchMWSUrl. check if mwsd is running there" );
					return false;
				}
				$xres = new SimpleXMLElement( $res );
				$this->processMathResults( $xres );
			}
		}
		return true;
	}
	/**
	 * @param unknown $xmlRoot
	 */
	function processMathResults( $xmlRoot ) {
		foreach ( $xmlRoot->children( "mws", TRUE ) as $page ) {
			$attrs = $page->attributes();
			$uri = explode( "#", $attrs["uri"] );
			$pageID = $uri[0];
			$AnchorID = $uri[1];
			$this->relevanceMap[$pageID] = true;
			$substarr = array();
			// $this->mathResults[(string) $pageID][(string) $AnchorID][]=$page->asXML();
			foreach ( $page->children( "mws", TRUE ) as $substpair ) {
				$substattrs = $substpair->attributes();
				$substarr[] = array( "qvar" => (string) $substattrs["qvar"], "xpath" => (string) $substattrs["xpath"] );
			}
			$this->resultSet[(string) $pageID][(string) $AnchorID][] = array( "xpath" => (string) $attrs["xpath"], "mappings" => $substarr ); // ,"original"=>$page->asXML()
		}
	}
}
