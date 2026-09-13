<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use MediaWiki\Extension\Math\InputCheck\InputCheckFactory;
use MediaWiki\Extension\Math\WikiTexVC\TexVC;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * POST /math/v0/check/{type}
 *
 * RESTBase's check endpoint, swh:1:cnt:7794702d70b6cd023b4864a134abbb8829abf1e8;lines=6-87
 * Returns the content address and records it, which is what later lets render
 * resolve a hash.
 */
class Check extends SimpleHandler {

	private InputCheckFactory $checkFactory;
	private FormulaStore $store;

	public function __construct( InputCheckFactory $checkFactory, FormulaStore $store ) {
		$this->checkFactory = $checkFactory;
		$this->store = $store;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'type' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => FormulaHash::TYPES,
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			'q' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	public function getSupportedRequestTypes(): array {
		return RequestInterface::FORM_DATA_CONTENT_TYPES;
	}

	public function run( string $type ) {
		$q = $this->getValidatedBody()['q'];

		// inline-tex differs from tex only in display, which does not change
		// what is valid, so both check as tex.
		$checker = $this->checkFactory->newLocalChecker( $q, $type === 'chem' ? 'chem' : 'tex' );
		if ( !$checker->isValid() ) {
			$error = $checker->getError();
			throw new HttpException(
				$error ? $error->inLanguage( 'en' )->text() : 'Invalid input',
				400
			);
		}

		// isValid() and getValidTex() are assigned together, so a valid input
		// always has a checked form.
		$checked = $checker->getValidTex();
		if ( $checked === null ) {
			throw new HttpException( 'The checker accepted the input but returned nothing', 500 );
		}
		$hash = $this->store->remember( $checked, $type );

		$response = $this->getResponseFactory()->createJson( [
			'success' => true,
			'checked' => $checked,
			'requiredPackages' => $type === 'chem' ? [ 'mhchem' ] : [],
			'identifiers' => self::identifiers( $q, $type ),
			'endsWithDot' => str_ends_with( rtrim( $checked ), '.' ),
		] );
		$response->setHeader( 'x-resource-location', $hash );
		// The address is derived from the input, so there is nothing to revalidate.
		$response->setHeader( 'Cache-Control', 'no-cache' );
		return $response;
	}

	/** @return string[] */
	private static function identifiers( string $q, string $type ): array {
		// Mathoid reports none for chemistry, so neither does this.
		if ( $type === 'chem' ) {
			return [];
		}
		$warnings = [];
		$result = ( new TexVC() )->check( $q, [], $warnings );
		$tree = $result['input'] ?? null;
		return is_object( $tree ) ? array_values( $tree->extractIdentifiers() ) : [];
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		// Recording the address is a write, but not one a reader has to be able to make.
		return false;
	}
}
