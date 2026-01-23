<?php

use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\HTMLForm\Field\HTMLTextField;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Lets the user import a CSV file with the results
 *
 * @author Moritz Schubotz
 * @author Yaron Koren (This class is based on DT_ImportCSV from the DataTransfer extension)
 */
class SpecialUploadResult extends SpecialPage {

	/** @var ImportCsv */
	private $importer;
	/** @var bool|int|string */
	protected $runId = false;

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly RendererFactory $rendererFactory,
		private readonly RevisionLookup $revisionLookup,
		string $name = 'MathUpload',
	) {
		$listed = (bool)$this->getConfig()->get( 'MathWmcServer' );
		parent::__construct( $name, 'mathwmcsubmit', $listed );
	}

	/**
	 * @param string[] $errors
	 *
	 * @return string
	 */
	private static function formatErrors( $errors ) {
		return wfMessage( 'math-wmc-Warnings' )->text() . "<br />" . implode( "<br />", $errors );
	}

	/**
	 * @param null|string $query
	 *
	 * @throws PermissionsError
	 */
	public function execute( $query ) {
		$this->setHeaders();
		if ( !$this->getUser()->isAllowed( 'mathwmcsubmit' ) ||
			!$this->getConfig()->get( 'MathUploadEnabled' )
		) {
			throw new PermissionsError( 'mathwmcsubmit' );
		}

		$this->getOutput()->addWikiTextAsInterface( $this->msg( 'math-wmc-Introduction' )->text() );
		$this->importer = new ImportCsv( $this->getUser() );
		$formDescriptor = $this->printRunSelector();
		$formDescriptor['File'] = [
			'label-message' => 'math-wmc-FileLabel',
			'help-message' => 'math-wmc-FileHelp',
			'class' => HTMLTextField::class,
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
		// If $wgMathWmcServer is unset there's no math_wmc_runs table to query
		if ( !$this->getConfig()->get( 'MathWmcServer' ) ) {
			return [];
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		$formFields = [];
		$options = [];
		$uID = $this->getUser()->getId();
		$res = $dbr->select( 'math_wmc_runs', [ 'runName', 'runId' ],
			[ 'isDraft' => true, 'userID' => $uID ], __METHOD__ );
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
	 */
	public function runSelectorFilter( $run ) {
		if ( $run == '' ) {
			return date( 'Y-m-d H:i:s (e)' );
		}
		$this->runId = $this->importer->validateRunId( $run );
		$warnings = $this->importer->getWarnings();
		if ( $warnings ) {
			echo "bad wqarni";
			foreach ( $warnings as $warning ) {
				$this->getOutput()->addWikiTextAsInterface( $warning );
			}
		}
		return $run;
	}

	/**
	 * @return bool|string
	 */
	public function runValidatorFilter() {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$uID = $this->getUser()->getId();
		$res = $dbr->selectField( 'math_wmc_runs', 'runName',
			[ 'isDraft' => true, 'userID' => $uID, 'runId' => $this->runId ], __METHOD__ );
		if ( !$res ) {
			return $this->msg( 'math-wmc-SelectRunHelp' )->text();
		} else {
			return true;
		}
	}

	/**
	 * @return bool|null|string
	 */
	public function runFileCheck() {
		$out = $this->getOutput();

		$uploadResult = ImportStreamSource::newFromUpload( 'wpFile' );

		if ( !$uploadResult->isOK() ) {
			return $uploadResult->getWikiText();
		}

		/** @var ImportStreamSource $source */
		$source = $uploadResult->value;

		$out->addWikiMsg( 'math-wmc-importing' );
		// FIXME: ImportStreamSource::$mHandle is private!
		$error_msg = $this->importer->importFromFile( $source->mHandle );

		if ( $error_msg !== null ) {
			return $error_msg;
		}
		if ( count( $this->importer->getWarnings() ) ) {
			$out->addWikiTextAsInterface( self::formatErrors( $this->importer->getWarnings() ) );
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function processInput() {
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
	 * @param array $row
	 */
	private function printResultRow( $row ) {
		$md5 = MathObject::hash2md5( $row['math_inputhash'] );
		if ( $this->getRequest()->getBool( "wpdisplayFormulae" ) ) {
			$this->getOutput()->addModuleStyles( [ 'ext.math.styles' ] );
			$renderer = $this->rendererFactory->getFromHash( $md5 );
			if ( $renderer->render() ) {
				$renderedMath = $renderer->getHtmlOutput();
			} else {
				$renderedMath = $md5;
			}
		} else {
			$renderedMath = $md5;
		}
		$formulaId = MathSearchHooks::generateMathAnchorString( $row['oldId'], $row['fId'] );
		$revisionRecord = $this->revisionLookup->getRevisionById( $row['oldId'] );
		$title = Title::newFromPageIdentity( $revisionRecord->getPage() );
		$link = $title->getLinkURL() . $formulaId;
		$this->getOutput()->addHTML( "<tr><td>{$row['qId']}</td><td><a href=\"$link\" >$formulaId</a></td>
			<td>{$row['rank']}</td><td>$renderedMath</td></tr>" );
	}

	private function displayFeedback() {
		$runId = $this->runId;
		$dbr = $this->dbProvider->getReplicaDatabase();
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
				'`math_wmc_results`.`qId` = `r`.`qId`'
			],
			__METHOD__,
			[
				'GROUP BY' => '`l`.`level`',
				'ORDER BY' => 'count(DISTINCT `r`.`qId`) DESC'
			]
		);
		if ( !$res || $res->numRows() == 0 ) {
			$this->getOutput()->addWikiTextAsInterface( "Score is 0. Check your submission" );
			return;
		} else {
			$this->getOutput()->addWikiTextAsInterface(
				"'''Scored in " . $res->numRows() . " evaluation levels'''"
			);
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

	private function displayFormulaFeedback() {
		$runId = $this->runId;
		$dbr = $this->dbProvider->getReplicaDatabase();
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
			$this->getOutput()->addWikiTextAsInterface( "Score is 0. Check your submission" );
			return;
		} else {
			$this->getOutput()->addWikiTextAsInterface(
				"'''Scored in " . $res->numRows() . " evaluation levels'''"
			);
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

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}
