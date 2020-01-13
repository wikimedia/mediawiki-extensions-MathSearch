<?php

use ValueFormatters\FormatterOptions;
use ValueParsers\StringParser;
use Wikibase\Rdf\DedupeBag;
use Wikibase\Rdf\EntityMentionListener;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Repo\Parsers\WikibaseStringValueNormalizer;
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
			'validator-factory-callback' => function () {
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
			'parser-factory-callback' => function ( ParserOptions $options ) {
				$repo = WikibaseRepo::getDefaultInstance();
				$normalizer = new WikibaseStringValueNormalizer( $repo->getStringNormalizer() );
				return new StringParser( $normalizer );
			},
			'formatter-factory-callback' => function ( $format, FormatterOptions $options ) {
				global $wgOut;
				$styles = [ 'ext.math.desktop.styles', 'ext.math.scripts', 'ext.math.styles' ];
				$wgOut->addModuleStyles( $styles );
				return new ContentMathFormatter( $format );
			},
			'rdf-builder-factory-callback' => function (
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

	/*
	 * Add Datatype "ContentMath" to the Wikibase Client
	 */
	public static function onWikibaseClientDataTypes( array &$dataTypeDefinitions ) {
		global $wgContentMathEnableWikibaseDataType;

		if ( !$wgContentMathEnableWikibaseDataType ) {
			return;
		}

		$dataTypeDefinitions['PT:contentmath'] = [
			'value-type'                 => 'string',
			'formatter-factory-callback' => function ( $format, FormatterOptions $options ) {
				global $wgOut;
				$styles = [ 'ext.math.desktop.styles', 'ext.math.scripts', 'ext.math.styles' ];
				$wgOut->addModuleStyles( $styles );
				return new ContentMathFormatter( $format );
			},
		];
	}

}
