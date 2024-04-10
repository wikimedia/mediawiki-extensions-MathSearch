<?php

namespace MediaWiki\Extension\MathSearch\Wikidata\MathML;

use ValueFormatters\FormatterOptions;
use Wikibase\Repo\Rdf\DedupeBag;
use Wikibase\Repo\Rdf\EntityMentionListener;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Purtle\RdfWriter;

class MathMLWikidataHook {

	/**
	 * Add Datatype "MathML" to the Wikibase Repository
	 * @param array &$dataTypeDefinitions
	 */
	public static function onWikibaseRepoDataTypes( array &$dataTypeDefinitions ) {
		global $wgContentMathEnableWikibaseDataType;
		if ( !$wgContentMathEnableWikibaseDataType ) {
			return;
		}

		$dataTypeDefinitions['PT:mathml'] = [
			'value-type' => 'string',
			'validator-factory-callback' => static function () {
				global $wgMathSearchContentTexMaxLength;
				// load validator builders
				$factory = WikibaseRepo::getDefaultValidatorBuilders();

				// initialize an array with string validators
				// returns an array of validators
				// that add basic string validation such as preventing empty strings
				$validators = $factory->buildStringValidators( $wgMathSearchContentTexMaxLength );
				$validators[] = new MathMLValidator();

				return $validators;
			},
			'formatter-factory-callback' => static function ( $format, FormatterOptions $options ) {
				return new MathMLFormatter( $format );
			},
			'rdf-builder-factory-callback' => static function (
				$mode, RdfVocabulary $vocab, RdfWriter $writer, EntityMentionListener $tracker,
				DedupeBag $dedupe
			) {
				return new MathMLRdfBuilder();
			},
		];
	}

	/**
	 * Add Datatype "MathML" to the Wikibase Client
	 *
	 * @param array[] &$dataTypeDefinitions
	 */
	public static function onWikibaseClientDataTypes( array &$dataTypeDefinitions ) {
		global $wgContentMathEnableWikibaseDataType;

		if ( !$wgContentMathEnableWikibaseDataType ) {
			return;
		}

		$dataTypeDefinitions['PT:mathml'] = [
			'value-type' => 'string',
			'formatter-factory-callback' => static function ( $format, FormatterOptions $options ) {
				return new MathMLFormatter( $format );
			},
		];
	}

}
