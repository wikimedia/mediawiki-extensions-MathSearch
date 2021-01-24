<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

class SpecialLaTeXTranslator extends SpecialPage {

	const VERSION = '1.0.0';
	private $cache;
	private $dgUrl;
	private $compUrl;
	private $httpFactory;
	private $logger;
	private $context;
	private $tex;
	/**
	 * @var false|mixed|string
	 */
	private $dependencyGraph;

	function __construct() {
		parent::__construct( 'LaTeXTranslator' );
		$mw = MediaWikiServices::getInstance();
		$this->cache = $mw->getMainWANObjectCache();
		// provisional Hack to get the URL
		$provisionalUrl = $mw->getMainConfig()->get( 'MathSearchTranslationUrl' );
		$this->dgUrl =
			preg_replace( '/translation/', 'generateAnnotatedDependencyGraph', $provisionalUrl );
		$this->compUrl =
			preg_replace( '/translation/', 'generateTranslatedComputedMoi', $provisionalUrl );
		$this->httpFactory = $mw->getHttpRequestFactory();
		$this->logger = LoggerFactory::getInstance( 'MathSearch' );
	}

	/**
	 * Returns corresponding Mathematica translations of LaTeX functions
	 * @param string|null $par
	 */
	function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$output->addWikiMsg( 'math-tex2nb-intro' );
		$formDescriptor = [
			'input' => [
				'label-message' => 'math-tex2nb-input',
				'class' => 'HTMLTextField',
				'default' => '(z)_n = \frac{\Gamma(z+n)}{\Gamma(z)}',
			],
			'wikitext' => [
				'label-message' => 'math-tex2nb-wikitext',
				'class' => 'HTMLTextAreaField',
				'default' => 'The Gamma function 
<math>\Gamma(z)</math>
and the Pochhammer symbol
<math>(a)_n</math>
are often used together.',
			],
		];
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Translate' );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
		$htmlForm->setHeaderText( '<h2>' . wfMessage( 'math-tex2nb-header' )->toString() .
			'</h2>' );
		$htmlForm->show();
	}

	/**
	 * Processes the submitted Form input
	 * @param array $formData
	 * @return bool
	 */
	public function processInput( $formData ) {
		$this->tex = $formData['input'];
		$this->context = $formData['wikitext'];
		$this->dependencyGraph = $this->getDependencyGraphFromContext();
		$output = $this->getOutput();
		$output->addWikiMsg( 'math-tex2nb-latex' );
		$output->addWikiTextAsInterface( "<syntaxhighlight lang='latex'>$this->tex</syntaxhighlight>" );
		$output->addWikiMsg( 'math-tex2nb-mathematica' );
		try {
			$calulation = $this->getTranslations();
		} catch ( MWException $exception ) {
			$expected_error = 'The given context (dependency graph) did not contain sufficient information';
			if ( strpos( $exception->getText(), $expected_error ) !== false ) {
				FormulaInfo::DisplayTranslations( $this->tex );
				return true;
			}
		}
		$insights = json_decode( $calulation );
		$output->addWikiTextAsContent( "Content MathML:" );
		$renderer = new MathLaTeXML( $insights->semanticFormula );
		$renderer->render();
		$output->addHTML( $renderer->getHtmlOutput() );
		$output->addWikiTextAsContent( "Confidence: " . $insights->confidence );
		foreach ( $insights->translations as $key => $value ) {
			$num = json_encode( $value->numericResults, JSON_PRETTY_PRINT );
			$symb = json_encode( $value->symbolicResults, JSON_PRETTY_PRINT );
			$output->addWikiTextAsContent( "{$key}: <code>{$value->translation}</code>\n\n" .
				"Symbolic evaluation  <syntaxhighlight  lang='json'>{$symb}</syntaxhighlight>\n\n" .
				"Numeric evaluation <syntaxhighlight lang='json'>{$num}</syntaxhighlight>\n\n" );
		}
	}

	/**
	 * @return false|mixed
	 */
	private function getTranslations(): string {
		$hash =	$this->cache->makeGlobalKey( self::class,
				sha1( self::VERSION . '-F-' . $this->dependencyGraph . $this->tex ) );

		return $this->cache->getWithSetCallback( $hash, WANObjectCache::TTL_INDEFINITE,
			[ $this, 'calculateTranslations' ] );
	}

	/**
	 * @return string
	 * @throws MWException
	 */
	public function calculateTranslations(): string {
		$this->logger->info( "Cache miss. Calculate translation." );
		$q = rawurlencode( $this->tex );
		$url = "{$this->compUrl}?latex=$q";
		$options = [
			'method' => 'POST',
			'postData' => $this->dependencyGraph
		];
		$req = $this->httpFactory->create( $url, $options, __METHOD__ );
		$req->setHeader( 'Content-Type', 'application/json' );
		$req->execute();
		$statusCode = $req->getStatus();
		if ( $statusCode === 200 ) {
			return $req->getContent();
		}
		$e = new MWException( 'Calculation endpoint failed. Error:' . $req->getContent() );
		$this->logger->error( 'Calculation "{url}" returned ' .
			'HTTP status code "{statusCode}" for post data "{postData}".: {exception}.', [
			'url' => $url,
			'statusCode' => $statusCode,
			'postData' => $this->dependencyGraph,
			'exception' => $e
		] );
		throw $e;
	}

	private function getDependencyGraphFromContext(): string {
		$hash =	$this->cache->makeGlobalKey( self::class,
			sha1( self::VERSION . '-DG-' . $this->context ) );

		return $this->cache->getWithSetCallback( $hash, WANObjectCache::TTL_INDEFINITE,
			[ $this, 'calculateDependencyGraphFromContext' ] );
	}

	/**
	 * @return false|mixed
	 * @throws MWException
	 */
	public function calculateDependencyGraphFromContext(): string {
		$this->logger->info( "Cache miss. Calculate dependency graph." );
		$url = $this->dgUrl;
		$q = rawurlencode( $this->context );
		$postData = "content=$q";
		$options = [
			'method' => 'POST',
			'postData' => $postData,
		];
		$req = $this->httpFactory->create( $url, $options, __METHOD__ );
		$req->execute();
		$statusCode = $req->getStatus();
		if ( $statusCode === 200 ) {
			return $req->getContent();
		}
		$e = new MWException( 'Dependency graph endpoint failed.' );
		$this->logger->error( 'Dependency graph "{url}" returned ' .
			'HTTP status code "{statusCode}" for post data "{postData}": {exception}.', [
				'url' => $url,
				'statusCode' => $statusCode,
				'postData' => $postData,
				'exception' => $e,
			] );
		throw $e;
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}
