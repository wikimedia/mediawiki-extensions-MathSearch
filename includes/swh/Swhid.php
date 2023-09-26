<?php

namespace MediaWiki\Extension\MathSearch\Swh;

use MediaWiki\Http\HttpRequestFactory;

class Swhid {
	private $url;
	private $httpFactory;

	private string $snapshot;

	private $snapshotDate;

	public function __construct(
		HttpRequestFactory $httpFactory, string $url
	) {
		$this->url = $url;
		$this->httpFactory = $httpFactory;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string {
		return $this->url;
	}

	/**
	 * @return string
	 */
	public function getSnapshot(): string {
		return $this->snapshot;
	}

	/**
	 * @return mixed
	 */
	public function getSnapshotDate() {
		return $this->snapshotDate;
	}

	public function fetchSnapshot() {
		$destination = "https://archive.softwareheritage.org/api/1/origin/$this->url/visit/latest/";
		$body = $this->httpFactory->get( $destination );
		if ( $body !== null ) {
			$content = json_decode( $body );
			if ( $content->status === 'full' ) {
				$this->snapshot = 'swh:1:snp:' . $content->snapshot;
				$this->snapshotDate = $content->date;
				return true;
			}
		}

		return false;
	}

	public function saveCodeNow() {
		$destination = "https://archive.softwareheritage.org/api/1/origin/save/git/url/$this->url/";
		$body = $this->httpFactory->post( $destination );
		return $body !== null;
	}

	public function fetchOrSave() {
		return $this->fetchSnapshot() || $this->saveCodeNow();
	}

}
