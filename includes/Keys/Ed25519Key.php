<?php

namespace MediaWiki\Extension\MathSearch\Keys;

/**
 * An Ed25519 public key as a libp2p peer ID (12D3KooW…), the form D-LOCKSS uses.
 * go-libp2p IDFromPublicKey, swh:1:cnt:b77fb684ec4b4f17d501a6762075940b3ae14d36;lines=162-174
 */
class Ed25519Key {

	private const string ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	/**
	 * Identity multihash and protobuf header of an Ed25519 key,
	 * swh:1:cnt:44e504423b330a655d70b83d23cdfffacfea6e09;lines=7-17
	 */
	private const string PREFIX = "\x00\x24\x08\x01\x12\x20";

	private function __construct( private readonly string $publicKey ) {
	}

	public static function fromString( string $key ): ?self {
		if ( !preg_match( '/^12D3KooW[1-9A-HJ-NP-Za-km-z]{44}$/', $key ) ) {
			return null;
		}
		$bytes = [];
		foreach ( str_split( $key ) as $digit ) {
			$carry = strpos( self::ALPHABET, $digit );
			for ( $i = count( $bytes ) - 1; $i >= 0; $i-- ) {
				$carry += $bytes[$i] * 58;
				$bytes[$i] = $carry & 0xff;
				$carry >>= 8;
			}
			for ( ; $carry; $carry >>= 8 ) {
				array_unshift( $bytes, $carry & 0xff );
			}
		}
		// Each leading 1 stands for a zero byte.
		$binary = str_repeat( "\0", strspn( $key, '1' ) ) . pack( 'C*', ...$bytes );
		if ( strlen( $binary ) !== 38 || !str_starts_with( $binary, self::PREFIX ) ) {
			return null;
		}
		return new self( substr( $binary, strlen( self::PREFIX ) ) );
	}

	/** The raw 32-byte public key */
	public function getBytes(): string {
		return $this->publicKey;
	}

	/**
	 * Checks a signature from `ipfs key sign`, which signs a prefixed message.
	 * Kubo KeyAPI::Sign, swh:1:cnt:27973a706078a5d06a07a3f3d3b88eb649fc76cd;lines=281-316
	 */
	public function verify( string $message, string $signature ): bool {
		$raw = str_starts_with( $signature, 'u' ) ?
			base64_decode( strtr( substr( $signature, 1 ), '-_', '+/' ), true ) : false;
		return is_string( $raw ) && strlen( $raw ) === SODIUM_CRYPTO_SIGN_BYTES &&
			sodium_crypto_sign_verify_detached( $raw, 'libp2p-key signed message:' . $message, $this->publicKey );
	}
}
