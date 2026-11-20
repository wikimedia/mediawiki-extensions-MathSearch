<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use MediaWiki\Config\Config;
use MediaWiki\Extension\MathSearch\Keys\Ed25519Key;
use MediaWiki\Extension\MathSearch\Keys\KeyStore;
use MediaWiki\MainConfigNames;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * POST /math/v0/key
 *
 * Adds a public key to the account after checking a signature made with it.
 */
class AddKey extends SimpleHandler {

	/** Older signatures are rejected, so that a captured one cannot be replayed later. */
	private const int MAX_AGE = 300;

	public function __construct(
		private readonly KeyStore $store,
		private readonly Config $config
	) {
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			'key' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'timestamp' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'signature' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** Only OAuth, so that no other site can add a key through a user's cookies. */
	public function requireSafeAgainstCsrf(): bool {
		return true;
	}

	public function run() {
		$authority = $this->getAuthority();
		if ( !$authority->isAllowed( 'mathtrustedkeys' ) ) {
			throw new HttpException( 'You are not allowed to add keys.', 403 );
		}
		[ 'key' => $key, 'timestamp' => $timestamp, 'signature' => $signature ] = $this->getValidatedBody();
		$publicKey = Ed25519Key::fromString( $key );
		if ( !$publicKey ) {
			throw new HttpException( "$key is not the peer ID of an Ed25519 key.", 400 );
		}
		if ( abs( ConvertibleTimestamp::time() - $timestamp ) > self::MAX_AGE ) {
			throw new HttpException( 'The timestamp is more than five minutes off.', 400 );
		}
		$host = parse_url( $this->config->get( MainConfigNames::CanonicalServer ), PHP_URL_HOST );
		$user = $authority->getUser();
		$message = "mathsearch-key-registration $host {$user->getName()} $timestamp";
		if ( !$publicKey->verify( $message, $signature ) ) {
			throw new HttpException( "The signature does not match \"$message\".", 400 );
		}
		if ( !$this->store->add( $user, $publicKey ) ) {
			throw new HttpException( 'The key belongs to another user.', 409 );
		}
		return $this->getResponseFactory()->createNoContent();
	}
}
