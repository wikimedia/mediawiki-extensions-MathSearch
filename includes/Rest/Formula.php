<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * GET /math/v0/formula/{hash}
 *
 * RESTBase's formula endpoint, swh:1:cnt:7794702d70b6cd023b4864a134abbb8829abf1e8;lines=88-132
 * The input a content address stands for.
 */
class Formula extends SimpleHandler {

	private FormulaStore $store;

	public function __construct( FormulaStore $store ) {
		$this->store = $store;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'hash' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	public function run( string $hash ) {
		$formula = $this->store->resolve( $hash );
		if ( $formula === null ) {
			throw new HttpException( "No formula is known for $hash on this wiki.", 404 );
		}

		$response = $this->getResponseFactory()->createJson( [
			'q' => $formula['q'],
			'type' => $formula['type'],
		] );
		// Derived from what is returned, not echoed, so the header always
		// describes the body.
		$response->setHeader( 'x-resource-location',
			FormulaHash::fromTex( $formula['q'], $formula['type'] ) );
		$response->setHeader( 'Cache-Control', 's-maxage=864000, max-age=86400' );
		return $response;
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}
}
