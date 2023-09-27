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

	public function fetchOrSave() {
		return $this->fetchSnapshot() || $this->saveCodeNow();
	}

	public function fetchSnapshot() {
		$destination = "https://archive.softwareheritage.org/api/1/origin/$this->url/visit/latest/";
		$body = $this->getBody( $destination, 'GET' );
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

	/**
	 * @param string $destination
	 * @return string|null
	 */
	public function getBody( string $destination, string $method = 'GET' ): ?string {
		global $wgMathSearchSwhToken;
		$req = $this->httpFactory->create( $destination, [
			'method' => $method,
		] );
		if ( $wgMathSearchSwhToken ) {
			$req->setHeader( 'Authorization', 'Bearer ' . $wgMathSearchSwhToken );
		}
		$res = $req->execute();

		return $res->isOK() ? $req->getContent() : null;
	}

	public function saveCodeNow() {
		$destination = "https://archive.softwareheritage.org/api/1/origin/save/git/url/$this->url/";
		$body = $this->getBody( $destination, 'POST' );

		return $body !== null;
	}

}
