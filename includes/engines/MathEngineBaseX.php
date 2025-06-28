<?php

use GuzzleHttp\Exception\GuzzleException;
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
class MathEngineBaseX extends MathEngineRest {

	/** @var string */
	protected $type = "mws";
	private ?string $content;

	public function __construct( $query = null ) {
		global $wgMathSearchBaseXBackendUrl;
		parent::__construct( $query, $wgMathSearchBaseXBackendUrl . 'mwsquery' );
	}

	protected static function doPost( string $url, string $postData ): string {
		global $wgMathSearchBaseXBackendUrl, $wgMathSearchBaseXRequestOptionsReadonly;

		$options = $wgMathSearchBaseXRequestOptionsReadonly;

		$postData = str_replace( [ '{', '}' ], [ '{{', '}}' ], $postData );

		$query = new SimpleXMLElement( '<query xmlns="http://basex.org/rest"></query>' );
		$query->addChild( 'text', $postData );

		$options['postData'] = $query->asXML();

		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$req = $requestFactory->create( $wgMathSearchBaseXBackendUrl, $options );
		$res = $req->execute();
		if ( $res->isOK() ) {
			return $req->getContent();
		}
		return false;
	}

	public function executeQuery( string $xq, string $db = '', ?array $options = null ) {
		global $wgMathSearchBaseXBackendUrl, $wgMathSearchBaseXRequestOptions;

		$doc = new DOMDocument();
		$query = $doc->createElement( 'query' );
		$query->setAttribute( 'xmlns', 'http://basex.org/rest' );
		$doc->appendChild( $query );
		$text = $doc->createElement( 'text', $xq );
		$query->appendChild( $text );

		$options ??= $this->getBasicHttpOptions( $wgMathSearchBaseXRequestOptions );
		$options['method'] = 'POST';
		$options['postData'] = $doc->saveXML();

		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$req = $requestFactory->create( $wgMathSearchBaseXBackendUrl . "/$db", $options );
		$res = $req->execute();
		$this->content = $req->getContent();
		return $res->isOK();
	}

	/**
	 * Retrieves the last recorded error.
	 *
	 * @return string|null The last error value stored, or null if no error exists.
	 */
	public function getContent(): ?string {
		return $this->content;
	}

	/**
	 * Gets the timeouts and authentication information from the config
	 * @param bool $readOnly
	 * @return array
	 */
	private function getBasicHttpOptions( bool $readOnly = false ): array {
		global $wgMathSearchBaseXRequestOptions, $wgMathSearchBaseXRequestOptionsReadonly;
		$options = $readOnly ? $wgMathSearchBaseXRequestOptionsReadonly : $wgMathSearchBaseXRequestOptions;
		// See T399373 why this is required
		if ( isset( $options['username'] ) && isset( $options['password'] ) ) {
			$options['headers']['Authorization'] = 'Basic ' .
				base64_encode( $options['username'] . ':' . $options['password'] );
		}
		return $options;
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

	public function update( $harvest = "", array $delte = [] ): bool {
		global $wgMathSearchBaseXBackendUrl, $wgMathSearchBaseXDatabaseName;

		$options = $this->getBasicHttpOptions( false );
		$options['body'] = $harvest;
		$options['method'] = 'PUT';
		$options['headers']['Content-Type'] = 'application/xml';

		$url = $wgMathSearchBaseXBackendUrl . '/' . $wgMathSearchBaseXDatabaseName . '/' . md5( $harvest ) . '.xml';

		$client = MediaWikiServices::getInstance()->getHttpRequestFactory()->createGuzzleClient( $options );
		try {
			$res = $client->put( $url, $options );
		} catch ( GuzzleException $e ) {
			LoggerFactory::getInstance( 'MathSearch' )->info( 'Can not update basex formula index:'
				. $e->getMessage() );
			return false;
		}
		return $res->getStatusCode() === 201;
	}

	public function getTotalIndexed(): int {
		global $wgMathSearchBaseXBackendUrl, $wgMathSearchBaseXRequestOptionsReadonly, $wgMathSearchBaseXDatabaseName;
		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$res = $requestFactory->get(
			$wgMathSearchBaseXBackendUrl . '/' . $wgMathSearchBaseXDatabaseName . '?query=count(//*:expr)',
			$wgMathSearchBaseXRequestOptionsReadonly );
		if ( $res && is_numeric( $res ) ) {
			return $res;
		}
		return 0;
	}

	public function getDatabases(): Traversable {
		global $wgMathSearchBaseXBackendUrl;
		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$res = $requestFactory->get( $wgMathSearchBaseXBackendUrl, $this->getBasicHttpOptions( false ) );
		$xml = simplexml_load_string( $res, null, 0, 'http://basex.org/rest' );
		foreach ( $xml->database as $database ) {
			yield (string)$database;
		}
	}
}
