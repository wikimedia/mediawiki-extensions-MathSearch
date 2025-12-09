<?php

namespace MediaWiki\Extension\MathSearch\Specials;

use MediaWiki\Config\Config;
use MediaWiki\HTMLForm\Field\HTMLInfoField;
use MediaWiki\HTMLForm\Field\HTMLSelectField;
use MediaWiki\HTMLForm\Field\HTMLTextField;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class SpecialImport extends SpecialPage {

	private HttpRequestFactory $http;
	private Config $config;

	public function __construct( HttpRequestFactory $http, Config $config ) {
		$this->http = $http;
		$this->config = $config;
		parent::__construct( 'ImportFromPid', 'import' );
	}

	public function execute( $subPage ): void {
		parent::execute( $subPage );
		# A formDescriptor Array to tell HTMLForm what to build
		$formDescriptor = [
			'user' => [
				'label' => 'User',
				'class' => HTMLInfoField::class,
				'default' => $this->getUser()->getName(),
			],
			'type' => [
				'label' => 'Type',
				'class' => HTMLSelectField::class,
				'default' => 'doi',
				'options' => [
					'DOI' => 'doi',
					'Wikidata qID' => 'qid',
				]
			],
			'value' => [
				'label' => 'Value',
				'class' => HTMLTextField::class,
			],

		];
		$htmlForm =	HTMLForm::factory( 'codex', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Run' );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
		$htmlForm->show();
	}

	public function processInput( $formData ) {
		$vals = [ $formData['value'] ?? '' ];
		$type = $formData['type'] ?? 'doi';
		$baseUrl = $this->config->get( 'MathSearchImporterUrl' );
		$url = "$baseUrl/import/";
		if ( ( $type ) === 'doi' ) {
			$reqData = [ 'dois' => $vals ];
			$url .= 'doi';
		} elseif ( $type === 'qid' ) {
			$reqData = [ 'qids' => $vals ];
			$url .= 'wikidata';
		} else {
			return false;
		}

		$request = $this->http->create( $url, [
			'method' => 'POST',
			'postData' => FormatJson::encode( $reqData ),
		], __METHOD__ );
		$request->setHeader( 'Content-Type', 'application/json' );

		$result = $request->execute();
		$this->displayFeedback( $request->getContent() );
		if ( !$result->isGood() ) {
			$this->getOutput()->addHTML( "<p style='color:red;'>Importer error: " . $result->getMessage() . "</p>" );
		}
		return $result->isGood();
	}

	private function displayFeedback( ?string $content ): void {
		// If the template exists, render it and return early.
		$tplTitle = Title::newFromText( 'Module:ImportFromPidFeedback' );
		if ( $tplTitle && $tplTitle->exists() ) {
			// Escape any accidental template closures
			$content = str_replace( [ '}}', '{{' ], [ ' } } ', ' } } ' ], $content );

			$this->getOutput()->addWikiTextAsContent( "{{#invoke:ImportFromPidFeedback|format| $content }}" );
			return;
		}
		$json = json_decode( $content );

		if ( isset( $json->errors ) && is_array( $json->errors ) ) {
			foreach ( $json->errors as $error ) {
				$this->getOutput()->addHTML( "<p style='color:red;'>Error: " . htmlspecialchars( $error ) . "</p>" );
			}
		}

			// Summary information
			$count = isset( $json->count ) ? intval( $json->count ) : null;
			$allImported = isset( $json->all_imported ) ? (bool)$json->all_imported : null;
			$qids = isset( $json->qids ) && is_array( $json->qids ) ? $json->qids : [];

		if ( $allImported !== null || $count !== null || !empty( $qids ) ) {
			if ( $allImported === true ) {
				$this->getOutput()->addHTML(
					"<p style='color:green;'>All items imported. Count: " . htmlspecialchars( (string)$count ) . "</p>"
				);
			} else {
				$requested = count( $qids );
				$this->getOutput()->addHTML(
					"<p>Imported: " . htmlspecialchars( (string)$count )
					. " &middot; Requested: " . htmlspecialchars( (string)$requested ) . "</p>"
				);
			}
		}

		// Per-item results: results are expected as an object mapping input -> { qid, status }
		if ( isset( $json->results ) && ( is_object( $json->results ) || is_array( $json->results ) ) ) {
			foreach ( $json->results as $input => $res ) {
				$resObj = is_object( $res ) ? $res : (object)$res;
				$status = isset( $resObj->status ) ? (string)$resObj->status : 'unknown';
				$targetQid = isset( $resObj->qid ) ? (string)$resObj->qid : '';

				$color = ( $status === 'success' ) ? 'green' : ( $status === 'failed' ? 'red' : 'black' );
				$line = "Input: " . htmlspecialchars( (string)$input ) .
					" &middot; Status: " . htmlspecialchars( $status );
				if ( $targetQid !== '' ) {
					$line .= " &middot; Target: " . htmlspecialchars( $targetQid );
				}

				$this->getOutput()->addHTML( "<p style='color:{$color};'>{$line}</p>" );
			}
		}
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}
