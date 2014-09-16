<?php

/**
 * Lets the user import a CSV file with the results
 *
 * @author Moritz Schubotz
 * @author Yaron Koren (This class is baaed on DT_ImportCSV from the DataTransfer extension)
 */
class SpecialUploadResult extends SpecialPage {
	private $columnHeaders = array( 'queryId', 'formulaId' );
	private $warnings = array();
	private $results = array();
	private $runID = false;

	public function __construct( $name = 'MathUpload' ) {
		parent::__construct( $name );
	}

	private static function formatErrors( $errors ) {
		return wfMessage( 'math-wmc-Warnings' )->text() . "<br />" . implode( "<br />", $errors );
	}

	function execute( $query ) {
		$this->setHeaders();
		if ( ! $this->getUser()->isAllowed( 'mathwmcsubmit' ) ) {
			throw new PermissionsError( 'mathwmcsubmit' );
		}

		$this->getOutput()->addWikiText( wfMessage( 'math-wmc-Introduction' )->text() );
		$formDescriptor = $this->printRunSelector();
		$formDescriptor['File'] = array(
			'label-message' => 'math-wmc-FileLabel',
			'help-message' => 'math-wmc-FileHelp',
			'class' => 'HTMLTextField',
			'type' => 'file',
			'required' => true,
			'validation-callback' => array( $this, 'runFileCheck' ),
		);
		$formDescriptor['displayFormulae'] = array(
			'label-message' => 'math-wmc-display-formulae-label',
			'help-message' => 'math-wmc-display-formulae-help',
			'type' => 'check',
		);

		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitCallback( array( $this, 'processInput' ) );
		$htmlForm->show();

	}

	protected function printRunSelector() {
		$dbr = wfGetDB( DB_SLAVE );
		$formFields = array();
		$options = array();
		$uID = $this->getUser()->getId();
		$res = $dbr->select( 'math_wmc_runs', array( 'runName', 'runId' ),
			array( 'isDraft' => true, 'userID' => $uID ) );
		foreach ( $res as $row ) {
			$options[ $row->runName . " (" . $row->runId . ")" ] = $row->runId;
		}
		//Probably we want to add more field in the future
		$formFields['run'] = array( 'type' => 'selectorother',
			'label-message' => 'math-wmc-SelectRun',
			'options' => $options,
			'required' => true,
			'help-message' => 'math-wmc-SelectRunHelp',
			'filter-callback' => array( $this, 'runSelectorFilter' ),
			'validation-callback' => array( $this, 'runValidatorFilter' ),
			 //'section' => 'wmcSectionRun'
		);
		return $formFields;
	}

	function runSelectorFilter( $run ) {
		if ( $run == '' ) {
			return date( 'Y-m-d H:i:s (e)' );
		}
		$dbw = wfGetDB( DB_MASTER );
		$uID = $this->getUser()->getId();
		$res = $dbw->selectField( 'math_wmc_runs', 'runName',
			array( 'isDraft' => true, 'userID' => $uID, 'runId' => $run ) );
		if ( ! $res ) {
			$exists = $dbw->selectField( 'math_wmc_runs', 'runId',
				array( 'userID' => $uID, 'runName' => $run ) );
			if ( ! $exists ) {
				$success = $dbw->insert( 'math_wmc_runs',
					array( 'isDraft' => true, 'userID' => $uID, 'runName' => $run ) );
				if ( $success ) {
					$this->runID = $dbw->insertId();
					$this->getOutput()->addWikiMsg( 'math-wmc-RunAdded', $run, $this->runID );
				} else {
					$this->runID = false;
					$this->getOutput()->addWikiMsg( 'math-wmc-RunAddError', $run );
				}
			} else {
				$this->getOutput()->addWikiMsg( 'math-wmc-RunAddExist', $run, $exists );
				$this->runID = false;
			}
		} else {
			$this->runID = $run;
		}
		return $run;
	}

	function runValidatorFilter() {
		$dbr = wfGetDB( DB_SLAVE );
		$uID = $this->getUser()->getId();
		$res = $dbr->selectField( 'math_wmc_runs', 'runName',
			array( 'isDraft' => true, 'userID' => $uID, 'runId' => $this->runID ) );
		if ( ! $res ) {
			return wfMessage( 'math-wmc-SelectRunHelp' )->text();
		} else {
			return true;
		}
	}

	function runFileCheck( ) {
		$out = $this->getOutput();


		$uploadResult = ImportStreamSource::newFromUpload( 'wpFile' );

		if ( ! $uploadResult->isOK() ) {
			return $uploadResult->getWikiText();
		}

		$source = $uploadResult->value;

		$this->results = array();
		$out->addWikiMsg( 'math-wmc-importing' );
		$error_msg = $this->importFromFile( $source->mHandle );

		if ( ! is_null( $error_msg ) ) {
			return $error_msg;
		}
		if ( sizeof( $this->warnings ) ) {
			$out->addWikiText( self::formatErrors( $this->warnings ) );
		}

		return true;
	}

	public static function deleteRun( $runID ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'math_wmc_results', array( 'runId' => $runID ) );
	}

	function processInput(  ) {
		$this->getOutput()->addWikiMsg( "math-wmc-SubmissionSuccess" );
		self::deleteRun( $this->runID );
		$dbw = wfGetDB( DB_MASTER );
		//TODO: Find adequate API call
		$this->getOutput()->addHTML('<table border="1" style="width:100%">
  <tr>
    <th>queryId</th>
    <th>formulaId</th>
    <th>rank</th>
    <th>rendering</th>
  </tr>');
		foreach ( $this->results as $result ) {
			$result['runId'] = $this->runID; //make sure that runId is correct
			$dbw->insert( 'math_wmc_results', $result );
			$this->printResultRow( $result );
		}
		$this->getOutput()->addHTML('</table>');
		$this->displayFeedback();
		return true;
	}

	private function printResultRow( $row ){
		$md5 = MathObject::hash2md5( $row['math_inputhash'] );
		if( $this->getRequest()->getBool( "wpdisplayFormulae" ) ){
			$this->getOutput()->addModuleStyles( array( 'ext.math.styles' ) );
			$renderer = MathLaTeXML::newFromMd5($md5);
			if ( $renderer->render() ){
				$renderedMath = $renderer->getHtmlOutput();
			} else {
				$renderedMath = $md5;
			}
		} else {
			$renderedMath = $md5;
		}
		$formulaId = "math.{$row['oldId']}.{$row['fId']}";
		$link=Revision::newFromId( $row['oldId'] )->getTitle()->getCanonicalURL()."#$formulaId";
		$this->getOutput()->addHTML("<tr><td>${row['qId']}</td><td><a href=\"${link}\">$formulaId</a></td>
			<td>${row['rank']}</td><td>$renderedMath</td></tr>");
	}
	protected function importFromFile( $csv_file ) {
		if ( is_null( $csv_file ) ) {
			return wfMessage( 'emptyfile' )->text();
		}

		$table = array();

		while ( $line = fgetcsv( $csv_file ) ) {
			array_push( $table, $line );
		}
		fclose( $csv_file );

		// Get rid of the "byte order mark", if it's there - this is
		// a three-character string sometimes put at the beginning
		// of files to indicate its encoding.
		// Code copied from:
		// http://www.dotvoid.com/2010/04/detecting-utf-bom-byte-order-mark/
		$byteOrderMark = pack( "CCC", 0xef, 0xbb, 0xbf );
		if ( 0 == strncmp( $table[0][0], $byteOrderMark, 3 ) ) {
			$table[0][0] = substr( $table[0][0], 3 );
			// If there were quotation marks around this value,
			// they didn't get removed, so remove them now.
			$table[0][0] = trim( $table[0][0], '"' );
		}

		return $this->importFromArray( $table );

	}

	private function isValidQId( $qId ) {
		$dbr = wfGetDB( DB_SLAVE );
		if ( $dbr->selectField( 'math_wmc_ref', 'qId', array( 'qId' => $qId ) ) ) {
			return true;
		} else {
			return false;
		}
	}

	private function  getInputHash( $pId, $eId ) {
		$dbr = wfGetDB( DB_SLAVE );
		return $dbr->selectField( 'mathindex', 'mathindex_inputhash',
			array( 'mathindex_page_id' => $pId, 'mathindex_anchor' => $eId ) );
	}

	protected function importFromArray( $table ) {
		global $wgMathWmcMaxResults;
		define( 'ImportPattern', '/math\.(\d+)\.(\d+)/' );

		// check header line
		$uploadedHeaders = $table[0];
		if ( $uploadedHeaders != $this->columnHeaders ) {
			$error_msg = wfMessage( 'math-wmc-bad-header' )->text();
			return $error_msg;
		}
		$rank = 0;
		$lastQueryID = 0;
		foreach ( $table as $i => $line ) {
			if ( $i == 0 )
				continue;
			$pId = false;
			$eId = false;
			$fHash = false;
			$qId = trim( $line[0] );
			$fId = trim( $line[1] );
			$qValid = $this->isValidQId( $qId );
			if ( $qValid == false ) {
				$this->warnings[] = wfMessage( 'math-wmc-wrong-query-reference', $i, $qId )->text();
			}
			if ( preg_match( ImportPattern, $fId, $m ) ) {
				$pId = (int)$m[1];
				$eId = (int)$m[2];
				$fHash = $this->getInputHash( $pId, $eId );
				if ( $fHash == false ) {
					$this->warnings[] = wfMessage( 'math-wmc-wrong-formula-reference', $i, $pId, $eId )->text();
				}
			} else {
				$this->warnings[] = wfMessage( 'math-wmc-malformed-formula-reference', $i, $fId, ImportPattern )->text();
			}
			if ( $qValid === true && $fHash !== false ) {
				// a valid result has been submitted
				if ( $qId == $lastQueryID ) {
					$rank ++;
				} else {
					$lastQueryID = $qId;
					$rank = 1;
				}
				if ( $rank <= $wgMathWmcMaxResults ) {
					$this->addValidatedResult( $qId, $pId, $eId, $fHash, $rank );
				} else {
					$this->warnings[] = wfMessage( 'math-wmc-too-many-results', $i, $qId, $fId, $rank, $wgMathWmcMaxResults )->text();
				}
			}
		}
		return null;
	}

	private function addValidatedResult( $qId, $pId, $eId, $fHash, $rank ) {
		$this->results[] = array(
			'runId' => $this->runID,
			'qId'   => $qId,
			'oldId' => $pId,
			'fId'   => $eId,
			'rank' => $rank,
			'math_inputhash' => $fHash );
	}

	private function displayFeedback(){
		$runId=$this->runID;
		$dbr=wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			array('l'=>'math_wmc_rank_levels','r'=>'math_wmc_ref','math_wmc_results'),
			array( 'count(DISTINCT `r`.`qId`)  AS `c`',
				'`l`.`level`                AS `level`'),
			array( "(`math_wmc_results`.`rank` <= `l`.`level`)" ,
				'runId'=>$runId,
				'`math_wmc_results`.`oldId` = `r`.`oldId`',
				'`math_wmc_results`.`qId` = `r`.`qId`'
			),
			__METHOD__,
			array( 'GROUP BY' => '`l`.`level`',
				'ORDER BY' => 'count(DISTINCT `r`.`qId`) DESC')
		);
		if ( ! $res || $res->numRows() == 0 ){
			$this->getOutput()->addWikiText( "Score is 0. Check your submission");
			return ;
		} else {
			$this->getOutput()->addWikiText( "'''Scored in " . $res->numRows() . " evaluation levels'''");
		}

		$this->getOutput()->addHTML('<table border="1" style="width:100%">
  <tr>
    <th>number of correct results</th>
    <th>rank cutoff</th>
  </tr>');
		foreach ( $res as $result ) {
			$c=$result->c;
			$l=$result->level;
			$this->getOutput()->addHTML("
  <tr>
    <td>$c</td>
    <td>$l</td>
  </tr>");
		}
		$this->getOutput()->addHTML('</table>');
	}
}
