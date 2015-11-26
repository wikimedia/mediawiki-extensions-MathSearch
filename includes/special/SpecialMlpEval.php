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
	const STEP_STYLE = 3;
	const STEP_IDENTIFIERS = 4;
	const STEP_DEFINITIONS = 5;
	const STEP_FINISHED = 6;
	private $selectedMathTag;
	/**
	 *
	 */
	const MAX_ATTEMPTS = 10;
	/**
	 * @var
	 */
	private $step;
	/**
	 * @var MathIdGenerator
	 */
	private $mathIdGen;
	/**
	 * @var Title
	 */
	private $title;
	/**
	 * @var
	 */
	private $wikitext;
	/**
	 * @var
	 */
	private $snippet;
	/**
	 * @var
	 */
	private $mathTags;
	/**
	 * @var
	 */
	private $oldId;
	/**
	 * @var bool
	 */
	private $lastError = false;
	/**
	 * @var
	 */
	private $fId;

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
		$form->prepareForm();
		$form->show();
		$this->printSource( var_export( $this->getStep(), true ) );
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
			if ( $retVal ) {
				$this->title = $title;
			}
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
		$gen = MathIdGenerator::newFromRevisionId( $revId );
		$mathTags = $gen->getMathTags();
		$tagCount = count( $mathTags );
		if ( $tagCount == 0 ) {
			$this->lastError = "has no math tags";
			return false;
		}
		$this->mathIdGen = $gen;
		$this->title = Revision::newFromId( $revId )->getTitle();
		$this->wikitext = $this->mathIdGen->getWikiText();
		$this->mathTags = $mathTags;
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
		return true;
		// $this->selectedMathTag = $this->mathIdGen->getTagFromId( $fId );
	}

	/**
	 * @return array
	 * @throws MWException
	 */
	private function getRandomFId() {
		$unique = array_rand( $this->mathTags );
		return $this->mathIdGen->parserKey2fId( $unique );
	}

	public function filterPage( $simpleTextField, $allData ) {
		$allData['revisionId'] = 'HERE AM I';
		return $simpleTextField;
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
		return $this->title;
	}

	private function printIntorduction() {
		$out = $this->getOutput();
		$out->addWikiText( "Welcome to the MLP evaluation. Your data will be recorded." );
		$out->addWikiText( "You are in step {$this->step} of possible evaluation 5 steps" );
		switch ( $this->step ) {
			case self::STEP_FORMULA:
				$this->fId = $this->getRandomFId();
				$this->printFormula();
				$hl = new MathHighlighter( $this->fId, $this->oldId );
				$this->getOutput()->addWikiText( $hl->getWikiText() );
				break;
			case self::STEP_STYLE:
				$this->enableMathStyles();
				$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
				$this->printSource( $mo->getUserInputTex(), 'TeX (original user input)', 'latex' );
				$texInfo = $mo->getTexInfo();
				$this->printSource( $texInfo->getChecked(), 'TeX (checked)', 'latex' );
				$this->DisplayRendering( $mo->getUserInputTex(), 'latexml' );
				$this->DisplayRendering( $mo->getUserInputTex(), 'mathml' );
				$this->DisplayRendering( $mo->getUserInputTex(), 'png' );
				break;
			case self::STEP_IDENTIFIERS:
				$this->enableMathStyles();
				$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
				$md = $mo->getTexInfo();
				$this->printFormula();
				$this->printSource( var_export( array_unique( $md->getIdentifiers() ), true ) );
				break;
			case self::STEP_DEFINITIONS:
				$this->enableMathStyles();
				$mo = MathObject::newFromRevisionText( $this->oldId, $this->fId );
				$this->printSource( var_export( $mo->getRelations(), true ) );
				break;
			case self::STEP_FINISHED:
				$out->addWikiText( 'thank you' );
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
		$this->printSource( json_encode( $logObject ), 'json' );

	}

	private function resetPage() {
		$req = $this->getRequest();
		$this->writeLog( "pgRst: User selects another page" );
		$req->unsetVal( 'wpevalPage' );
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
}
