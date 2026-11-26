<?php

namespace MediaWiki\Extension\MathSearch\Ipfs;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;

/**
 * Adds files to a Kubo node through its HTTP API.
 */
class IpfsClient {

	public function __construct(
		private readonly ClientInterface $client,
		private readonly string $apiUrl,
		private readonly ?string $token = null
	) {
	}

	/**
	 * Adds and pins a file with the settings of the unixfs-v1-2025 import profile,
	 * so that the CID does not depend on the node's defaults.
	 */
	public function add( string $path, string $name ): string {
		$response = $this->client->request( 'POST', "$this->apiUrl/api/v0/add", [
			'headers' => $this->token === null ? [] : [ 'Authorization' => "Bearer $this->token" ],
			'query' => [
				'cid-version' => 1,
				'raw-leaves' => 'true',
				'hash' => 'sha2-256',
				'chunker' => 'size-1048576',
				'max-file-links' => 1024,
				'pin' => 'true',
			],
			'multipart' => [
				[ 'name' => 'file', 'contents' => Utils::tryFopen( $path, 'r' ), 'filename' => $name ],
			],
		] );
		return json_decode( (string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR )['Hash'];
	}
}
