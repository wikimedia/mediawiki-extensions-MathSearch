<?php

use MediaWiki\Logger\LoggerFactory;

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
	const STEP_PAGE = 1;
	const STEP_FORMULA = 2;
	const STEP_TEX = 3;
	const STEP_RENDERING = 4;
	const STEP_IDENTIFIERS = 5;
	const STEP_DEFINITIONS = 6;
	const STEP_FINISHED = 7;
	const MAX_ATTEMPTS = 10;
	private $subStepNames = array(
		''   => '',
		'4'  => '',
		'4a' => 'PNG-',
		'4b' => 'SVG-',
		'4c' => 'MathML-'
	);
	/** @var  MathObject */
	private $selectedMathTag;
	/** @var int */
	private $step;
	/**@var MathIdGenerator */
	private $mathIdGen;
	/** @var int */
	private $oldId;
	/** @var bool|string */
	private $lastError = false;
	/** @var string */
	private $fId;
	/** @var  Revision */
	private $revision;
	private $texInputChanged = false;
	private $identifiers = array();
	private $relations;
	private $speechRuleText;
	private $subStep = '';
	private $renderingFields =array( 'absolute', 'best', 'size', 'spacing', 'integration', 'font' );

	/**
	 * @return boolean
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
					$this->writeLog( "$revId was not selected", - 1, $revId );
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
				$this->writeLog( "User updates step 4." );
				return 4;
			} else {
				$this->writeLog( "User completes step 4." );
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
		$this->writeLog( "User completes step ".$req->getInt( 'oldStep' ) );
		return $this->setStep( $req->getInt( 'oldStep' ) + 1 );
	}

	/**
	 * The main function
	 */
	public function execute( $par ) {
		$this->loadData();
		$this->setHeaders();
		$this->printIntorduction();
		$form = new MlpEvalForm( $this );
		$form->show();
		if ( $this->step == 5 or $this->step == 6 ) {
			$this->getOutput()->addWikiMsg( 'math-lp-5-footer' );
		}
	}

	public function getStep() {
		return $this->step;
	}


	public function getRandomPage() {
		$rp = new RandomPage();
		for ( $i = 0; $i < self::MAX_ATTEMPTS; $i ++ ) {
			$title = $rp->getRandomTitle();
			if ( $this->setPage( $title ) ) {
				$this->lastError = "";
				return $title;
			}
		}
		$this->log()->warning( "Could not find suitable page with math:" . $this->lastError );
		$this->lastError = "";
		return "";
	}

	private function setPage( Title $title ) {
		if ( is_null( $title ) ) {
			$this->lastError = "Title was null.";
			return false;
		}
		if ( $title->isRedirect() ) {
			$this->lastError = "Redirects are not supported";
			return false;
		}
		$revision = $title->getLatestRevID();
		if ( $revision ) {
			$retVal = $this->setRevision( $revision );
			return $retVal;
		} else {
			$this->lastError = "invalid revision";
			return false;
		}
	}

	/**
	 * @param $revId
	 * @return bool
	 * @throws MWException
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
		$this->revision = Revision::newFromId( $revId );
		$this->mathIdGen = MathIdGenerator::newFromRevision( $this->revision );
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
		$styles = array( 'ext.math.desktop.styles', 'ext.math.scripts' );
		if ( $this->subStep == '4b' ){
			$styles[] ='ext.math-svg.styles';
		} elseif( $this->subStep ){
			$styles[] ='ext.math-mathml.styles';
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
		if ( $step === false ){
			$step = $this->step;
			$substep = $this->subStep;
		}
		if ( $step == 5 ) {
			return '4c';
		}
		if ( $step == 4 ) {
			switch ( $substep ) {
				case '4':
					return '3';
				case '4a':
					return '4';
				case '4b':
					return '4a';
				case '4c':
					return '4b';
				default:
					return '3';
			}
		} else {
			return $step - 1;
		}
	}

	public function getRenderingFields() {
		return $this->renderingFields;
	}

	protected function getGroupName() {
		return 'mathsearch';
	}


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
	 * @return array
	 * @throws MWException
	 */
	private function getRandomFId() {
		$unique = array_rand( $this->mathIdGen->getMathTags() );
		return $this->mathIdGen->parserKey2fId( $unique );
	}

	/**
	 * @return mixed
	 */
	public function getFId() {
		return $this->fId;
	}

	/**
	 * @return mixed
	 */
	public function getOldId() {
		return $this->oldId;
	}

	/**
	 * @return Title
	 */
	public function getRevisionTitle() {
		return $this->revision->getTitle();
	}

	private function printIntorduction() {
		$this->enableMathStyles();
		$out = $this->getOutput();
		// $out->addWikiText( "Welcome to the MLP evaluation. Your data will be recorded." );
		// $out->addWikiText( "You are in step {$this->step} of possible evaluation 5 steps" );
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
					wfMessage( 'math-lp-3-pretty-option-1' )->text(), 'latex' );
				$texInfo = $mo->getTexInfo();
				if ( $texInfo ) {
					if ( $texInfo->getChecked() !== $mo->getUserInputTex() ) {
						$this->printSource( $texInfo->getChecked(),
							wfMessage( 'math-lp-3-pretty-option-2' )->text(), 'latex' );
						$this->texInputChanged = true;
					}
				}
				break;
			case self::STEP_RENDERING:
				switch ( $this->subStep ) {
					case '4a':
						$this->getUser()->setOption( 'math', 'png' );
						$this->printMathObjectInContext( false, false,
							$this->getPngRenderingAsHtmlFragment() );
						break;
					case '4b':
						$this->getUser()->setOption( 'math', 'mathml' );
						$this->printMathObjectInContext( false, false,
							$this->getSvgRenderingAsHtmlFragment() );
						break;
					case '4c':
						$this->getUser()->setOption( 'math', 'mathml' );
						$this->printMathObjectInContext( false, false,
							$this->getMathMLRenderingAsHtmlFragment(),
							"SpecialMlpEval::removeSVGs" );
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
				$this->relations = array();
				$rels = $mo->getRelations();
				$srt = $mo->getMathMlAltText();
				if ( !$srt ) {
					// retry to get alltext might be a caching problem
					$r = new MathoidDriver( $mo->getUserInputTex() );
					$srt = $r->getSpeech();
				}
				$this->speechRuleText = $srt;
				foreach ( $this->identifiers as $i ) {
					$this->relations[$i] = array();
					if ( isset( $rels[$i] ) ) {
						foreach ( $rels[$i] as $rel ) {
							$this->relations[$i][] = $rel->definition;
						}
					}
				}
				break;
		}

	}

	private function writeLog( $message, $step = 0, $revId = false ) {
		$userId = $this->getUser()->getId();
		$logData = array(
			'data'   => $this->getRequest()->getValues(),
			'header' => $this->getRequest()->getAllHeaders()
		);
		$json = json_encode( $logData );
		if ( !$revId ) {
			$revId = $this->oldId;
		}
		$row = array(
			'user_id'     => $userId,
			'step'        => $step,
			'json_data'   => $json,
			'revision_id' => $revId,
			'anchor'      => $this->fId,
			'comment'     => $message
		);
		if ( $userId == 0 ) {
			$this->printSource( var_export( $message, true ), 'Error: No user found to store results.' );
			return;
		}
		$dbw = wfGetDB( DB_WRITE );
		$dbw->upsert( 'math_mlp', $row, array( 'user_id', 'revision_id', 'anchor', 'step' ), $row );
	}

	private function resetPage() {
		$req = $this->getRequest();
		$rndRev = $req->getInt( 'oldId' );
		$oldStep = $req->getInt( 'oldStep' );
		if ( $rndRev && $oldStep == 1 ) {
			$this->writeLog( "$rndRev was not selected, by button", - 1, $rndRev );
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
		$valueNames = $req->getValueNames( array( 'oldId', 'wpEditToken' ) );
		foreach ( $valueNames as $name ) {
			$req->unsetVal( $name );
		}
		$this->fId = '';
	}

	private function printSource( $source, $description = "", $language = "text", $linestart = true ) {
		if ( $description ) {
			$description .= ": ";
		}
		$this->getOutput()->addWikiText( "$description<syntaxhighlight lang=\"$language\">" .
			$source . '</syntaxhighlight>', $linestart );
	}

	public function getSvgRenderingAsHtmlFragment( $factor = 2, $tex = false, $options = array() ) {
		$renderer = $this->getMathMlRenderer( $tex, $options );
		return MathObject::getReSizedSvgLink( $renderer, $factor );
	}

	public function getMathMLRenderingAsHtmlFragment(
			$factor = 2.5, $tex = false, $options = array()
	) {
		$renderer = $this->getMathMlRenderer( $tex, $options );
		$largeMathML = $renderer->getMathml();
		$factor = round( $factor * 100 );
		$largeMathML =
			preg_replace( "/<math/", "<math style=\"font-size: {$factor}% !important;\"", $largeMathML );
		return $largeMathML;
	}


	public function getPngRenderingAsHtmlFragment( $factor = 1.75, $tex = false, $options = array() ) {
		$this->updateTex( $tex, $options );
		$renderer = new MathTexvc( $tex, $options );
		$renderer->checkTex();
		$renderer->render();
		$dims = getimagesizefromstring( $renderer->getPng() );
		return Html::element( 'img', array(
			'src'    => $renderer->getMathImageUrl(),
			'width'  => $dims[0] * $factor,
			'height' => $dims[1] * $factor
		) );
	}

	private function printFormula() {
		$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
		$this->getOutput()->addHTML( MathRenderer::renderMath( $mo->getUserInputTex(), array(),
			'mathml' ) );
	}

	private function printTitle() {
		$sectionTitle = wfMessage( "math-lp-{$this->step}-head" )
			->params( $this->subStepNames[$this->subStep], $this->subStep );
		$this->getOutput()->addHTML( "<h2>$sectionTitle</h2>" );
	}

	private function printIntro() {
		$msg =
			new Message( "math-lp-{$this->step}-intro", array( $this->subStepNames[$this->subStep] ) );
		$this->getOutput()->addWikiText( $msg->text() );
	}

	private function getWikiTextLink() {
		$description = "{$this->getRevisionTitle()}#{$this->fId}";
		return "[[Special:Permalink/{$this->oldId}#{$this->fId}|$description]]";
	}

	private function printFormulaRef() {
		$this->getOutput()->addWikiMsg( 'math-lp-formula-ref', $this->getWikiTextLink(),
			$this->selectedMathTag->getWikiText(), $this->revision->getTimestamp() );
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
	 * @throws MWException
	 */
	public function printMathObjectInContext(
			$highlight = true, $collapsed = true, $formula = false, $filter = false ) {
		if ( $collapsed ) {
			$collapsed = "mw-collapsed";
		}
		$out = $this->getOutput();
		$hl = new MathHighlighter( $this->fId, $this->oldId, $highlight );
		$out->addHtml(
			"<div class=\"toccolours mw-collapsible $collapsed\"  style=\"text-align: left\">"
		);
		if ( !$formula ){
			$this->printFormula();
		} else {
			$out->addHTML( $formula );
		}
		$out->addHtml( '<div class="mw-collapsible-content">' );
		global $wgParser;

		$popts = $out->parserOptions();
		$popts->setInterfaceMessage( false );

		$parserOutput = $wgParser->getFreshParser()->parse(
			$hl->getWikiText(), $this->getRevisionTitle(), $popts )->getText();
		if ( $filter ){
			call_user_func_array( $filter, array( &$parserOutput ) );
		}
		$out->addHTML( $parserOutput );
		$out->addHtml( '</div></div>' );
	}

	/**
	 * @return mixed
	 */
	public function getRelations( $key = null ) {
		if ( $key ) {
			return $this->relations[$key];
		}
		return $this->relations;
	}

	/**
	 * @return mixed
	 */
	public function getSpeechRuleText() {
		return $this->speechRuleText;
	}

	/**
	 * @param $tex
	 * @param $options
	 * @return MathMathML
	 */
	private function getMathMlRenderer( $tex, $options ) {
		$this->updateTex( $tex, $options );
		$renderer = new MathMathML( $tex, $options );
		$renderer->checkTex();
		$renderer->render();
		$renderer->writeCache();
		return $renderer;
	}

	/**
	 * @param $tex
	 * @param $options
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
