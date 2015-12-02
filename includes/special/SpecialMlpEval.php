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
	const STEP_FORMULA =2;
	const STEP_TEX = 3;
	const STEP_RENDERING = 4;
	const STEP_IDENTIFIERS = 5;
	const STEP_DEFINITIONS = 6;
	const STEP_FINISHED = 7;
	const MAX_ATTEMPTS = 10;
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
		if ( $revId === 0 ) {
			return $this->setStep( 1 );
		}
		if ( $this->setRevision( $revId ) === false ) {
			return $this->setStep( 1 );
		}
		if ( $req->getText( 'pgRst' ) ) {
			return $this->resetPage();
		} elseif ( $req->getInt( 'oldStep' ) === 1 ) {
			$this->writeLog( "pgSelect: User selects page" . $revId );
		}
		$fId = $req->getText( 'fId' );
		if ( $fId === '' ) {
			$this->setFId( $this->getRandomFId() );
			return $this->setStep( 2 );
		}
		if ( $this->setFId( $fId ) === false ) {
			return $this->setStep( 2 );
		}
		// @TODO: Switch back to === 2
		if ( $req->getInt( 'oldStep' ) > 1 ){
			switch ( $req->getInt( 'wpsnippetSelector' ) ){
						case MlpEvalForm::OPT_BACK;
						return $this->resetPage();
						case MlpEvalForm::OPT_RETRY:
						return $this->resetFormula();
						case MlpEvalForm::OPT_CONTINUE:
						$this->writeLog( "pgRst: User selects formula $fId" );
			}
		}
		if ( $req->getArray( 'wp5-identifiers' ) ){
			$this->identifiers = $req->getArray( 'wp5-identifiers' );
			$missing = $req->getText( 'wp5-missing' );
			if ( $missing ){
				// TODO: Check for invalid TeX
				$this->identifiers = array_merge( $this->identifiers, preg_split( '/[\n\r]/', $missing ) );
			}
		}
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
		// $this->printSource( var_export( $this->getStep(), true ) );
		$this->printSource( var_export( $this->getRequest(), true ) );
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
		$out->addModuleStyles(
			array( 'ext.math.styles', 'ext.math.desktop.styles', 'ext.math.scripts' )
		);
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
	public function getTitle() {
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
					if ( $texInfo->getChecked() !== $mo->getUserInputTex() ){
						$this->printSource( $texInfo->getChecked(),
							wfMessage( 'math-lp-3-pretty-option-2' )->text(), 'latex' );
						$this->texInputChanged = true;
					}
				}
				break;
			case self::STEP_RENDERING:
				$mo = $this->selectedMathTag;
				$this->DisplayRendering( $mo->getUserInputTex(), 'latexml' );
				$this->DisplayRendering( $mo->getUserInputTex(), 'mathml' );
				$this->DisplayRendering( $mo->getUserInputTex(), 'png' );
				break;
			case self::STEP_IDENTIFIERS:
				$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
				$md = $mo->getTexInfo();
				if ( $md ) {
					// $this->printFormula();
					$this->identifiers = array_unique( $md->getIdentifiers() );
					// $this->getRequest()->setVal('wp5-identifiers',array('h','f'));
					// $this->printSource( var_export( array_unique( $md->getIdentifiers() ), true ) );
				}
					break;
			case self::STEP_DEFINITIONS:
				$this->printMathObjectInContext();
				$this->enableMathStyles();
				$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
				$this->relations = array();
				$rels =  $mo->getRelations();
				foreach ( $this->identifiers as $i ){
					$this->relations[$i] = array();
					if ( isset( $rels[$i] ) ){
						foreach ( $rels[$i] as $rel ){
							$this->relations[$i][] = $rel->definition;
						}
					}
				}
				break;
		}

	}

	private function writeLog( $message ) {
		$logObject = array(
			'message' => $message,
			'user' => $this->getUser()->getName(),
			'step' => $this->step,
			'oldId' => $this->oldId,
			'fId' => $this->fId,
		);
		// $this->printSource( json_encode( $logObject ), 'json' );

	}

	private function resetPage() {
		$req = $this->getRequest();
		$this->writeLog( "pgRst: User selects another page" );
		$req->unsetVal( 'wp1-page' );
		$req->unsetVal( 'oldId' );
		$req->unsetVal( 'wpsnippetSelector' );
		$req->unsetVal( 'fId' );
		$req->unsetVal( 'oldStep' );
		$this->fId = '';
		return $this->setStep( 1 );
	}
	private function resetFormula() {
		$req = $this->getRequest();
		$this->writeLog( "pgRst: User selects another formula" );
		$req->unsetVal( 'wpsnippetSelector' );
		$req->unsetVal( 'fId' );
		$req->unsetVal( 'oldStep' );
		$this->fId = '';
		return $this->setStep( 2 );
	}

	private function printSource( $source, $description = "", $language = "text", $linestart = true ) {
		if ( $description ) {
			$description .= ": ";
		}
		$this->getOutput()->addWikiText( "$description<syntaxhighlight lang=\"$language\">" .
				$source . '</syntaxhighlight>', $linestart );
	}

	private function DisplayRendering( $tex, $mode ) {
		global $wgMathValidModes;
		if ( !in_array( $mode, $wgMathValidModes ) ) {
			return;
		}
		$out = $this->getOutput();
		$names = MathHooks::getMathNames();
		$name = $names[$mode];
		$out->addWikiText( "=== $name rendering === " );
		$renderer = MathRenderer::getRenderer( $tex, array(), $mode );
		$renderer->checkTex();
		$renderer->render();
		$out->addHTML( $renderer->getHtmlOutput() );
		$renderer->writeCache();

	}

	private function printFormula() {
		$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
		$this->getOutput()->addHTML( MathRenderer::renderMath( $mo->getUserInputTex(), array(),
				'mathml' ) );
	}

	private function printTitle() {
		$sectionTitle = wfMessage( "math-lp-{$this->step}-head" )->text();
		$this->getOutput()->addHTML( "<h2>$sectionTitle</h2>" );
	}

	private function printIntro() {
		$msg = new Message( "math-lp-{$this->step}-intro", array( 'moritz','max' ) );
		$this->getOutput()->addWikiText( $msg->text() );
	}

	private function getWikiTextLink() {
		return "[[Special:Permalink/{$this->oldId}#{$this->fId}|{$this->getTitle()}#{$this->fId}]]";
	}

	private function printFormulaRef() {
		$this->getOutput()->addWikiMsg( 'math-lp-formula-ref', $this->getWikiTextLink(),
			$this->selectedMathTag->getWikiText(), $this->revision->getTimestamp() );
	}

	private function printPrefix() {
		if ( $this->step > 1 ){
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
	private function printMathObjectInContext() {
		$out = $this->getOutput();
		$hl = new MathHighlighter( $this->fId, $this->oldId );
		$out->addHtml(
				'<div class="toccolours mw-collapsible mw-collapsed"  style="text-align: left">'
		);
		$this->printFormula();
		$out->addHtml( '<div class="mw-collapsible-content">' );
		$out->addWikiText( $hl->getWikiText() );
		$out->addHtml( '</div></div>' );
	}

	/**
	 * @return mixed
	 */
	public function getRelations( $key = null ) {
		if ( $key ){
			return $this->relations[$key];
		}
		return $this->relations;
	}

}
