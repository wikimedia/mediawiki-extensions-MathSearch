<?php
/**
 * Lets the user import a CSV file with the results
 *
 * @author Moritz Schubotz
 */
class SpecialDisplayTopics extends SpecialPage {
	/** @var ImportCsv $importer */
	private $importer;
	/** @var bool $runId */
	protected $runId = false;

	/**
	 * @param string $name
	 */
	public function __construct( $name = 'DisplayTopics' ) {
		global $wgMathWmcServer;
		if ( $wgMathWmcServer ) {
			parent::__construct( $name );
		}
	}

	/**
	 * @param null|string $query
	 *
	 * @throws MWException
	 * @throws PermissionsError
	 */
	function execute( $query ) {
		$this->setHeaders();
		if ( !( $this->getUser()->isAllowed( 'mathwmcsubmit' ) ) ) {
			throw new PermissionsError( 'mathwmcsubmit' );
		}
		$this->getOutput()->addWikiTextAsInterface( wfMessage( 'math-wmc-Queries' )->text() );
		if ( $query ) {
			$this->displayTopic( $query );
		} else {
			$this->displayOverview();
		}
	}

	private function displayOverview( $filter = 1 ) {
		$dbw = wfGetDB( DB_MASTER );
		$cols = [ '#', 'fId', '#Var', '#matches', 'query', 'reference' ];
		// @codingStandardsIgnoreStart
		$res = $dbw->query( <<<SQL
SELECT
  concat( '[[{{FULLPAGENAME}}/',qId,'|',qId,']]'),
  concat('[[Special:Permalink/', oldId, '#math.', oldId, '.', fId, '|', p.page_title, '-',fId, ']]'),
  qVarCount,
  count( mathindex_revision_id ),
  concat('<mquery>', texQuery, '</mquery>' ),
  concat('<math>', math_inputtex, '</math>')
FROM math_wmc_ref ref
  NATURAL JOIN mathlatexml L
  JOIN revision rev ON ref.oldId = rev.rev_id
  JOIN page p on rev.rev_page = p.page_id
  JOIN mathindex ON mathindex_inputhash = math_inputhash
  WHERE $filter
  GROUP BY qId
SQL
		);
		// @codingStandardsIgnoreEnd
		$this->getOutput()->addWikiTextAsInterface( MathSearchUtils::dbRowToWikiTable( $res, $cols ) );
	}

	private function displayTopic( $query ) {
		$out = $this->getOutput();
		$dbr = wfGetDB( DB_REPLICA );
		$qId = $dbr->selectField( 'math_wmc_ref', 'qId', [ 'qID' => $query ] );
		if ( !$qId ) {
			$out->addWikiTextAsInterface( "Topic $query does not exist." );
			return;
		}
		$this->displayOverview( "qID = $qId" );
		$this->printMostFrequentRuns( $qId );
		$this->printIndividualResults( $qId );
	}

	/**
	 * @param $row
	 *
	 * @return string
	 */

	/** @noinspection PhpExpressionResultUnusedInspection */
	private function createTopicTex( $qId, $qVarCount ) {
		/*
		$this->printMostFrequentRuns( $qId );
		if ( $qVarCount > 0 ) {
			$relevance = "  \\begin{relevance}[{$qVarCount}]
			$row->rendering
			\\end{relevance}";
		} else {
			$relevance = '';
		}
		$individualResults = '';
		$res = $dbr->select( 'math_wmc_page_ranks', '*', array( 'qId' => $row->qId ) );
		foreach ( $res as $rank ) {
			$individualResults .= $rank->runId . ': ' . $rank->rank . '; ';
		}
		$out = <<<TEX
		\\begin{topic}{{$tName}}
		\\begin{fquery}
		\${$row->texQuery}\$
		\\end{fquery}

		\\begin{private}
		$relevance
		The reference entry occurs {$row->exactMatches} time(s) in the dataset.
		In the top 25 results the page {$row->title} occurred {$row->count} times.
		The rank varied between {$row->min} and {$row->max} with an average of {$row->avg}.
		In detail the run results are the following: $individualResults
		$mostFrequent
		\\end{private}
		\\end{topic}
		TEX;

		return $out;
		*/
	}

	/**
	 * @param $qId
	 */
	private function printMostFrequentRuns( $qId ) {
		$out = $this->getOutput();
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->query( "select
			  math_inputtex as            rendering,
			  count(distinct runs.userId) cntUser,
			  count(distinct runs.runId)  cntRun,
			  min(`rank`)                 minRank
			from
			  math_wmc_results r
			  join mathlatexml l ON r.math_inputhash = l.math_inputhash
			  join math_wmc_runs runs ON r.runId = runs.runId
			where
			  r.qId = $qId
			  and r.runId in (select runId from math_wmc_runs WHERE isDraft <> 1 and pageOnly <> 1)
			group by r.math_inputhash
			having min(rank) < 50
			order by count(distinct runs.userId) desc, min(rank) asc
			Limit 15"
		);
		$out->addWikiTextAsInterface( "== Most frequent results ==" );
		foreach ( $res as $hit ) {
			$out->addWikiTextAsInterface(
				"*<math>{$hit->rendering}</math>  was found by {$hit->cntUser} users in " .
				" {$hit->cntRun} runs with minimal rank of {$hit->minRank} \n"
			);
			$mo = new MathObject( $hit->rendering );
			$all = $mo->getAllOccurences();
			foreach ( $all as  $occ ) {
				$out->addWikiTextAsInterface( '*' . $occ->printLink2Page( false ) );
			}
		}
	}

	private function printIndividualResults( $qId ) {
		$out = $this->getOutput();
		$out->addWikiTextAsInterface( "== Individual results ==" );
		$dbr = wfGetDB( DB_REPLICA );
		if ( !$dbr->tableExists( 'math_wmc_page_ranks' ) ) {
			MathSearchUtils::createEvaluationTables();
		}
		$res = $dbr->select( 'math_wmc_page_ranks', '*', [ 'qId' => $qId ] );
		foreach ( $res as $rank ) {
			$out->addWikiTextAsInterface( $rank->runId . ': ' . $rank->rank );
		}
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}
