<?php
namespace MediaWiki\Extension\MathSearch\Engine;

use GuzzleHttp\Exception\GuzzleException;
use MathObject;
use MathQueryObject;
use MediaWiki\Extension\MathSearch\XQuery\XQueryGeneratorBaseX;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use SimpleXMLElement;
use Traversable;

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2014 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @file
 * @ingroup extensions
 */
class BaseX {

	/** @var string */
	protected $type = "mws";
	/** @var int[] */
	protected $relevanceMap = [];
	/** @var int|false */
	protected $size = false;
	protected ?MathQueryObject $query;

	/** @var array<int,array<string,array[]>> */
	protected $resultSet = [];
	private ?string $content;

	public function __construct( ?MathQueryObject $query = null ) {
		$this->query = $query;
	}

	protected function doSearch( string $postData ): string {
		global $wgMathSearchBaseXRequestOptionsReadonly;

		$options = $wgMathSearchBaseXRequestOptionsReadonly;
		$query = new SimpleXMLElement( '<query xmlns="http://basex.org/rest"></query>' );
		$query->addChild( 'text', $postData );

		$options['postData'] = $query->asXML();
		$options['method'] = 'POST';

		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$req = $requestFactory->create( $this->getDatabaseUrl(), $options );
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
	 * @return int|false
	 */
	public function getSize() {
		return $this->size;
	}

	/**
	 * Posts the query to basex and evaluates the result data
	 */
	public function postQuery(): void {
		$postData = $this->getPostData();
		$res = $this->doSearch( $postData );
		$this->processResults( $res );
	}

	/**
	 * TODO: Add error handling.
	 * @param string $res
	 * @return bool
	 */
	private function processResults( $res ): void {
		global $wgOut;
		$wgOut->addWikiTextAsInterface( "<code>{$res}</code>" );
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

	public function update( $harvest = "", ?string $hash = null ): bool {
		$options = $this->getBasicHttpOptions( false );
		$options['body'] = $harvest;
		$options['method'] = 'PUT';
		$options['headers']['Content-Type'] = 'application/xml';

		$url = $this->getDatabaseUrl() . '/' . $hash ?? md5( $harvest ) . '.xml';

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
		global $wgMathSearchBaseXRequestOptionsReadonly;
		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$res = $requestFactory->get( $this->getDatabaseUrl() . '?query=count(//*:expr)',
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

	public function storeMathObject( MathObject $mo ): bool {
		$hash = $mo->getInputHash();
		$mml = $mo->getMathML();
		$this->update( $mml, $hash );

		return true;
	}

	/**
	 * @return int[]
	 */
	public function getRelevanceMap() {
		return $this->relevanceMap;
	}

	/**
	 * @param string $type
	 */
	public function setType( $type ) {
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	protected function getPostData() {
		global $wgMathDebug;
		if ( $this->query->getXQuery() ) {
			return $this->query->getXQuery();
		} else {
			$tmp =
				str_replace( "answsize=\"30\"", " totalreq=\"yes\"",
					$this->getQuery()->getCQuery() );
			$postData = str_replace( "m:", "", $tmp );
			if ( $wgMathDebug ) {
				LoggerFactory::getInstance( 'MathSearch' )->debug( 'MWS query:' . $postData );
			}
			$xqGen = new XQueryGeneratorBaseX( $postData );

			return $xqGen->getXQuery();
		}
	}

	/**
	 * @param MathQueryObject $query
	 * @return BaseX
	 */
	public function setQuery( MathQueryObject $query ) {
		$this->query = $query;
		return $this;
	}

	/**
	 * @return array<int,array<string,array[]>>
	 */
	public function getResultSet() {
		return $this->resultSet;
	}

	public function resetResults() {
		$this->size = false;
		$this->resultSet = [];
		$this->relevanceMap = [];
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return MathQueryObject
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * Returns the full BaseX database URL.
	 *
	 * @return string
	 */
	private function getDatabaseUrl(): string {
		global $wgMathSearchBaseXBackendUrl, $wgMathSearchBaseXDatabaseName;
		return rtrim( $wgMathSearchBaseXBackendUrl, '/' ) . '/' . $wgMathSearchBaseXDatabaseName;
	}
}
