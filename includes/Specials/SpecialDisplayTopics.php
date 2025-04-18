<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * Lets the user import a CSV file with the results
 *
 * @author Moritz Schubotz
 */
class SpecialDisplayTopics extends SpecialPage {

	/**
	 * @param string $name
	 */
	public function __construct( $name = 'DisplayTopics' ) {
		$listed = (bool)$this->getConfig()->get( 'MathWmcServer' );
		parent::__construct( $name, 'mathwmcsubmit', $listed );
	}

	/**
	 * @param null|string $query
	 *
	 * @throws PermissionsError
	 */
	public function execute( $query ) {
		$this->setHeaders();
		if ( !( $this->getUser()->isAllowed( 'mathwmcsubmit' ) ) ) {
			throw new PermissionsError( 'mathwmcsubmit' );
		}
		$this->getOutput()->addWikiTextAsInterface( $this->msg( 'math-wmc-Queries' )->text() );
		if ( $query ) {
			$this->displayTopic( $query );
		} else {
			$this->displayOverview();
		}
	}

	private function displayOverview( int $filter = 1 ) {
		// If $wgMathWmcServer is unset there's no math_wmc_ref table to query
		if ( !$this->getConfig()->get( 'MathWmcServer' ) ) {
			return;
		}

		$dbw = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getPrimaryDatabase();
		$cols = [ '#', 'fId', '#Var', '#matches', 'query', 'reference' ];
		$res = $dbw->query( <<<SQL
SELECT
  concat( '[[{{FULLPAGENAME}}/',qId,'|',qId,']]'),
  concat('[[Special:Permalink/', oldId, '#math.', oldId, '.', fId, '|', p.page_title, '-',fId, ']]'),
  qVarCount,
  count( mathindex_revision_id ),
  concat('<mquery>', texQuery, '</mquery>' ),
  concat('<math>', math_input, '</math>')
FROM math_wmc_ref ref
  NATURAL JOIN mathlog L
  JOIN revision rev ON ref.oldId = rev.rev_id
  JOIN page p on rev.rev_page = p.page_id
  JOIN mathindex ON mathindex_inputhash = math_inputhash
  WHERE $filter
  GROUP BY qId
SQL
			,
			__METHOD__
		);
		$this->getOutput()->addWikiTextAsInterface( MathSearchUtils::dbRowToWikiTable( $res, $cols ) );
	}

	private function displayTopic( int $query ) {
		// If $wgMathWmcServer is unset there's no math_wmc_ref table to query
		if ( !$this->getConfig()->get( 'MathWmcServer' ) ) {
			return;
		}

		$out = $this->getOutput();
		$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getReplicaDatabase();
		$qId = $dbr->selectField( 'math_wmc_ref', 'qId', [ 'qID' => $query ], __METHOD__ );
		if ( !$qId ) {
			$out->addWikiTextAsInterface( "Topic $query does not exist." );
			return;
		}
		$this->displayOverview( "qID = $qId" );
		$this->printMostFrequentRuns( $qId );
		$this->printIndividualResults( $qId );
	}

	/**
	 * @param int $qId
	 */
	private function printMostFrequentRuns( $qId ) {
		$out = $this->getOutput();
		$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getReplicaDatabase();
		$res = $dbr->query( "select
			  math_input as            rendering,
			  count(distinct runs.userId) cntUser,
			  count(distinct runs.runId)  cntRun,
			  min(`rank`)                 minRank
			from
			  math_wmc_results r
			  join mathlog l ON r.math_inputhash = l.math_inputhash
			  join math_wmc_runs runs ON r.runId = runs.runId
			where
			  r.qId = $qId
			  and r.runId in (select runId from math_wmc_runs WHERE isDraft <> 1 and pageOnly <> 1)
			group by r.math_inputhash
			having min(rank) < 50
			order by count(distinct runs.userId) desc, min(rank) asc
			Limit 15",
			__METHOD__
		);
		$out->addWikiTextAsInterface( "== Most frequent results ==" );
		foreach ( $res as $hit ) {
			$out->addWikiTextAsInterface(
				"*<math>{$hit->rendering}</math>  was found by {$hit->cntUser} users in " .
				" {$hit->cntRun} runs with minimal rank of {$hit->minRank} \n"
			);
			$mo = new MathObject( $hit->rendering );
			$all = $mo->getAllOccurrences();
			foreach ( $all as  $occ ) {
				$out->addWikiTextAsInterface( '*' . $occ->printLink2Page( false ) );
			}
		}
	}

	private function printIndividualResults( int $qId ) {
		$out = $this->getOutput();
		$out->addWikiTextAsInterface( "== Individual results ==" );
		$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getReplicaDatabase();
		if ( !$dbr->tableExists( 'math_wmc_page_ranks', __METHOD__ ) ) {
			MathSearchUtils::createEvaluationTables();
		}
		$res = $dbr->select( 'math_wmc_page_ranks', '*', [ 'qId' => $qId ], __METHOD__ );
		foreach ( $res as $rank ) {
			$out->addWikiTextAsInterface( $rank->runId . ': ' . $rank->rank );
		}
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}
