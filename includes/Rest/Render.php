<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use RuntimeException;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * GET /math/v0/render/{format}/{hash}
 *
 * RESTBase's render endpoint, swh:1:cnt:7794702d70b6cd023b4864a134abbb8829abf1e8;lines=133-180
 * One content-addressed formula, several representations under one hash.
 */
class Render extends SimpleHandler {

	private const array FORMATS = [
		'svg' => [
			'type' => 'image/svg+xml',
			'profile' => 'https://www.mediawiki.org/wiki/Specs/SVG/1.0.0',
		],
		'mml' => [
			'type' => 'application/mathml+xml',
			'profile' => 'https://www.mediawiki.org/wiki/Specs/MathML/1.0.0',
		],
	];

	private FormulaStore $store;
	private RendererFactory $rendererFactory;
	private SvgRenderer $svgRenderer;
	private Config $config;

	public function __construct(
		FormulaStore $store, RendererFactory $rendererFactory, SvgRenderer $svgRenderer, Config $config
	) {
		$this->store = $store;
		$this->rendererFactory = $rendererFactory;
		$this->svgRenderer = $svgRenderer;
		$this->config = $config;
	}

	/** @return string[] */
	private function getFormats(): array {
		$formats = array_keys( self::FORMATS );
		if ( !$this->config->get( 'MathSearchRestSvg' ) ) {
			$formats = array_diff( $formats, [ 'svg' ] );
		}
		return array_values( $formats );
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'format' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => $this->getFormats(),
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
		$body = $this->renderOnServer( $formula['q'] );
		if ( !is_string( $body ) || $body === '' ) {
			throw new HttpException( "Nothing was produced for format $format", 500 );
		}
		if ( $format === 'svg' ) {
			$body = $this->renderSvg( $formula, $body );
		}

		return $this->respond( $body, $spec, $formula );
	}

	private function renderOnServer( string $tex ): ?string {
		$renderer = $this->rendererFactory->getRenderer( $tex, [], MathConfig::MODE_NATIVE_MML );
		// As MathReferenceData does: without this the MathML gets class=mathjax_ignore (T434686).
		$renderer->setMode( MathConfig::MODE_NATIVE_JAX );
		if ( !$renderer->render() ) {
			throw new HttpException(
				'Server-side rendering failed: ' . $renderer->getLastError(), 500 );
		}
		return $renderer->getMathml();
	}

	/** @param array{q:string,type:string} $formula */
	private function renderSvg( array $formula, string $mathml ): string {
		$svg = $this->store->getSvg( $formula, $mathml );
		if ( $svg !== null ) {
			return $svg;
		}
		try {
			$svg = $this->svgRenderer->render( $mathml );
		} catch ( RuntimeException $e ) {
			throw new HttpException( 'SVG rendering failed: ' . $e->getMessage(), 500 );
		}
		if ( $svg === null ) {
			throw new HttpException( 'MathJax produced no SVG', 500 );
		}
		$this->store->saveSvg( $formula, $mathml, $svg );
		return $svg;
	}

	/**
	 * @param string $body
	 * @param array $spec
	 * @param array{q:string,type:string} $formula
	 * @return Response
	 */
	private function respond( string $body, array $spec, array $formula ): Response {
		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( $body ) );

		$response->setHeader( 'Content-Type',
			$spec['type'] . '; charset=utf-8; profile="' . $spec['profile'] . '"' );
		$response->setHeader( 'x-resource-location',
			FormulaHash::fromTex( $formula['q'], $formula['type'] ) );
		$response->setHeader( 'Cache-Control', 's-maxage=864000, max-age=86400' );
		// Raw SVG from an API path is a script vector without these.
		$response->setHeader( 'X-Content-Type-Options', 'nosniff' );
		$response->setHeader( 'Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'" );
		$response->setHeader( 'ETag',
			'"' . sha1( $body ) . '"' );
		return $response;
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}
}
