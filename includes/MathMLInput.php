<?php

namespace MediaWiki\Extension\MathSearch;

use DOMDocument;
use DOMXPath;
use MediaWiki\Config\Config;
use MediaWiki\Parser\Sanitizer;
use StatusValue;

/**
 * Checks MathML that users enter and returns it ready for output.
 */
class MathMLInput {

	private const MATHML_NS = 'http://www.w3.org/1998/Math/MathML';
	private const XML_ENTITIES = [ 'amp', 'lt', 'gt', 'quot', 'apos' ];

	public function __construct(
		private readonly string $schema,
	) {
	}

	public static function newFromConfig( Config $config ): self {
		return new self( $config->get( 'MathSearchMathMLSchema' ) );
	}

	/**
	 * @return StatusValue with the MathML to output as value
	 */
	public function check( string $mathml ): StatusValue {
		$mathml = $this->normalize( $mathml );
		$doc = new DOMDocument();
		$useErrors = libxml_use_internal_errors( true );
		libxml_clear_errors();
		try {
			if ( !$doc->loadXML( $mathml, LIBXML_NONET ) ) {
				return $this->libxmlError();
			}
			// A DTD could define entities
			if ( $doc->doctype ) {
				return StatusValue::newFatal( 'mathsearch-mathml-invalid', 'DOCTYPE' );
			}
			if ( !$doc->relaxNGValidate( $this->schema ) ) {
				return $this->libxmlError();
			}
			// A schema cannot check CSS
			foreach ( ( new DOMXPath( $doc ) )->query( '//@style' ) as $style ) {
				if ( Sanitizer::checkCss( $style->value ) !== $style->value ) {
					return StatusValue::newFatal( 'mathsearch-mathml-invalid', 'style' );
				}
			}
			return StatusValue::newGood( $doc->saveXML( $doc->documentElement ) );
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $useErrors );
		}
	}

	/** LaTeXML omits the namespace, and some values use HTML entities. */
	private function normalize( string $mathml ): string {
		$mathml = trim( $mathml );
		if ( !preg_match( '/^<math\b[^>]*\sxmlns\s*=/', $mathml ) ) {
			$mathml = preg_replace( '/^<math\b/', '<math xmlns="' . self::MATHML_NS . '"', $mathml );
		}
		return preg_replace_callback( '/&([A-Za-z][A-Za-z0-9]*);/', static function ( array $m ): string {
			if ( in_array( $m[1], self::XML_ENTITIES, true ) ) {
				return $m[0];
			}
			$char = html_entity_decode( $m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			return $char === $m[0] ? $m[0] : '&#' . mb_ord( $char, 'UTF-8' ) . ';';
		}, $mathml );
	}

	private function libxmlError(): StatusValue {
		$errors = libxml_get_errors();
		return StatusValue::newFatal( 'mathsearch-mathml-invalid',
			$errors ? trim( $errors[0]->message ) : 'XML' );
	}
}
