<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

class MathosphereDriver {

	/** @var string */
	private $wikiText;
	/** @var string */
	private $language = "en";
	/** @var string */
	private $title = "";
	/** @var string|null */
	private $version;
	/** @var bool|null */
	private $success;
	/** @var array */
	private $relations = [];

	public function __construct( $revisionId = null ) {
		if ( $revisionId !== null ) {
			$revisionRecord = MediaWikiServices::getInstance()
				->getRevisionLookup()
				->getRevisionById( $revisionId );
			/** @var TextContent $content */
			'@phan-var TextContent $content';
			$content = $revisionRecord->getContent( SlotRecord::MAIN );
			$this->wikiText = $content->getText();
		}
	}

	public static function newFromWikitext( $wikiText ) {
		$instance = new MathosphereDriver();
		$instance->setWikiText( $wikiText );
		return $instance;
	}

	public function setWikiText( $wikiText ) {
		$this->wikiText = $wikiText;
	}

	public function getWikiText() {
		return $this->wikiText;
	}

	/**
	 * @return string
	 */
	public function getLanguage() {
		return $this->language;
	}

	/**
	 * @param string $language
	 */
	public function setLanguage( $language ) {
		$this->language = $language;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @param string $title
	 */
	public function setTitle( $title ) {
		$this->title = $title;
	}

	/**
	 * @return string|null
	 */
	public function getVersion() {
		return $this->version;
	}

	public function analyze() {
		return $this->processResults( self::doPost( $this->getBackendUrl() . "/AnalyzeWikiText",
			$this->getPostData() ) );
	}

	protected function processResults( string $res ): bool {
		$jsonResult = json_decode( $res );
		if ( $jsonResult &&
			json_last_error() === JSON_ERROR_NONE &&
			isset( $jsonResult->success )
		) {
			$this->success = $jsonResult->success;
			if ( $this->success ) {
				foreach ( $jsonResult->relations as $r ) {
					$this->addIdentifierDefinitionTuple( $r );
				}
				return true;
			}
		}
		// TODO: Implement error handling
		return false;
	}

	private function addIdentifierDefinitionTuple( \stdClass $r ) {
		$this->relations[$r->identifier][] = $r;
	}

	/**
	 * @param string $url
	 * @param array|string $postData
	 * @return string|false
	 */
	protected static function doPost( string $url, $postData ) {
		$options = [
			"postData" => $postData,
			"timeout"  => 60,
			"method"   => 'POST'
		];
		$req = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $url, $options, __METHOD__ );
		$status = $req->execute();

		if ( $status->isOK() ) {
			return $req->getContent();
		} else {
			$errors = $status->getErrorsByType( 'error' );
			$logger = LoggerFactory::getInstance( 'http' );
			$logger->warning( $status->getWikiText(),
				[ 'error' => $errors, 'caller' => __METHOD__, 'content' => $req->getContent() ] );
			return false;
		}
	}

	/**
	 * @return string
	 */
	public function getBackendUrl() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'main' );
		return $config->get( "MathSearchBaseXBackendUrl" ) . 'api';
	}

	protected function getPostData(): string {
		$post = [
			"wikitext" => $this->wikiText,
			"title" => $this->title
		];
		return json_encode( $post );
	}

	public function checkBackend() {
		$res = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->get( $this->getBackendUrl() . '/_info', [], __METHOD__ );
		if ( $res ) {
			$res = json_decode( $res );
			if ( $res && json_last_error() === JSON_ERROR_NONE ) {
				if ( isset( $res->name ) && $res->name === 'mathosphere' ) {
					$this->version = $res->version;
					// Mathosphere version 3.0.0-SNAPSHOT is only version currently supported
					return ( $this->version === "3.0.0-SNAPSHOT" );
				}
			}
		}
		$logger = LoggerFactory::getInstance( 'MathSearch' );
		$logger->warning( "Mathosphere Backend server backend does not point to mathosphere.", [
			'detail' => $res
		] );
		return false;
	}

	/**
	 * @return array[]
	 */
	public function getRelations() {
		return $this->relations;
	}
}
