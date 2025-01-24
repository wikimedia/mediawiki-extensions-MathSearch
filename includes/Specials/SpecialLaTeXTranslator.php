<?php

use MediaWiki\HTMLForm\Field\HTMLCheckField;
use MediaWiki\HTMLForm\Field\HTMLTextAreaField;
use MediaWiki\HTMLForm\Field\HTMLTextField;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LegacyLogger;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\SpecialPage\SpecialPage;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class SpecialLaTeXTranslator extends SpecialPage {

	private const VERSION = '1.0.0';

	/** @var WANObjectCache */
	private $cache;
	/** @var string */
	private $dgUrl;
	/** @var string */
	private $compUrl;
	/** @var HttpRequestFactory */
	private $httpFactory;
	/** @var LoggerInterface */
	private $logger;
	/** @var string */
	private $context;
	/** @var string */
	private $tex;
	/** @var bool */
	private $purge;
	/** @var string|null */
	private $dependencyGraph;

	private function log( int $level, string $message, array $context = [] ) {
		$this->logger->log( $level, $message, $context );
		if ( $this->getConfig()->get( 'ShowDebug' ) ) {
			$msg = LegacyLogger::interpolate( $message, $context );
			$this->getOutput()->addWikiTextAsContent( "Log $level:" . $msg );
		}
	}

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
		$pid = $this->getRequest()->getVal( 'pid' ); // Page ID
		$eid = $this->getRequest()->getVal( 'eid' ); // Equation ID
		$this->setHeaders();
		$output = $this->getOutput();
		$output->addWikiMsg( 'math-tex2nb-intro' );
		if ( $pid && $eid ) {
			$revisionRecord =
				MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $pid );
			$contentModel =
				$revisionRecord->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getModel();
			if ( $contentModel !== CONTENT_MODEL_WIKITEXT ) {
				throw new RuntimeException( "Only CONTENT_MODEL_WIKITEXT supported for translation." );
			}

			$content = $revisionRecord->getContent( SlotRecord::MAIN );
			if ( !$content instanceof TextContent ) {
				throw new RuntimeException( "Translation supports only TextContent" );
			}
			$this->context = $content->getText();
			$mo = MathObject::newFromRevisionText( $pid, $eid );
			$this->tex = $mo->getTex();
			$this->displayResults();
		} else {
			$this->tex = '(z)_n = \frac{\Gamma(z+n)}{\Gamma(z)}';
			$this->context = 'The Gamma function
<math>\Gamma(z)</math>
and the Pochhammer symbol
<math>(a)_n</math>
are often used together.';
		}
		$formDescriptor = [
			'input' => [
				'label-message' => 'math-tex2nb-input',
				'class' => HTMLTextField::class,
				'default' => $this->tex,
			],
			'wikitext' => [
				'label-message' => 'math-tex2nb-wikitext',
				'class' => HTMLTextAreaField::class,
				'default' => $this->context,
			],
			'purge' => [
				'label-message' => 'math-tex2nb-purge',
				'class' => HTMLCheckField::class,
			],
		];
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Translate' );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
		$htmlForm->setHeaderHtml( '<h2>' . $this->msg( 'math-tex2nb-header' )->escaped() .
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
		$this->purge = $formData['purge'];

		return $this->displayResults();
	}

	private function getTranslations(): string {
		$hash =
			$this->cache->makeGlobalKey( self::class,
				sha1( self::VERSION . '-F-' . $this->dependencyGraph . $this->tex ) );
		if ( $this->purge ) {
			$value = $this->calculateTranslations();
			$this->cache->set( $hash, $value );
		}

		return $this->cache->getWithSetCallback( $hash, WANObjectCache::TTL_INDEFINITE,
			[ $this, 'calculateTranslations' ] );
	}

	public function calculateTranslations(): string {
		$this->log( LogLevel::INFO, "Cache miss. Calculate translation." );
		$q = rawurlencode( $this->tex );
		$url = "{$this->compUrl}?latex=$q";
		$options = [
			'method' => 'POST',
			'postData' => $this->dependencyGraph,
		];
		$req = $this->httpFactory->create( $url, $options, __METHOD__ );
		$req->setHeader( 'Content-Type', 'application/json' );
		$req->execute();
		$statusCode = $req->getStatus();
		if ( $statusCode === 200 ) {
			return $req->getContent();
		}
		$e = new RuntimeException( 'Calculation endpoint failed. Error:' . $req->getContent() );
		$this->logger->error( 'Calculation "{url}" returned ' .
			'HTTP status code "{statusCode}" for post data "{postData}".: {exception}.', [
			'url' => $url,
			'statusCode' => $statusCode,
			'postData' => $this->dependencyGraph,
			'exception' => $e,
		] );
		throw $e;
	}

	private function getDependencyGraphFromContext(): string {
		$hash =
			$this->cache->makeGlobalKey( self::class,
				sha1( self::VERSION . '-DG-' . $this->context ) );
		$this->log( LogLevel::DEBUG, "DG Hash is {hash}", [ 'hash' => $hash ] );
		if ( $this->purge ) {
			$this->log( LogLevel::INFO, 'Cache purging requested' );
			$value = $this->calculateDependencyGraphFromContext();
			$this->cache->set( $hash, $value );
		}

		return $this->cache->getWithSetCallback( $hash, 31556952,
			[ $this, 'calculateDependencyGraphFromContext' ] );
	}

	public function calculateDependencyGraphFromContext(): string {
		$this->log( LogLevel::INFO, "Cache miss. Calculate dependency graph." );
		$url = $this->dgUrl;
		$q = rawurlencode( $this->context );
		$postData = "content=$q";
		$options = [
			'method' => 'POST',
			'postData' => $postData,
			'timeout' => 240,
		];
		$req = $this->httpFactory->create( $url, $options, __METHOD__ );
		$req->execute();
		$statusCode = $req->getStatus();
		if ( $statusCode === 200 ) {
			return $req->getContent();
		}
		$e = new RuntimeException( 'Dependency graph endpoint failed.' );
		$this->log( LogLevel::ERROR, 'Dependency graph "{url}" returned ' .
			'HTTP status code "{statusCode}" for post data "{postData}": {exception}.', [
			'url' => $url,
			'statusCode' => $statusCode,
			'postData' => $postData,
			'exception' => $e,
		] );
		throw $e;
	}

	private function printSource(
		string $source,
		string $description = "",
		string $language = "text",
		bool $linestart = true,
		bool $collapsible = true
	) {
		$inline = ' inline ';
		$out = $this->getOutput();
		if ( $description ) {
			$description .= ": ";
		}
		if ( $collapsible ) {
			$this->printColHeader( $description );
			$description = '';
			$inline = '';
		}
		$out->addWikiTextAsInterface( "$description<syntaxhighlight lang=\"$language\" $inline>" .
			$source .
			'</syntaxhighlight>', $linestart );
		if ( $collapsible ) {
			$this->printColFooter();
		}
	}

	public function displayResults(): bool {
		$output = $this->getOutput();
		$output->addWikiMsg( 'math-tex2nb-latex' );
		$this->printSource( $this->tex, '', 'latex', false, false );
		$output->addWikiTextAsContent( "<math>$this->tex</math>", false );
		$output->addWikiMsg( 'math-tex2nb-mathematica' );
		try {
			$this->dependencyGraph = $this->getDependencyGraphFromContext();
			$calulation = $this->getTranslations();
		} catch ( Exception $exception ) {
			$expected_error =
				'The given context (dependency graph) did not contain sufficient information';
			if ( strpos( $exception->getText(), $expected_error ) !== false ) {
				FormulaInfo::DisplayTranslations( $this->tex );

				return false;
			} else {
				$output->addWikiTextAsContent( "Could not consider context: {$exception->getMessage()}" );
				FormulaInfo::DisplayTranslations( $this->tex );

				return false;
			}
		}
		$insights = json_decode( $calulation );
		$this->printSource( $insights->semanticFormula, "Semantic latex", 'latex', false, false );
		$output->addWikiTextAsContent( "Confidence: " . $insights->confidence, false );

		foreach ( $insights->translations as $key => $value ) {
			$output->addWikiTextAsContent( "=== $key ===" );
			$this->printSource( $value->translation, "Translation", '$key', false, false );
			$output->addWikiTextAsContent( "==== Information ====" );
			$info = $value->translationInformation;
			$this->printList( $info->subEquations, "Sub Equations" );
			$this->printList( $info->freeVariables, "Free variables" );
			$this->printList( $info->constraints, "Constraints" );
			$this->printList( $info->tokenTranslations, "Symbol info" );

			// $this->printList($value);
			$output->addWikiTextAsContent( "==== Tests ====" );
			$output->addWikiTextAsContent( "=====  Symbolic =====" );

			$this->displayTests( $value->symbolicResults->testCalculationsGroup );
			$output->addWikiTextAsContent( "=====  Numeric =====" );

			$this->displayTests( $value->numericResults->testCalculationsGroups );
		}

		$output->addWikiTextAsContent( "=== Dependency Graph Information ===" );
		$mathprint = static function ( $x ) {
			return "* <math>$x</math>";
		};
		$this->printList( $insights->includes, "Includes", $mathprint );
		$this->printList( $insights->isPartOf, "Is part of", $mathprint );
		$this->printList( $insights->definiens, 'Description',
			static function ( $x ) { return "* {$x->definition}";
			} );
		$this->printSource( $calulation, 'Complete translation information', 'json' );
		return false;
	}

	/**
	 * @param array $list
	 * @param string $description
	 * @param callable|false $callable
	 */
	private function printList( array $list, string $description, $callable = false ): void {
		if ( !$list || empty( $list ) ) {
			return;
		}
		$output = $this->getOutput();
		$this->printColHeader( $description );
		foreach ( $list as $key => $value ) {
			if ( $callable === false ) {
				$callable = static function ( $x, $y ) { return "* $x";
				};
			}
			$value = $callable( $value, $key );
			$output->addWikiTextAsContent( $value );
		}
			$this->printColFooter();
	}

	protected function getGroupName() {
		return 'mathsearch';
	}

	private function printColHeader( string $description ): void {
		$out = $this->getOutput();
		$out->addHTML( '<div class="toccolours mw-collapsible mw-collapsed"  style="text-align: left">' );
		$out->addWikiTextAsContent( $description );
		$out->addHTML( '<div class="mw-collapsible-content">' );
	}

	private function printColFooter(): void {
		$this->getOutput()->addHTML( '</div></div>' );
	}

	/**
	 * @param array $group
	 */
	private function displayTests( $group ) {
		if ( !is_array( $group ) ) {
			return;
		}
		foreach ( $group as $testGroup ) {
			if ( !$testGroup->testExpression ) {
				continue;
			}
			$this->printSource( $testGroup->testExpression, "Test expression", "text",
				true, false );
			foreach ( $testGroup->testCalculations as $calculation ) {
				$calcRes = json_encode( $calculation, JSON_PRETTY_PRINT );
				$description = $calculation->result;
				if ( isset( $calculation->testExpression ) ) {
					$description .= ": " . $calculation->testExpression;
				}
				if ( isset( $calculation->testValues ) ) {
					$description .= ":";
					foreach ( $calculation->testValues as $k => $v ) {
						$description .= " $k = $v, ";
					}
				}
				$this->printSource( $calcRes, $description, 'json' );
			}
		}
	}
}
