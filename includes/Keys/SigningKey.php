<?php

namespace MediaWiki\Extension\MathSearch\Keys;

/**
 * An Ed25519 private key that is ready to sign.
 */
class SigningKey {

	public function __construct( private readonly string $secretKey ) {
	}

	public function getKey(): Ed25519Key {
		return Ed25519Key::fromBytes( sodium_crypto_sign_publickey_from_secretkey( $this->secretKey ) );
	}

	/** Signs like `ipfs key sign`, so that both kinds of keys are checked the same way; returns 64 raw bytes. */
	public function sign( string $message ): string {
		return sodium_crypto_sign_detached( Ed25519Key::SIGNED_PREFIX . $message, $this->secretKey );
	}

	/**
	 * The key as `ipfs key import` reads it: a libp2p PrivateKey protobuf of type Ed25519.
	 * swh:1:cnt:44e504423b330a655d70b83d23cdfffacfea6e09;lines=7-22
	 */
	public function export(): string {
		return "\x08\x01\x12\x40" . $this->secretKey;
	}
}
