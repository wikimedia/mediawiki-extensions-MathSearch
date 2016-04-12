<?php
/**
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @ingroup Maintenance
 */

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * TODO: Get rid of the workaround
 */
// @codingStandardsIgnoreStart
function createMySqlFunctionDropperClass() {
// @codingStandardsIgnoreEnd
	class MySqlFunctionDropper extends MysqlUpdater {
		/**
		 * @param $db
		 *
		 * @return MySqlFunctionDropper
		 */
		public static function newInstance( $db ) {
			return new MySqlFunctionDropper( $db, false, null );
		}

		/**
		 * Applies a SQL patch
		 *
		 * @param string $path       Path to the patch file
		 * @param bool   $isFullPath Whether to treat $path as a relative or not
		 * @param string $msg        Description of the patch
		 *
		 * @return bool False if patch is skipped.
		 */
		public function dropFunction( $path, $isFullPath = false, $msg = null ) {
			parent::applyPatch( $path, $isFullPath, $msg );
		}
	}
}

class QueryEval extends Maintenance {
	/** @type DatabaseUpdater*/
	private $dbu = null;
	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		// @codingStandardsIgnoreStart
		$this->mDescription = "Exports submissions to a folder. \n Each run is named after the following convention: \n \$userName-\$runName-\$runId.csv";
		// @codingStandardsIgnoreEnd
		$this->addArg( "dir", "The output directory", true );
	}
	private function addExtensionTable( $name, $folder = '' ) {
		if ( is_null( $this->dbu ) ) {
			$dbw = wfGetDB( DB_MASTER );
			$this->dbu = DatabaseUpdater::newForDB( $dbw );
		}
		$this->dbu->addExtensionTable( $name, __DIR__ . "/../db/wmc/${folder}${name}.sql" );
	}

	/**
	 * @param $row
	 *
	 * @return string
	 */
	/** @noinspection PhpExpressionResultUnusedInspection */
	private function createTopicTex( $row ) {
		$qId = $row->qId;
		$row->title = str_replace( [ 'π','ő' ], [ '$\\pi$', 'ö' ], $row->title );
		$tName = $row->qId. ': {\\wikiLink{' . $row->title .'}{' . $row->oldId. '}{'.$row->fId.'}}';
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'math_wmc_freq_hits',
			[ 'cntRun', 'cntUser' , 'links', 'minRank', 'rendering' ],
			[ 'qId' => $qId ] );
		$mostFrequent = "\\subsection*{Most frequent results}\n \\begin{enumerate}\n";
		foreach ( $res as $hit ) {
			$hit->rendering = str_replace( // TODO: preg_match replaces for by f\lor
				[ '\\or ','%','$\\begin{align}','\\end{align}$' ],
				[ '\\lor ', '\\%','\\begin{align}','\\end{align}' ], $hit->rendering );
			$mostFrequent .= "\\item {$hit->rendering} was found by {$hit->cntUser} users in ".
				" {$hit->cntRun} runs with minimal rank of {$hit->minRank}. \n".
				"For example in the context of the following pages: {$hit->links}\n";
		}
		$mostFrequent .= "\\end{enumerate}";
		if ( $row->qVarCount > 0 ) {
			$relevance = "  \\begin{relevance}[{$row->qVarCount}]
  $row->rendering
  \\end{relevance}";
		} else {
			$relevance = '';
		}
		$individualResults='';
		$res = $dbr->select( 'math_wmc_page_ranks', '*', [ 'qId'=>$row->qId ] );
		foreach ( $res as $rank ) {
			$individualResults .= $rank->runId . ': '.$rank->rank.'; ';
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
	}

	/**
	 * TODO: Replace with DBU call
	 */
	private function dropUDFs() {
		$dbw = wfGetDB( DB_MASTER );
		if ( !class_exists( "MySqlFunctionDropper" ) ) {
			createMySqlFunctionDropperClass();
		}
		$dbu = MySqlFunctionDropper::newInstance( $dbw );
		$dbu->dropFunction( __DIR__ . '/../db/wmc/math_wmc_udf_drop.sql', true,
			'drop math_wmc udfs' );
	}

	/**
	 *
	 */
	public function execute() {
		$dir = $this->getArg( 0 );
		if ( !is_dir( $dir ) ) {
			$this->output( "{$dir} is not a directory.\n" );
			exit( 1 );
		}
		MathSearchUtils::createEvaluationTables();
		$this->addExtensionTable( 'math_wmc_udf_create' );
		$this->dbu->doUpdates( [ "extensions" ] );
		$dbr = wfGetDB( DB_SLAVE );
		// runId INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
		// runName VARCHAR(45),
		// userId INT UNSIGNED,
		// isDraft TINYINT NOT NULL,
		$res = $dbr->select( "math_wmc_query_summary", '*' );
		$all = "";
		mb_internal_encoding( 'UTF-8' );
		mb_regex_encoding( "UTF-8" );
		foreach ( $res as $row ) {
			$all .= $this->createTopicTex( $row ) . "\n\n";
		}
		$fh =  fopen( $dir . '/all.tex', 'w' );
		fwrite( $fh, $all );
		fclose( $fh );
		echo "all done";
		$this->dropUDFs();
		exit( 0 );
	}
}

$maintClass = 'QueryEval';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
