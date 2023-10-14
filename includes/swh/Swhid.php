<?php

namespace MediaWiki\Extension\MathSearch\Swh;

use MediaWiki\Http\HttpRequestFactory;
use Throwable;

class Swhid {
	private $url;
	private $httpFactory;

	private ?string $snapshot = null;

	private $snapshotDate;
	private int $status;

	private int $wait = 0;

	public function __construct(
		HttpRequestFactory $httpFactory, string $url
	) {
		$this->url = $url;
		$this->httpFactory = $httpFactory;
	}

	public function getWait(): int {
		return $this->wait;
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
	public function getSnapshot(): ?string {
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
		} else {
			$this->snapshot = null;
			$this->snapshotDate = null;
		}

		return false;
	}

	public function getBody( string $destination, string $method = 'GET' ): ?string {
		global $wgMathSearchSwhToken;
		$req = $this->httpFactory->create( $destination, [
			'method' => $method,
		] );
		if ( $wgMathSearchSwhToken ) {
			$req->setHeader( 'Authorization', 'Bearer ' . $wgMathSearchSwhToken );
		}
		$res = $req->execute();
		$this->status = $req->getStatus();
		if ( $res->isOK() ) {
			return $req->getContent();
		}
		try {
			if ( $this->status == 429 ) {
				$json = json_decode( $req->getContent() );
				if ( preg_match( '/(?P<seconds>\d+)\s+[sS]/', $json->reason, $match ) ) {
					$this->wait = $match['seconds'];
					return null;
				}
			}
		} catch ( Throwable $exception ) {
			// empty
		}
		// wait for 1 hour
		$this->wait = 3600;
		return null;
	}

	public function getStatus() {
		return $this->status;
	}

	public function saveCodeNow() {
		$destination = "https://archive.softwareheritage.org/api/1/origin/save/git/url/$this->url/";
		$body = $this->getBody( $destination, 'POST' );

		return $body !== null;
	}

}
