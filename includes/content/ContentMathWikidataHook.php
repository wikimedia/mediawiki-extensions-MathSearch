<?php

use ValueFormatters\FormatterOptions;
use ValueParsers\StringParser;
use Wikibase\Repo\Parsers\WikibaseStringValueNormalizer;
use Wikibase\Repo\Rdf\DedupeBag;
use Wikibase\Repo\Rdf\EntityMentionListener;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Purtle\RdfWriter;

class ContentMathWikidataHook {

	/**
	 * Add Datatype "ContentMath" to the Wikibase Repository
	 * @param array &$dataTypeDefinitions
	 */
	public static function onWikibaseRepoDataTypes( array &$dataTypeDefinitions ) {
		global $wgContentMathEnableWikibaseDataType;
		if ( !$wgContentMathEnableWikibaseDataType ) {
			return;
		}

		$dataTypeDefinitions['PT:contentmath'] = [
			'value-type'                 => 'string',
			'validator-factory-callback' => static function () {
				global $wgMathSearchContentTexMaxLength;
				// load validator builders
				$factory = WikibaseRepo::getDefaultValidatorBuilders();

				// initialize an array with string validators
				// returns an array of validators
				// that add basic string validation such as preventing empty strings
				$validators = $factory->buildStringValidators( $wgMathSearchContentTexMaxLength );
				$validators[] = new ContentMathValidator();
				return $validators;
			},
			'parser-factory-callback' => static function ( ParserOptions $options ) {
				$normalizer = new WikibaseStringValueNormalizer( WikibaseRepo::getStringNormalizer() );
				return new StringParser( $normalizer );
			},
			'formatter-factory-callback' => static function ( $format, FormatterOptions $options ) {
				global $wgOut;
				$styles = [ 'ext.math.desktop.styles', 'ext.math.scripts', 'ext.math.styles' ];
				$wgOut->addModuleStyles( $styles );
				return new ContentMathFormatter( $format );
			},
			'rdf-builder-factory-callback' => static function (
				$mode,
				RdfVocabulary $vocab,
				RdfWriter $writer,
				EntityMentionListener $tracker,
				DedupeBag $dedupe
			) {
				return new ContentMathMLRdfBuilder();
			},
		];
	}

	/**
	 * Add Datatype "ContentMath" to the Wikibase Client
	 *
	 * @param array[] &$dataTypeDefinitions
	 */
	public static function onWikibaseClientDataTypes( array &$dataTypeDefinitions ) {
		global $wgContentMathEnableWikibaseDataType;

		if ( !$wgContentMathEnableWikibaseDataType ) {
			return;
		}

		$dataTypeDefinitions['PT:contentmath'] = [
			'value-type'                 => 'string',
			'formatter-factory-callback' => static function ( $format, FormatterOptions $options ) {
				global $wgOut;
				$styles = [ 'ext.math.desktop.styles', 'ext.math.scripts', 'ext.math.styles' ];
				$wgOut->addModuleStyles( $styles );
				return new ContentMathFormatter( $format );
			},
		];
	}

}
