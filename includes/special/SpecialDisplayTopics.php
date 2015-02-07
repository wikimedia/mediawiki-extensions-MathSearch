<?php
/**
 * Lets the user import a CSV file with the results
 *
 * @author Moritz Schubotz
 */
class SpecialDisplayTopics extends SpecialPage {
	/** @type ImportCsv $importer */
	private $importer;
	/** @type bool $runId */
	protected $runId = false;

	/**
	 * @param string $name
	 */
	public function __construct( $name = 'DisplayTopics' ) {
		parent::__construct( $name );
	}

	/**
	 * @param null|string $query
	 *
	 * @throws MWException
	 * @throws PermissionsError
	 */
	function execute( $query ) {
		$this->setHeaders();
		if ( ! ( $this->getUser()->isAllowed( 'mathwmcsubmit' ) ) ) {
			throw new PermissionsError( 'mathwmcsubmit' );
		}

		$this->getOutput()->addWikiText( wfMessage( 'math-wmc-Queries' )->text() );
		$dbw = wfGetDB( DB_MASTER );
		$cols = array('#','fId','query','inputtex');
		$res = $dbw->query( <<<'SQL'
SELECT
  qId,
  concat('[[Special:Permalink/', oldId, '#math.', oldId, '.', fId, '|', p.page_title, '-',fId, ']]'),
  concat('<mquery>', texQuery, '</mquery>' ),
  concat('<math>', math_inputtex, '</math>')
FROM math_wmc_ref ref
  NATURAL JOIN mathlatexml L
  JOIN revision rev ON ref.oldId = rev.rev_id
  JOIN page p on rev.rev_page = p.page_id
SQL
);
		$this->getOutput()->addWikiText( MathSearchUtils::dbRowToWikiTable($res,$cols) );
	}

}
