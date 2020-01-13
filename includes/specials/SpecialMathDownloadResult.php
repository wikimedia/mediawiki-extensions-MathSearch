<?php
/**
 * Lets the user download a CSV file with the results
 *
 * @author Moritz Schubotz
 */
class SpecialMathDownloadResult extends SpecialUploadResult {
	public function __construct( $name = 'MathDownload' ) {
		global $wgMathWmcServer;
		if ( $wgMathWmcServer ) {
			parent::__construct( $name );
		}
	}

	public static function run2CSV( $runId ) {
		$out = ImportCsv::getCsvColumnHeader() . "\n";
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'math_wmc_results',
			[ 'qId' , 'oldId' , 'fId' ],
			[ 'runId' => $runId ],
			__METHOD__,
			[ 'ORDER BY' => [ 'qId' , 'rank' ] ] );
		if ( $res !== false ) {
			foreach ( $res as $row ) {
				$fId = $row->fId == null ? 0 : $row->fId;
				$out .= "{$row->qId},math.{$row->oldId}.{$fId}\n";
			}
		}
		return $out;
	}

	function execute( $query ) {
		$this->setHeaders();
		if ( !$this->getUser()->isAllowed( 'mathwmcsubmit' ) ) {
			throw new PermissionsError( 'mathwmcsubmit' );
		}

		$this->getOutput()->addWikiTextAsInterface( wfMessage( 'math-wmc-download-intro' )->text() );
		$formDescriptor = $this->printRunSelector( 'select' );
		$formDescriptor['run']['help-message'] = '';
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
		$htmlForm->setSubmitTextMsg( 'math-wmc-download-button' );
		$htmlForm->show();
	}

	function processInput() {
		$this->getOutput()->disable();
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="run' . $this->runId . '.csv"' );
		print ( self::run2CSV( $this->runId ) );
		return true;
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}
