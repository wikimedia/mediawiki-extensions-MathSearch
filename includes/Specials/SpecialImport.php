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

		return $result->isGood();
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}
