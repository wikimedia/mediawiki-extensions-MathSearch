<?php

use MediaWiki\MediaWikiServices;

class WikidataDriver {

	/** @var string */
	private $lang = 'en';
	/** @var bool|array */
	private $data = false;

	/**
	 * @return string
	 */
	public function getBackendUrl() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'main' );
		return $config->get( "MathSearchWikidataUrl" );
	}

	public function search( $term ) {
		$term = urlencode( $term );
		$request = [
			'method' => 'GET',
			'url'    => $this->getBackendUrl() .
				"/w/api.php?format=json&action=wbsearchentities&uselang={$this->lang}" .
				"&language={$this->lang}&search={$term}"
		];

		$multiHttpClient = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->createMultiClient();
		$response = $multiHttpClient->run( $request );
		if ( $response['code'] === 200 ) {
			$json = json_decode( $response['body'] );
			if ( $json && json_last_error() === JSON_ERROR_NONE ) {
				$this->data = $json;
				return true;
			}
		}
		$this->data = false;
		return false;
	}

	private function element2String( \stdClass $d, bool $desc = true ): string {
		if ( $desc && isset( $d->description ) ) {
			return "{$d->label} ({$d->description})";
		} else {
			return $d->label;
		}
	}

	public function getResults( $links = false, $desc = true ) {
		$res = [];
		if ( $this->data ) {
			foreach ( $this->data->search as $d ) {
				if ( $links ) {
					$res[$d->id] = "<a href='{$d->concepturi}'>{$this->element2String($d, $desc)}</a>";
				} else {
					$res[$d->id] = $this->element2String( $d, $desc );
				}
			}
		}
		return $res;
	}
}
