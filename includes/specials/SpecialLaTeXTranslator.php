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
and the pochhammer symbol
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
		$tex = $formData['input'];
		$context = $formData['wikitext'];
		$dependencyGraph = $this->getDependencyGraphFromContext( $context );
		$output = $this->getOutput();
		$output->addWikiMsg( 'math-tex2nb-latex' );
		$output->addWikiTextAsInterface( "<syntaxhighlight lang='latex'>$tex</syntaxhighlight>" );
		$output->addWikiMsg( 'math-tex2nb-mathematica' );
		FormulaInfo::DisplayTranslations( $tex );
	}

	/**
	 * @param $context
	 * @return false|mixed
	 */
	private function getDependencyGraphFromContext( $context ): string {
		$hash =
			$this->cache->makeGlobalKey( self::class, sha1( self::VERSION . '-DG-' . $context ) );

		return $this->cache->getWithSetCallback( $hash, WANObjectCache::TTL_INDEFINITE,
			[ $this, 'calculateDependencyGraphFromContext' ] );
	}

	/**
	 * @param $context
	 * @return false|mixed
	 * @throws MWException
	 */
	public function calculateDependencyGraphFromContext( $context ): string {
		$this->logger->error( "Cache miss. Calculate dependency graph." );
		$url = $this->dgUrl;
		$q = rawurlencode( $context );
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
