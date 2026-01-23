<?php

use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Revision\RevisionLookup;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Lets the user download a CSV file with the results
 *
 * @author Moritz Schubotz
 */
class SpecialMathDownloadResult extends SpecialUploadResult {

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		RendererFactory $rendererFactory,
		RevisionLookup $revisionLookup,
	) {
		parent::__construct(
			$dbProvider,
			$rendererFactory,
			$revisionLookup,
			'MathDownload'
		);
	}

	public function run2CSV( $runId ) {
		$out = ImportCsv::getCsvColumnHeader() . "\n";
		$dbr = $this->dbProvider->getReplicaDatabase();
		$res = $dbr->select( 'math_wmc_results',
			[ 'qId', 'oldId', 'fId' ],
			[ 'runId' => $runId ],
			__METHOD__,
			[ 'ORDER BY' => [ 'qId', 'rank' ] ] );
		if ( $res !== false ) {
			foreach ( $res as $row ) {
				$fId = $row->fId == null ? 0 : $row->fId;
				$out .= "{$row->qId},math.{$row->oldId}.{$fId}\n";
			}
		}
		return $out;
	}

	public function execute( $query ) {
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

	public function processInput() {
		$this->getOutput()->disable();
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="run' . $this->runId . '.csv"' );
		print ( $this->run2CSV( $this->runId ) );
		return true;
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}
