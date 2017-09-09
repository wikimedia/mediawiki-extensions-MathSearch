<?php

/**
 * Lets the user import a CSV file with the results
 *
 * @author Moritz Schubotz
 * @author Yaron Koren (This class is baaed on DT_ImportCSV from the DataTransfer extension)
 */
class SpecialUploadResult extends SpecialPage {
	/** @type ImportCsv $importer */
	private $importer;
	/** @type bool $runId */
	protected $runId = false;

	/**
	 * @param string $name
	 */
	public function __construct( $name = 'MathUpload' ) {
		global $wgMathWmcServer;
		if ( $wgMathWmcServer ) {
			parent::__construct( $name );
		}
	}

	/**
	 * @param $errors
	 *
	 * @return string
	 */
	private static function formatErrors( $errors ) {
		return wfMessage( 'math-wmc-Warnings' )->text() . "<br />" . implode( "<br />", $errors );
	}

	/**
	 * @param null|string $query
	 *
	 * @throws MWException
	 * @throws PermissionsError
	 */
	function execute( $query ) {
		global $wgMathUploadEnabled;
		$this->setHeaders();
		if ( !( $this->getUser()->isAllowed( 'mathwmcsubmit' ) && $wgMathUploadEnabled === true ) ) {
			throw new PermissionsError( 'mathwmcsubmit' );
		}

		$this->getOutput()->addWikiText( wfMessage( 'math-wmc-Introduction' )->text() );
		$this->importer = new ImportCsv( $this->getUser() );
		$formDescriptor = $this->printRunSelector();
		$formDescriptor['File'] = [
			'label-message' => 'math-wmc-FileLabel',
			'help-message' => 'math-wmc-FileHelp',
			'class' => 'HTMLTextField',
			'type' => 'file',
			'required' => true,
			'validation-callback' => [ $this, 'runFileCheck' ],
		];
		$formDescriptor['displayFormulae'] = [
			'label-message' => 'math-wmc-display-formulae-label',
			'help-message' => 'math-wmc-display-formulae-help',
			'type' => 'check',
		];
		$formDescriptor['attachResults'] = [
			'label-message' => 'math-wmc-attach-results-label',
			'help-message' => 'math-wmc-attach-results-help',
			'type' => 'check',
		];
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
		$htmlForm->show();
	}

	/**
	 * @param string $type
	 *
	 * @return array
	 */
	protected function printRunSelector( $type = 'selectorother' ) {
		$dbr = wfGetDB( DB_SLAVE );
		$formFields = [];
		$options = [];
		$uID = $this->getUser()->getId();
		$res = $dbr->select( 'math_wmc_runs', [ 'runName', 'runId' ],
			[ 'isDraft' => true, 'userID' => $uID ] );
		foreach ( $res as $row ) {
			$options[ $row->runName . " (" . $row->runId . ")" ] = $row->runId;
		}
		// Probably we want to add more fields in the future
		$formFields['run'] = [
			'type' => $type,
			'label-message' => 'math-wmc-SelectRun',
			'options' => $options,
			'required' => true,
			'help-message' => 'math-wmc-SelectRunHelp',
			'filter-callback' => [ $this, 'runSelectorFilter' ],
			'validation-callback' => [ $this, 'runValidatorFilter' ],
			// 'section' => 'math-wmc-SectionRun'
		];
		return $formFields;
	}

	/**
	 * @param string $run
	 *
	 * @return bool|int|string
	 * @throws MWException
	 */
	function runSelectorFilter( $run ) {
		if ( $run == '' ) {
			return date( 'Y-m-d H:i:s (e)' );
		}
		$this->runId = $this->importer->validateRunId( $run );
		/** @type array $warnings */
		$warnings = $this->importer->getWarnings();
		if ( $warnings ) {
			echo "bad wqarni";
			foreach ( $warnings as $warning ) {
				$this->getOutput()->addWikiText( $warning );
			}
		}
		return $run;
	}

	/**
	 * @return bool|string
	 */
	function runValidatorFilter() {
		$dbr = wfGetDB( DB_SLAVE );
		$uID = $this->getUser()->getId();
		$res = $dbr->selectField( 'math_wmc_runs', 'runName',
			[ 'isDraft' => true, 'userID' => $uID, 'runId' => $this->runId ] );
		if ( !$res ) {
			return wfMessage( 'math-wmc-SelectRunHelp' )->text();
		} else {
			return true;
		}
	}

	/**
	 * @return bool|null|string
	 * @throws MWException
	 */
	function runFileCheck() {
		$out = $this->getOutput();

		$uploadResult = ImportStreamSource::newFromUpload( 'wpFile' );

		if ( !$uploadResult->isOK() ) {
			return $uploadResult->getWikiText();
		}

		$source = $uploadResult->value;

		$out->addWikiMsg( 'math-wmc-importing' );
		$error_msg = $this->importer->importFromFile( $source->mHandle );

		if ( !is_null( $error_msg ) ) {
			return $error_msg;
		}
		if ( count( $this->importer->getWarnings() ) ) {
			$out->addWikiText( self::formatErrors( $this->importer->getWarnings() ) );
		}

		return true;
	}

	/**
	 * @return bool
	 */
	function processInput() {
		$this->getOutput()->addWikiMsg( "math-wmc-SubmissionSuccess" );
		$this->importer->setOverwrite( !$this->getRequest()->getBool( "wpattachResults" ) );
		$this->importer->processInput();
		// TODO: Find adequate API call
		$this->getOutput()->addHTML( '<table border="1" style="width:100%">
  <tr>
    <th>queryId</th>
    <th>formulaId</th>
    <th>rank</th>
    <th>rendering</th>
  </tr>' );
		foreach ( $this->importer->getResults() as $result ) {
			$this->printResultRow( $result );
		}
		$this->getOutput()->addHTML( '</table>' );
		$this->displayFeedback();
		return true;
	}

	/**
	 * @param $row
	 */
	private function printResultRow( $row ) {
		$md5 = MathObject::hash2md5( $row['math_inputhash'] );
		if ( $this->getRequest()->getBool( "wpdisplayFormulae" ) ) {
			$this->getOutput()->addModuleStyles( [ 'ext.math.styles' ] );
			$renderer = MathLaTeXML::newFromMd5( $md5 );
			if ( $renderer->render() ) {
				$renderedMath = $renderer->getHtmlOutput();
			} else {
				$renderedMath = $md5;
			}
		} else {
			$renderedMath = $md5;
		}
		$formulaId = MathSearchHooks::generateMathAnchorString( $row['oldId'], $row['fId'] );
		$link = Revision::newFromId( $row['oldId'] )->getTitle()->getLinkURL().$formulaId;
		$this->getOutput()->addHTML( "<tr><td>${row['qId']}</td><td><a href=\"$link\" >$formulaId</a></td>
			<td>${row['rank']}</td><td>$renderedMath</td></tr>" );
	}

	/**
	 * @throws MWException
	 */
	private function displayFeedback() {
		$runId = $this->runId;
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			[ 'l' => 'math_wmc_rank_levels', 'r' => 'math_wmc_ref', 'math_wmc_results' ],
			[
				'count(DISTINCT `r`.`qId`)  AS `c`',
				'`l`.`level`                AS `level`'
			],
			[
				"(`math_wmc_results`.`rank` <= `l`.`level`)" ,
				'runId' => $runId,
				'`math_wmc_results`.`oldId` = `r`.`oldId`',
				'`math_wmc_results`.`qId` = `r`.`qId`'
			],
			__METHOD__,
			[
				'GROUP BY' => '`l`.`level`',
				'ORDER BY' => 'count(DISTINCT `r`.`qId`) DESC'
			]
		);
		if ( !$res || $res->numRows() == 0 ) {
			$this->getOutput()->addWikiText( "Score is 0. Check your submission" );
			return;
		} else {
			$this->getOutput()->addWikiText( "'''Scored in " . $res->numRows() . " evaluation levels'''" );
		}

		$this->getOutput()->addHTML( '<table border="1" style="width:100%">
  <tr>
    <th>number of correct results</th>
    <th>rank cutoff</th>
  </tr>' );
		foreach ( $res as $result ) {
			$c = $result->c;
			$l = $result->level;
			$this->getOutput()->addHTML( "
  <tr>
    <td>$c</td>
    <td>$l</td>
  </tr>" );
		}
		$this->getOutput()->addHTML( '</table>' );
	}

	/**
	 * @throws MWException
	 */
	private function displayFormulaFeedback() {
		$runId = $this->runId;
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			[ 'l' => 'math_wmc_rank_levels', 'r' => 'math_wmc_ref', 'math_wmc_results' ],
			[
				'count(DISTINCT `r`.`qId`)  AS `c`',
				'`l`.`level`                AS `level`'
			],
			[
				"(`math_wmc_results`.`rank` <= `l`.`level`)",
				'runId' => $runId,
				'`math_wmc_results`.`oldId` = `r`.`oldId`',
				'`math_wmc_results`.`qId` = `r`.`qId`',
				'`math_wmc_results`.`fId` = `r`.`fId`',
			],
			__METHOD__,
			[
				'GROUP BY' => '`l`.`level`',
				'ORDER BY' => 'count(DISTINCT `r`.`qId`) DESC'
			]
		);
		if ( !$res || $res->numRows() == 0 ) {
			$this->getOutput()->addWikiText( "Score is 0. Check your submission" );
			return;
		} else {
			$this->getOutput()->addWikiText( "'''Scored in " . $res->numRows() . " evaluation levels'''" );
		}

		$this->getOutput()->addHTML( '<table border="1" style="width:100%">
  <tr>
    <th>number of correct results</th>
    <th>rank cutoff</th>
  </tr>' );
		foreach ( $res as $result ) {
			$c = $result->c;
			$l = $result->level;
			$this->getOutput()->addHTML( "
  <tr>
    <td>$c</td>
    <td>$l</td>
  </tr>" );
		}
		$this->getOutput()->addHTML( '</table>' );
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}
