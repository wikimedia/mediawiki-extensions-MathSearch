<?php

namespace MediaWiki\Extension\MathSearch;

use MediaWiki\Extension\Math\Hooks\HookRunner;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use Psr\Log\LoggerInterface;
use StatusValue;
use Throwable;
use Wikimedia\Mime\XmlTypeCheck;

/**
 * Converts TeX to MathML with a LaTeXML daemon.
 *
 * @copyright 2012 Moritz Schubotz
 * @license GPL-2.0-or-later
 */
class MathLaTeXML extends MathRenderer {

	public const MODE = 'latexml';

	// Defaults for when Math no longer registers these settings.
	private const DEFAULT_URL = 'https://latexml.portal.mardi4nfdi.de/convert';
	private const DEFAULT_TIMEOUT = 240;
	public const DEFAULT_SETTINGS = [
		'format' => 'xhtml',
		'whatsin' => 'math',
		'whatsout' => 'math',
		'pmml',
		'cmml',
		'mathtex',
		'nodefaultresources',
		'preload' => [
			'LaTeX.pool',
			'article.cls',
			'amsmath.sty',
			'amsthm.sty',
			'amstext.sty',
			'amssymb.sty',
			'eucal.sty',
			'[dvipsnames]xcolor.sty',
			'url.sty',
			'hyperref.sty',
			'[ids]latexml.sty',
			'texvc',
		],
		'linelength' => 90,
	];
	private const DEFAULT_ROOT_ELEMENTS = [ 'math', 'div', 'table', 'query' ];

	/** @var string[] */
	private array $allowedRootElements = [];
	private string $host;
	/** @var string|array */
	private $settings = '';
	private LoggerInterface $logger;

	/** @inheritDoc */
	public function __construct( string $tex = '', array $params = [], $cache = null ) {
		parent::__construct( $tex, $params, $cache );
		$this->mode = self::MODE;
		$this->host = self::getConfigValue( 'MathLaTeXMLUrl', self::DEFAULT_URL );
		if ( !isset( $params['display'] ) && $this->getMathStyle() == 'inlineDisplaystyle' ) {
			$this->tex = '{\\displaystyle ' . $tex . '}';
		}
		$this->logger = LoggerFactory::getInstance( 'MathSearch' );
	}

	/**
	 * Like Math's RendererFactory, which will no longer know the LaTeXML mode.
	 */
	public static function getRenderer(
		RendererFactory $factory, string $tex, array $params, string $mode
	): MathRenderer {
		return $mode === self::MODE ?
			new self( $tex, $params ) :
			$factory->getRenderer( $tex, $params, $mode );
	}

	/**
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	private static function getConfigValue( string $name, $default ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		return $config->has( $name ) ? $config->get( $name ) : $default;
	}

	public static function getDefaultSettings(): array {
		return self::getConfigValue( 'MathDefaultLaTeXMLSetting', self::DEFAULT_SETTINGS );
	}

	/**
	 * Converts an array with LaTeXML settings to a URL encoded string.
	 * A string is returned unchanged, so applying this twice is safe.
	 * @param string|array $array
	 * @return string
	 */
	public function serializeSettings( $array ) {
		if ( !is_array( $array ) ) {
			return $array;
		}

		// LaTeXML takes several values for one key, e.g. preload=a.sty&preload=b.sty
		$cgi_string = wfArrayToCgi( $array );
		$cgi_string = preg_replace( '|\%5B\d+\%5D|', '', $cgi_string );
		$cgi_string = preg_replace( '|&\d+=|', '&', $cgi_string );

		return $cgi_string;
	}

	/**
	 * @return string|array
	 */
	public function getLaTeXMLSettings() {
		return $this->settings ?: self::getDefaultSettings();
	}

	/**
	 * Settings for this instance only, see
	 * https://math.nist.gov/~BMiller/LaTeXML/manual/commands/latexmlpost.html
	 * An empty value means the default settings.
	 * @param string|array $settings
	 */
	public function setLaTeXMLSettings( $settings ) {
		$this->settings = $settings;
	}

	public function setHost( string $host ): void {
		$this->host = $host;
	}

	/**
	 * @return string[]
	 */
	public function getAllowedRootElements() {
		return $this->allowedRootElements ?: self::DEFAULT_ROOT_ELEMENTS;
	}

	/**
	 * An empty value means the default root elements.
	 * @param string[] $settings
	 */
	public function setAllowedRootElements( $settings ) {
		$this->allowedRootElements = $settings;
	}

	/**
	 * @return string HTTP POST data
	 */
	public function getPostData() {
		$tex = $this->getTex();
		if ( $this->getMathStyle() == 'inlineDisplaystyle' ) {
			// Displaystyle like texvc's $$ $tex $$, but not centered.
			$tex = '{\displaystyle ' . $tex . '}';
		}
		$texcmd = rawurlencode( $tex );
		$settings = $this->serializeSettings( $this->getLaTeXMLSettings() );
		$postData = $settings . '&tex=' . $texcmd;

		// Some versions of the LaTeXML daemon require the literal prefix, others reject it.
		if ( !str_contains( $this->host, '/convert' ) ) {
			$postData = preg_replace( '/&tex=/', '&tex=literal:', $postData, 1 );
		}

		$this->logger->debug( 'Get post data: ' . $postData );
		return $postData;
	}

	/**
	 * @return StatusValue result with the response body as value
	 */
	public function makeRequest() {
		$timeout = self::getConfigValue( 'MathLaTeXMLTimeout', self::DEFAULT_TIMEOUT );
		$post = $this->getPostData();
		$options = [ 'method' => 'POST', 'postData' => $post, 'timeout' => $timeout ];
		$req = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $this->host, $options, __METHOD__ );
		$status = $req->execute();
		if ( $status->isGood() ) {
			return StatusValue::newGood( $req->getContent() );
		}
		if ( $status->hasMessage( 'http-timed-out' ) ) {
			$this->logger->warning( 'LaTeXML request timeout', [
				'post' => $post,
				'host' => $this->host,
				'timeout' => $timeout
			] );
			return StatusValue::newFatal( 'math_timeout', $this->getModeName(), $this->host );
		}
		$errormsg = $req->getContent();
		$this->logger->warning( 'LaTeXML request failed', [
			'post' => $post,
			'host' => $this->host,
			'errormsg' => $errormsg
		] );
		return StatusValue::newFatal(
			'math_invalidresponse',
			$this->getModeName(),
			$this->host,
			$errormsg,
			$this->getModeName()
		);
	}

	/** @inheritDoc */
	public function render() {
		// The cache key ignores the LaTeXML settings, so cached entries are not reused.
		try {
			$status = $this->doRender();
		} catch ( Throwable $e ) {
			$this->logger->error( $e->getMessage(), [ 'exception' => $e ] );
			$status = StatusValue::newFatal( 'math_invalidresponse', $this->getModeName(),
				$this->host, $e->getMessage(), $this->getModeName() );
		}
		if ( !$status->isGood() ) {
			$error = $status->getMessages()[0];
			$this->lastError = $this->getError( $error->getKey(), ...$error->getParams() );
		}
		return $status->isGood();
	}

	protected function doRender(): StatusValue {
		if ( trim( $this->getUserInputTex() ) === '' ) {
			$this->logger->warning( 'Rendering was requested, but no TeX string is specified.' );
			return StatusValue::newFatal( 'math_empty_tex' );
		}
		$requestStatus = $this->makeRequest();
		if ( !$requestStatus->isGood() ) {
			return $requestStatus;
		}
		$jsonResult = json_decode( $requestStatus->getValue() );
		if ( !$jsonResult || json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->warning( 'LaTeXML invalid JSON', [
				'post' => $this->getPostData(),
				'host' => $this->host,
				'res' => $requestStatus->getValue()
			] );
			return StatusValue::newFatal( 'math_invalidjson', $this->getModeName(), $this->host );
		}
		if ( !$this->isValidMathML( $jsonResult->result ?? '' ) ) {
			// Bad MathML is probably verbose and might break the page.
			$this->logger->warning( 'LaTeXML invalid MathML', [
				'post' => $this->getPostData(),
				'host' => $this->host,
				'result' => $requestStatus->getValue()
			] );
			return StatusValue::newFatal( 'math_invalidxml', $this->getModeName(), $this->host );
		}
		$this->setMathml( $jsonResult->result );
		$renderer = $this;
		( new HookRunner( MediaWikiServices::getInstance()->getHookContainer() ) )
			->onMathRenderingResultRetrieved( $renderer, $jsonResult );
		return StatusValue::newGood();
	}

	/**
	 * Whether $XML is well-formed and has an allowed root element.
	 * @param string $XML
	 * @return bool
	 */
	public function isValidMathML( $XML ) {
		$xmlObject = new XmlTypeCheck( $XML, null, false );
		if ( !$xmlObject->wellFormed ) {
			$this->logger->error( 'XML validation error: ' . var_export( $XML, true ) );
			return false;
		}
		$name = $xmlObject->getRootElement();
		$elementSplit = explode( ':', $name );
		if ( in_array( end( $elementSplit ), $this->getAllowedRootElements(), true ) ) {
			return true;
		}
		$this->logger->error( "Got wrong root element: $name" );
		return false;
	}

	/**
	 * @param bool $svg ignored, LaTeXML renders no SVG
	 * @return string
	 */
	public function getHtmlOutput( bool $svg = true ): string {
		$className = 'mwe-math-element';
		if ( $this->getMathStyle() === 'display' ) {
			$mmlClass = 'mwe-math-mathml-display';
			$className .= ' mwe-math-element-block';
		} else {
			$mmlClass = 'mwe-math-mathml-inline';
			$className .= ' mwe-math-element-inline';
		}
		$attribs = [ 'class' => $className ];
		if ( $this->getID() !== '' ) {
			$attribs['id'] = $this->getID();
		}
		$hyperlink = null;
		if ( isset( $this->params['qid'] ) && preg_match( '/Q\d+/', $this->params['qid'] ) ) {
			$attribs['data-qid'] = $this->params['qid'];
			$hyperlink = SpecialPage::getTitleFor( 'MathWikibase' )
				->getLocalURL( [ 'qid' => $this->params['qid'] ] );
		}
		$mml = preg_replace(
			'/(<math[^>]*)(display|mode)=["\'](inline|block)["\']/', '$1', $this->getMathml()
		);
		if ( $this->getMathStyle() == 'display' ) {
			$mml = preg_replace( '/<math/', '<math display="block"', $mml );
		}
		// T434686: Client side MathJax must not affect this MathML
		$mml = preg_replace( '/<math/', '<math class="mathjax_ignore"', $mml );
		$output = Html::rawElement( 'span', [ 'class' => $mmlClass ], $mml );

		if ( $hyperlink && self::getConfigValue( 'MathEnableFormulaLinks', false ) ) {
			$output = Html::rawElement( 'a',
				[ 'href' => $hyperlink, 'style' => 'color:inherit;' ],
				$output
			);
		}

		return Html::rawElement( 'span', $attribs, $output );
	}
}
