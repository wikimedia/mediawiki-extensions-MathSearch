<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * GET /math/v0/render/{format}/{hash}
 *
 * RESTBase's render endpoint, swh:1:cnt:7794702d70b6cd023b4864a134abbb8829abf1e8;lines=133-180
 * One content-addressed formula, several representations under one hash.
 */
class Render extends SimpleHandler {

	private const FORMATS = [
		'svg' => [ 'type' => 'image/svg+xml' ],
		'mml' => [ 'type' => 'application/mathml+xml' ],
	];

	private const SVG_PROFILE = 'https://www.mediawiki.org/wiki/Specs/SVG/1.0.0';
	private const MML_PROFILE = 'https://www.mediawiki.org/wiki/Specs/MathML/1.0.0';

	private FormulaStore $store;
	private RendererFactory $rendererFactory;

	public function __construct( FormulaStore $store, RendererFactory $rendererFactory ) {
		$this->store = $store;
		$this->rendererFactory = $rendererFactory;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'format' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => array_keys( self::FORMATS ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			'hash' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	public function run( string $format, string $hash ) {
		$formula = $this->store->resolve( $hash );
		if ( $formula === null ) {
			throw new HttpException(
				"No formula is known for $hash on this wiki. Check it first.",
				404
			);
		}

		$spec = self::FORMATS[$format];
		$body = $this->renderOnServer( $formula['q'], $format );
		if ( !is_string( $body ) || $body === '' ) {
			throw new HttpException( "Nothing was produced for format $format", 500 );
		}

		return $this->respond( $body, $format, $spec, $formula );
	}

	/** The reference rendering, from the same service the wiki itself uses. */
	private function renderOnServer( string $tex, string $format ): ?string {
		$renderer = $this->rendererFactory->getRenderer( $tex, [], MathConfig::MODE_MATHML );
		if ( !$renderer->render() ) {
			throw new HttpException(
				'Server-side rendering failed: ' . $renderer->getLastError(), 500 );
		}
		return $format === 'svg' ? $renderer->getSvg() : $renderer->getMathml();
	}

	/**
	 * @param string $body
	 * @param string $format
	 * @param array $spec
	 * @param array{q:string,type:string} $formula
	 * @return Response
	 */
	private function respond( string $body, string $format, array $spec, array $formula ): Response {
		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( $body ) );

		$profile = $format === 'svg' ? self::SVG_PROFILE : self::MML_PROFILE;
		$response->setHeader( 'Content-Type',
			$spec['type'] . '; charset=utf-8; profile="' . $profile . '"' );
		$response->setHeader( 'x-resource-location',
			FormulaHash::fromTex( $formula['q'], $formula['type'] ) );
		$response->setHeader( 'Cache-Control', 's-maxage=864000, max-age=86400' );
		// Raw SVG from an API path is a script vector without this.
		$response->setHeader( 'X-Content-Type-Options', 'nosniff' );
		$response->setHeader( 'ETag',
			'"' . sha1( $body ) . '"' );
		return $response;
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}
}
