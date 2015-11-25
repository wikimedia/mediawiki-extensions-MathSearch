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
		if ( $req->getInt( 'oldStep' ) === 2 ){
			switch ( $req->getInt( 'wpsnippetSelector' ) ){
						case MlpEvalForm::OPT_BACK;
						return $this->resetPage();
						case MlpEvalForm::OPT_RETRY:
						return $this->resetFormula();
						case MlpEvalForm::OPT_CONTINUE:
						$this->writeLog( "pgRst: User selects formula $fId" );
			}
		}
		return $this->setStep( 3 );
	}

	/**
	 * The main function
	 */
	public function execute( $par ) {
		$this->loadData();
		$this->setHeaders();
		$this->printIntorduction();
		$this->printSnippet();
		$this->printVisualEval();
		$form = new MlpEvalForm( $this );
		$form->prepareForm();
		$form->show();
		$this->printSource( var_export( $this->getStep(), true ) );
		$this->printSource( var_export( $this->getRequest(), true ) );
		return;
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

	private function displaySelectFormulaForm() {
		$formDescriptor = array();
		$this->enableMathStyles();

		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Continue' );
		$htmlForm->addHiddenField( 'step', 3 );
// 		$htmlForm->addHiddenField( 'wpevalPage', $this->title->getText() );
		$htmlForm->setSubmitCallback( array( $this, 'processFormulaInput' ) );
		$htmlForm->setHeaderText( "<h2>Step 2: Select a formula</h2>" );
		$htmlForm->prepareForm();
		$htmlForm->displayForm( '' );
		$this->fId = $this->getRandomFId();
		$this->printSource( $this->fId );
		$hl = new MathHighlighter( $this->fId, $this->oldId );
		$this->getOutput()->addWikiText( $hl->getWikiText() );
	}

	private function enableMathStyles() {
		$out = $this->getOutput();
		$out->addModuleStyles(
			array( 'ext.math.styles', 'ext.math.desktop.styles', 'ext.math.scripts' )
		);
	}


	/**
	 *
	 * @param String $src
	 * @param String $lang the language of the source snippet
	 */
	public function printSource( $src, $lang = "xml" ) {
		$out = $this->getOutput();
		$out->addWikiText( '<source lang="' . $lang . '">' . $src . '</source>' );
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
	}

	private function writeLog( $string ) {
		$out = $this->getOutput();
		$out->addWikiText( "LOG:\n " . $string . "--END-LOG" );

	}

	private function printSnippet() {
		if ( $this->step == 2 ) {
			$this->fId = $this->getRandomFId();
			$this->printSource( $this->fId );
			$hl = new MathHighlighter( $this->fId, $this->oldId );
			$this->getOutput()->addWikiText( $hl->getWikiText() );
		}

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

	private function printVisualEval() {
		$out = $this->getOutput();
		if ( $this->step == 3 ){

			$out->addWikiText( "Visual Evaluation" );
		}
	}
	private function printSelectIdentifier() {
		$out = $this->getOutput();
		if ( $this->step == 4 ){

			$out->addWikiText( "Visual Evaluation" );
		}
	}
	private function printSelectMeaning() {
		$out = $this->getOutput();
		if ( $this->step == 5 ){

			$out->addWikiText( "Visual Evaluation" );
		}
	}
}
