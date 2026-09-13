<?php

namespace MediaWiki\Extension\MathSearch\Rest;

/**
 * Content address of a formula, as RESTBase computes it: sha1 over
 * fast-json-stable-stringify output, which sorts keys and emits no whitespace.
 * swh:1:cnt:37b1271b57efe5de321bc3a38f93d747d54f2dca;lines=9-14
 */
class FormulaHash {

	public const TYPES = [ 'tex', 'inline-tex', 'chem' ];

	private const RESTBASE_LENGTH = 40;

	public static function fromTex( string $q, string $type = 'tex' ): string {
		return sha1( self::preimage( $q, $type ) );
	}

	/** The exact bytes the address is the sha1 of. */
	public static function preimage( string $q, string $type = 'tex' ): string {
		// stringify leaves slashes and unicode unescaped, PHP does not by default.
		return json_encode(
			[ 'q' => $q, 'type' => $type ],
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}

	/** Well formed as a content address. */
	public static function isRestbase( string $hash ): bool {
		return (bool)preg_match( '/^[0-9a-f]{' . self::RESTBASE_LENGTH . '}$/', $hash );
	}

}
