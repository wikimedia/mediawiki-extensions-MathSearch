<?php

use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2015 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @file
 * @ingroup extensions
 */
class SpecialMlpEval extends SpecialPage {

	public const STEP_PAGE = 1;
	public const STEP_FORMULA = 2;
	public const STEP_TEX = 3;
	public const STEP_RENDERING = 4;
	public const STEP_IDENTIFIERS = 5;
	public const STEP_DEFINITIONS = 6;
	public const STEP_FINISHED = 7;
	private const MAX_ATTEMPTS = 10;

	private $subStepNames = [
		''   => '',
		'4'  => '',
		'4a' => 'Mathoid-',
		'4b' => 'Native-',
		'4c' => 'LaTeXML-'
	];
	/** @var MathObject */
	private $selectedMathTag;
	/** @var int */
	private $step;
	/** @var MathIdGenerator */
	private $mathIdGen;
	/** @var int */
	private $oldId;
	/** @var bool|string */
	private $lastError = false;
	/** @var string */
	private $fId;
	/** @var RevisionRecord */
	private $revisionRecord;
	private $texInputChanged = false;
	private $identifiers = [];
	private $relations;
	private $speechRuleText;
	/** @var string */
	private $subStep = '';
	/** @var string[] */
	private $renderingFields = [ 'absolute', 'best', 'size', 'spacing', 'integration', 'font' ];

	/**
	 * @return bool
	 */
	public function isTexInputChanged() {
		return $this->texInputChanged;
	}

	/**
	 * @return array
	 */
	public function getIdentifiers() {
		return $this->identifiers;
	}

	function __construct() {
		parent::__construct( 'MlpEval' );
	}

	private function setStep( $step ) {
		$this->step = $step;
		return $step;
	}

	private function loadData() {
		$req = $this->getRequest();
		$revId = $req->getInt( 'oldId' );
		if ( $req->getText( 'wp1-page' ) ) {
			$t = Title::newFromText( $req->getText( 'wp1-page' ) );
			if ( $this->setPage( $t ) ) {
				if ( $revId && $revId != $this->getOldId() ) {
					$this->writeLog( "$revId was not selected", -1, $revId );
				}
			}
			$revId = $this->getOldId();
		}
		if ( $revId === 0 ) {
			return $this->setStep( 1 );
		}
		if ( $this->setRevision( $revId ) === false ) {
			return $this->setStep( 1 );
		}
		if ( $req->getText( 'pgRst' ) ) {
			return $this->resetPage();
		} elseif ( $req->getInt( 'oldStep' ) === 1 ) {
			$this->writeLog( "pgSelect: User selects page" . $revId, 1 );
		}
		$fId = $req->getText( 'fId' );
		$oldStep = $req->getInt( 'oldStep' );
		$restButton = $req->getBool( 'fRst' );
		if ( $oldStep >= self::STEP_FINISHED || $restButton ) {
			$this->resetFormula();
			$fId = '';
		}
		if ( $fId === '' ) {
			$this->setFId( $this->getRandomFId() );
			return $this->setStep( 2 );
		}
		if ( $this->setFId( $fId ) === false ) {
			return $this->setStep( 2 );
		}
		if ( $req->getInt( 'oldStep' ) == 3 || $req->getInt( 'oldStep' ) == 4 ) {
			$this->setStep( 4 );
			$this->subStep = $req->getText( 'oldSubStep' );
			foreach ( $this->renderingFields as $key ) {
				$val = $req->getVal( "wp4-$key" );
				$substep = $this->subStep;
				if ( $val ) {
					$req->setVal( "4-$key-$substep", $val );
					$req->unsetVal( "wp4-$key" );
				}
			}
			$nextStep = $this->getNextStep();
			if ( $nextStep !== 5 ) {
				$this->subStep = $nextStep;
				$this->writeLog( "User updates step 4.", 4 );
				return 4;
			} else {
				$this->writeLog( "User completes step 4.", 4 );
				return $this->setStep( 5 );
			}
		}
		if ( $req->getArray( 'wp5-identifiers' ) ) {
			$this->identifiers = $req->getArray( 'wp5-identifiers' );
			$missing = $req->getText( 'wp5-missing' );
			if ( $missing ) {
				// TODO: Check for invalid TeX
				$this->identifiers =
					array_merge( $this->identifiers, preg_split( '/[\n\r]/', $missing ) );
			}
		}
		$this->writeLog( "User completes step " . $req->getInt( 'oldStep' ), $req->getInt( 'oldStep' ) );
		return $this->setStep( $req->getInt( 'oldStep' ) + 1 );
	}

	/**
	 * The main function
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$this->loadData();
		$this->setHeaders();
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			$this->printIntorduction();
		}
		$form = new MlpEvalForm( $this );
		$form->show();
		if ( $this->step == 5 || $this->step == 6 ) {
			$this->getOutput()->addWikiMsg( 'math-lp-5-footer' );
		}
	}

	public function getStep() {
		return $this->step;
	}

	public function getRandomPageText() {
		try {
			$uid = $this->getUser()->getId();
			$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getReplicaDatabase();
			$results = $dbr->selectFieldValues( 'math_review_list', 'revision_id',
					"revision_id not in (SELECT revision_id from math_mlp where user_id = $uid )",
					__METHOD__, [
						'LIMIT'    => 1,
						'ORDER BY' => 'priority desc'
				] );
			if ( $results ) {
				$this->setRevision( $results[0] );
				return $this->revisionRecord->getPageAsLinkTarget()->getText();
			}
		} catch ( Exception $e ) {
			// empty
		}
		$rp = MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'Randompage' );
		$rp->setContext( $this->getContext() );
		for ( $i = 0; $i < self::MAX_ATTEMPTS; $i++ ) {
			$title = $rp->getRandomTitle();
			if ( $title !== null && $this->setPage( $title ) ) {
				$this->lastError = "";
				return $title->getText();
			}
		}
		$this->log()->warning( "Could not find suitable page with math:" . $this->lastError );
		$this->lastError = "";
		return "";
	}

	private function setPage( Title $title ) {
		if ( $title === null ) {
			$this->lastError = "Title was null.";
			return false;
		}
		if ( $title->isRedirect() ) {
			$this->lastError = "Redirects are not supported";
			return false;
		}
		$revision = $title->getLatestRevID();
		if ( $revision ) {
			return $this->setRevision( $revision );
		} else {
			$this->lastError = "invalid revision";
			return false;
		}
	}

	/**
	 * @param int $revId
	 * @return bool
	 */
	private function setRevision( $revId = 0 ) {
		if ( $revId == 0 && $this->oldId > 0 ) {
			$revId = $this->oldId;
		} else {
			$this->oldId = $revId;
		}
		if ( $revId == 0 ) {
			$this->lastError = "no revision id given";
			return false;
		}
		$this->revisionRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionById( $revId );
		$this->mathIdGen = MathIdGenerator::newFromRevisionRecord(
			$this->revisionRecord
		);
		$tagCount = count( $this->mathIdGen->getMathTags() );
		if ( $tagCount == 0 ) {
			$this->lastError = "has no math tags";
			return false;
		}
		return true;
	}

	/**
	 * @return \Psr\Log\LoggerInterface
	 */
	private function log() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	private function enableMathStyles() {
		$out = $this->getOutput();
		$styles = [ 'ext.math.desktop.styles', 'ext.math.scripts' ];
		if ( $this->subStep == '4b' ) {
			$styles[] = 'ext.math-svg.styles';
		} elseif ( $this->subStep ) {
			$styles[] = 'ext.math-mathml.styles';
		} else {
			$styles[] = 'ext.math.styles';
		}
		$out->addModuleStyles( $styles );
	}

	public function getSubStep() {
		return $this->subStep;
	}

	public function getNextStep() {
		if ( $this->step == 4 ) {
			switch ( $this->subStep ) {
				case '':
					return '4';
				case '4':
					return '4a';
				case '4a':
					return '4b';
				case '4b':
					return '4c';
				default:
					return 5;
			}
		} else {
			return $this->step + 1;
		}
	}

	public function getPreviousStep( $step = false, $substep = '' ) {
		if ( $step === false ) {
			$step = $this->step;
			$substep = $this->subStep;
		}
		if ( $step == 5 ) {
			return '4c';
		}
		if ( $step == 4 ) {
			switch ( $substep ) {
				case '4a':
					return '4';
				case '4b':
					return '4a';
				case '4c':
					return '4b';
				// case '4':
				default:
					return '3';
			}
		} else {
			return $step - 1;
		}
	}

	/**
	 * @return string[]
	 */
	public function getRenderingFields() {
		return $this->renderingFields;
	}

	protected function getGroupName() {
		return 'mathsearch';
	}

	/**
	 * @param string $fId
	 *
	 * @return true
	 */
	private function setFId( $fId = '' ) {
		if ( $fId == '' && $this->fId != '' ) {
			$fId = $this->fId;
		} else {
			$this->fId = $fId;
		}
		$this->selectedMathTag = MathObject::newFromRevisionText( $this->oldId, $fId );
		return true;
	}

	/**
	 * @return string
	 */
	private function getRandomFId() {
		try {
			$uid = $this->getUser()->getId();
			$rid = $this->revisionRecord->getId();
			$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getReplicaDatabase();
			// Note that the math anchor is globally unique
			$results = $dbr->selectFieldValues( 'math_review_list', 'anchor',
				"anchor not in (SELECT anchor from math_mlp where user_id = $uid " .
				"and anchor is not NULL) and revision_id = $rid",
				__METHOD__, [
					'LIMIT'    => 1,
					'ORDER BY' => 'priority desc'
				] );
			if ( $results ) {
				return $results[0];
			}
		} catch ( Exception $e ) {
			// empty
		}
		$unique = array_rand( $this->mathIdGen->getMathTags() );
		return $this->mathIdGen->parserKey2fId( $unique );
	}

	/**
	 * @return string
	 */
	public function getFId() {
		return $this->fId;
	}

	/**
	 * @return int
	 */
	public function getOldId() {
		return $this->oldId;
	}

	/**
	 * @return Title
	 */
	public function getRevisionTitle() {
		return Title::newFromLinkTarget( $this->revisionRecord->getPageAsLinkTarget() );
	}

	private function printIntorduction() {
		$this->enableMathStyles();
		$out = $this->getOutput();
		// $out->addWikiTextAsInterface( "Welcome to the MLP evaluation. Your data will be recorded." );
		// $out->addWikiTextAsInterface( "You are in step {$this->step} of possible evaluation 5 steps" );
		$this->printPrefix();
		switch ( $this->step ) {
			case self::STEP_PAGE:
				break;
			case self::STEP_FORMULA:
				$this->printMathObjectInContext();

				break;
			case self::STEP_TEX:
				$mo = $this->selectedMathTag;
				$this->printSource( $mo->getUserInputTex(),
					$this->msg( 'math-lp-3-pretty-option-1' )->text(), 'latex' );
				$texInfo = $mo->getTexInfo();
				if ( $texInfo ) {
					if ( $texInfo->getChecked() !== $mo->getUserInputTex() ) {
						$this->printSource( $texInfo->getChecked(),
							$this->msg( 'math-lp-3-pretty-option-2' )->text(), 'latex' );
						$this->texInputChanged = true;
					}
				}
				break;
			case self::STEP_RENDERING:
				$services = MediaWikiServices::getInstance();
				switch ( $this->subStep ) {
					case '4a':
						$services->getUserOptionsManager()->setOption( $this->getUser(), 'math', 'mathml' );
						$this->printMathObjectInContext( false, false,
							$this->getMathMLRenderingAsHtmlFragment( 'mathml' ) );
						break;
					case '4b':
						$services->getUserOptionsManager()->setOption( $this->getUser(), 'math', 'native' );
						$this->printMathObjectInContext( false, false,
							$this->getMathMLRenderingAsHtmlFragment( 'native' ) );
						break;
					case '4c':
						$services->getUserOptionsManager()->setOption( $this->getUser(), 'math', 'latexml' );
						$this->printMathObjectInContext( false, false,
							$this->getMathMLRenderingAsHtmlFragment( 'latexml' ),
							[ __CLASS__, 'removeSVGs' ] );
				}
				break;
			case self::STEP_IDENTIFIERS:
				$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
				$md = $mo->getTexInfo();
				if ( $md ) {
					$this->identifiers = array_unique( $md->getIdentifiers() );
					if ( $this->getRequest()->getArray( 'wp5-identifiers' ) == null ) {
						$this->getRequest()->setVal( 'wp5-identifiers', $this->identifiers );
					}
				}
				break;
			case self::STEP_DEFINITIONS:
				$this->printMathObjectInContext();
				$this->enableMathStyles();
				$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
				$this->relations = [];
				$rels = $mo->getRelations();
				$srt = $mo->getMathMlAltText();
				if ( !$srt ) {
					// retry to get alltext might be a caching problem
					$r = new MathoidDriver( $mo->getUserInputTex() );
					$srt = $r->getSpeech();
				}
				$this->speechRuleText = $srt;
				$wd = new WikidataDriver();
				foreach ( $this->identifiers as $i ) {
					$this->relations[$i] = [];
					if ( isset( $rels[$i] ) ) {
						foreach ( $rels[$i] as $rel ) {
							if ( preg_match( '/\[\[(.*)\]\]/', $rel->definition, $m ) ) {
								if ( $wd->search( $m[1] ) ) {
									$res = $wd->getResults();
									$this->relations[$i][] = $res[1];
								}
							} else {
								$this->relations[$i][] = $rel->definition;
							}
						}
					}
				}
				break;
		}
	}

	private function writeLog( $message, $step = false, $revId = false ) {
		$userId = $this->getUser()->getId();
		$logData = [
			'data'   => $this->getRequest()->getValues(),
			'header' => $this->getRequest()->getAllHeaders()
		];
		$json = json_encode( $logData );
		if ( $step == false ) {
			$step = $this->step;
		}
		if ( !$revId ) {
			$revId = $this->oldId;
		}
		$row = [
			'user_id'     => $userId,
			'step'        => $step,
			'json_data'   => $json,
			'revision_id' => $revId,
			'anchor'      => $this->fId ?? 'undefined', // NULL is no longer allowed
			'comment'     => $message
		];
		if ( $userId == 0 ) {
			$this->printSource( var_export( $message, true ), 'Error: No user found to store results.' );
			return;
		}
		$dbw = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getPrimaryDatabase();

		$dbw->upsert( 'math_mlp', $row, [ [ 'user_id', 'revision_id', 'anchor', 'step' ] ], $row );
		if ( $this->fId ) {
			$dbw->startAtomic( __METHOD__ );
			$cnt = $dbw->selectField( 'math_mlp', 'count( distinct user_id)', [
				'revision_id' => $revId,
				'anchor' => $this->fId
			] );
			if ( $cnt == 1 ) {
				$row = [
						'revision_id' => $revId,
						'anchor'      => $this->fId,
						'priority'    => 2
				];
				$dbw->upsert( 'math_review_list', $row, [ [ 'revision_id', 'anchor' ] ], $row );
			} elseif ( $cnt > 1 ) {
				$dbw->delete( 'math_review_list', [ 'revision_id', 'anchor' ] );
			}
			$dbw->endAtomic( __METHOD__ );
		}
	}

	private function resetPage() {
		$req = $this->getRequest();
		$rndRev = $req->getInt( 'oldId' );
		$oldStep = $req->getInt( 'oldStep' );
		if ( $rndRev && $oldStep == 1 ) {
			$this->writeLog( "$rndRev was not selected, by button", -1, $rndRev );
		}
		$req->unsetVal( 'wp1-page' );
		$req->unsetVal( 'oldId' );
		$req->unsetVal( 'fId' );
		$req->unsetVal( 'oldStep' );
		$this->fId = '';
		return $this->setStep( 1 );
	}

	private function resetFormula() {
		$req = $this->getRequest();
		$this->writeLog( "pgRst: User selects another formula", $req->getInt( 'oldStep' ) );
		$valueNames = $req->getValueNames( [ 'oldId', 'wpEditToken' ] );
		foreach ( $valueNames as $name ) {
			$req->unsetVal( $name );
		}
		$this->fId = '';
	}

	private function printSource( $source, $description = "", $language = "text", $linestart = true ) {
		if ( $description ) {
			$description .= ": ";
		}
		$this->getOutput()->addWikiTextAsInterface( "$description<syntaxhighlight lang=\"$language\">" .
			$source . '</syntaxhighlight>', $linestart );
	}

	/**
	 * @param string $mode
	 * @param float $factor
	 * @param string|false $tex
	 * @param array $options
	 * @return string
	 */
	public function getMathMLRenderingAsHtmlFragment(
		$mode, $factor = 2.5, $tex = false, $options = []
	) {
		$renderer = $this->getMathMlRenderer( $mode, $tex, $options );
		$largeMathML = $renderer->getMathml();
		$factor = round( $factor * 100 );
		return preg_replace(
			"/<math/",
			"<math style=\"font-size: {$factor}% !important;\"",
			$largeMathML
		);
	}

	private function printFormula() {
		$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
		/** @var MathRenderer $renderer */
		$renderer = MediaWikiServices::getInstance()
			->get( 'Math.RendererFactory' )
			->getRenderer( $mo->getUserInputTex(), [] );
		if ( $renderer->render() ) {
			$output = $renderer->getHtmlOutput();
		} else {
			$output = $renderer->getLastError();
		}
		$this->getOutput()->addHTML( $output );
	}

	private function printTitle() {
		$sectionTitle = $this->msg( "math-lp-{$this->step}-head" )
			->params( $this->subStepNames[$this->subStep], $this->subStep );
		$this->getOutput()->addHTML( "<h2>$sectionTitle</h2>" );
	}

	private function printIntro() {
		$msg =
			new Message( "math-lp-{$this->step}-intro", [ $this->subStepNames[$this->subStep] ] );
		$this->getOutput()->addWikiTextAsInterface( $msg->text() );
	}

	private function getWikiTextLink() {
		$description = "{$this->getRevisionTitle()}#{$this->fId}";
		return "[[Special:Permalink/{$this->oldId}#{$this->fId}|$description]]";
	}

	private function printFormulaRef() {
		$this->getOutput()->addWikiMsg( 'math-lp-formula-ref', $this->getWikiTextLink(),
			$this->selectedMathTag->getWikiText(), $this->revisionRecord->getTimestamp() );
	}

	private function printPrefix() {
		if ( $this->step > 1 ) {
			$this->printFormulaRef();
		}
		if ( $this->step === 1 ) {
			$this->printIntro();
			$this->printTitle();
		} else {
			$this->printTitle();
			$this->printIntro();
		}
	}

	/**
	 * @param bool $highlight
	 * @param bool $collapsed
	 * @param string|false $formula
	 * @param callback|false $filter
	 */
	public function printMathObjectInContext(
		$highlight = true, $collapsed = true, $formula = false, $filter = false
	) {
		if ( $collapsed ) {
			$collapsed = "mw-collapsed";
		}
		$out = $this->getOutput();
		$hl = new MathHighlighter( $this->fId, $this->oldId, $highlight );
		$out->addHTML(
			"<div class=\"toccolours mw-collapsible $collapsed\"  style=\"text-align: left\">"
		);
		if ( !$formula ) {
			$this->printFormula();
		} else {
			$out->addHTML( $formula );
		}
		$out->addHTML( '<div class="mw-collapsible-content">' );

		$popts = $out->parserOptions();
		$popts->setInterfaceMessage( false );

		if ( method_exists( ParserFactory::class, 'getInstance' ) ) {
			// MW 1.39+
			$parser = MediaWikiServices::getInstance()->getParserFactory()->getInstance();
		} else {
			$parser = MediaWikiServices::getInstance()->getParser()->getFreshParser();
		}
		$parserOutput = $parser->parse(
			$hl->getWikiText(), $this->getRevisionTitle(), $popts )->getText();
		if ( $filter ) {
			call_user_func_array( $filter, [ &$parserOutput ] );
		}
		$out->addHTML( $parserOutput );
		$out->addHTML( '</div></div>' );
	}

	/**
	 * @param mixed|null $key
	 * @return mixed
	 */
	public function getRelations( $key = null ) {
		if ( $key ) {
			return $this->relations[$key];
		}
		return $this->relations;
	}

	/**
	 * @return string
	 */
	public function getSpeechRuleText() {
		return $this->speechRuleText;
	}

	/**
	 * @param string $mode
	 * @param string|false $tex
	 * @param array $options
	 * @return MathRenderer
	 */
	private function getMathMlRenderer( $mode, $tex, $options ) {
		$this->updateTex( $tex, $options );
		$renderer = MathRenderer::getRenderer( $tex, $options, $mode );
		$renderer->checkTeX();
		$renderer->render();
		$renderer->writeCache();
		return $renderer;
	}

	/**
	 * @param string|false &$tex
	 * @param array &$options
	 */
	private function updateTex( &$tex, &$options ) {
		if ( !$tex ) {
			$tex = $this->selectedMathTag->getUserInputTex();
			$options = $this->selectedMathTag->getParams();
		}
	}

	public static function removeSVGs( &$html ) {
		// $html = str_replace( 'style="display: none;"><math', '><math', $html );
		// $html = preg_replace( '/<meta(.*?)mwe-math-fallback-image(.*?)>/', '', $html );
	}

}
