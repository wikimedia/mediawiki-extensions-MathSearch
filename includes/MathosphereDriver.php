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
	private $error;
	/** @var bool|null */
	private $success;
	private $relations = [];
	private $identifiers = [];

	function __construct( $revisionId = null ) {
		if ( $revisionId !== null ) {
			$revisionRecord = MediaWikiServices::getInstance()
				->getRevisionLookup()
				->getRevisionById( $revisionId );
			$this->wikiText = $revisionRecord->getContent( SlotRecord::MAIN )->getNativeData();
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

	/**
	 * @return mixed
	 */
	public function getError() {
		return $this->error;
	}

	public function analyze() {
		return $this->processResults( self::doPost( $this->getBackendUrl() . "/AnalyzeWikiText",
			$this->getPostData() ) );
	}

	protected function processResults( $res ) {
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
			} else {
				// TODO: Implement error handling
				return false;
			}
			return true;
		} else {
			return false;
		}
	}

	private function addIdentifierDefinitionTuple( $r ) {
		if ( !isset( $this->relations[$r->identifier] ) ) {
			$this->relations[$r->identifier] = [];
		}
		$this->relations[$r->identifier][] = $r;
	}

	protected static function doPost( $url, $postData ) {
		$options = [
			"postData" => $postData,
			"timeout"  => 60,
			"method"   => 'POST'
		];
		$req = MWHttpRequest::factory( $url, $options, __METHOD__ );
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
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'main' );
		return $config->get( "MathSearchBaseXBackendUrl" ) . 'api';
	}

	protected function getPostData() {
		$post = [
			"wikitext" => $this->wikiText,
			"title" => $this->title
		];
		return json_encode( $post );
	}

	public function checkBackend() {
		$res = Http::get( $this->getBackendUrl() . '/_info' );
		if ( $res ) {
			$res = json_decode( $res );
			if ( $res && json_last_error() === JSON_ERROR_NONE ) {
				if ( isset( $res->name ) && $res->name === 'mathosphere' ) {
					$this->version = $res->version;
					// Mathosphere version 3.0.0-SNAPSHOT is only version currently supported
					if ( $this->version === "3.0.0-SNAPSHOT" ) {
						return true;
					} else {
						return false;
					}
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
