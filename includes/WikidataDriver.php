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
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'main' );
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
		$serviceClient = new VirtualRESTServiceClient(
			MediaWikiServices::getInstance()->getHttpRequestFactory()->createMultiClient()
		);
		$response = $serviceClient->run( $request );
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

	private function element2String( $d, $desc = true ) {
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
