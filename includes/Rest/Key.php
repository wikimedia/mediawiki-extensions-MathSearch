<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use MediaWiki\Extension\MathSearch\Keys\Ed25519Key;
use MediaWiki\Extension\MathSearch\Keys\KeyStore;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * GET /math/v0/key/{key}
 *
 * Whether a public key is trusted, so that D-LOCKSS can decide what to pin:
 * 204 if it is, 404 if it is not or is unknown.
 */
class Key extends SimpleHandler {

	public function __construct( private readonly KeyStore $store ) {
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'key' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	public function run( string $key ) {
		$publicKey = Ed25519Key::fromString( $key );
		if ( !$publicKey ) {
			throw new HttpException( "$key is not the peer ID of an Ed25519 key.", 400 );
		}
		if ( !$this->store->isTrusted( $publicKey ) ) {
			throw new HttpException( "$key is not trusted.", 404 );
		}
		return $this->getResponseFactory()->createNoContent();
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}
}
