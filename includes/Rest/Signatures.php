<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use MediaWiki\Extension\MathSearch\Ipfs\Cid;
use MediaWiki\Extension\MathSearch\Keys\CidSignatures;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * GET /math/v0/signatures/{cid}
 *
 * The keys that signed a CID and their signatures, for the manifest that D-LOCKSS
 * writes. Several users may have uploaded the same file.
 */
class Signatures extends SimpleHandler {

	public function __construct( private readonly CidSignatures $signatures ) {
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'cid' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	public function run( string $cid ) {
		$parsed = Cid::fromString( $cid );
		if ( !$parsed ) {
			throw new HttpException( "$cid is not a CIDv1 in base32 with a sha2-256 hash.", 400 );
		}
		return $this->signatures->get( $parsed );
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}
}
