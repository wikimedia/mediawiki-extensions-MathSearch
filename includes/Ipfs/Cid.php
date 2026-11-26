<?php

namespace MediaWiki\Extension\MathSearch\Ipfs;

/**
 * A CIDv1 with a sha2-256 hash of a raw or dag-pb block, as Kubo makes them
 * with the unixfs-v1-2025 profile. Its binary form is 36 bytes long.
 * Binary form: https://specs.ipfs.tech/cid/#decoding-a-binary-cid
 * String form: https://specs.ipfs.tech/cid/#decoding-a-cid-string
 */
class Cid {

	private const string ALPHABET = 'abcdefghijklmnopqrstuvwxyz234567';

	private function __construct( private readonly string $bytes ) {
	}

	public static function fromBytes( string $bytes ): ?self {
		return preg_match( '/^\x01[\x55\x70]\x12\x20.{32}\z/s', $bytes ) ? new self( $bytes ) : null;
	}

	/** Reads the multibase base32 form, e.g. bafkrei… */
	public static function fromString( string $cid ): ?self {
		if ( !preg_match( '/^b[a-z2-7]{58}\z/', $cid ) ) {
			return null;
		}
		$bits = '';
		foreach ( str_split( substr( $cid, 1 ) ) as $digit ) {
			$bits .= sprintf( '%05b', strpos( self::ALPHABET, $digit ) );
		}
		// 58 digits carry 290 bits; the last two must be zero.
		if ( substr( $bits, 288 ) !== '00' ) {
			return null;
		}
		$bytes = '';
		foreach ( str_split( substr( $bits, 0, 288 ), 8 ) as $byte ) {
			$bytes .= chr( bindec( $byte ) );
		}
		return self::fromBytes( $bytes );
	}

	public function getBytes(): string {
		return $this->bytes;
	}

	public function __toString(): string {
		$bits = '';
		foreach ( str_split( $this->bytes ) as $byte ) {
			$bits .= sprintf( '%08b', ord( $byte ) );
		}
		$out = 'b';
		foreach ( str_split( $bits . '00', 5 ) as $digit ) {
			$out .= self::ALPHABET[bindec( $digit )];
		}
		return $out;
	}
}
