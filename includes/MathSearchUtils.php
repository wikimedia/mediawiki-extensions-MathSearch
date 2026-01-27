<?php

use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IResultWrapper;

class MathSearchUtils {

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
	) {
	}

	private function addExtensionTable( string $name, string $folder = '' ) {
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$sql = file_get_contents( __DIR__ . "/../db/wmc/{$folder}/{$name}.sql" );
		$dbw->query( $sql, __METHOD__ );
	}

	public function createEvaluationTables() {
		$this->addExtensionTable( 'math_wmc_formula_counts', 'topics' );
		$this->addExtensionTable( 'math_wmc_topics_easy', 'topics' );
		$this->addExtensionTable( 'math_wmc_topics_frequent', 'topics' );
		$this->addExtensionTable( 'math_wmc_topics_variable', 'topics' );
		$this->addExtensionTable( 'math_wmc_topics_hard', 'topics' );
		$this->addExtensionTable( 'math_wmc_results_pages' );
		$this->addExtensionTable( 'math_wmc_results_top' );
		$this->addExtensionTable( 'math_wmc_queries_top_dist' );
		$this->addExtensionTable( 'math_wmc_page_ranks' );
		$this->addExtensionTable( 'math_wmc_query_summary' );
	}

	/**
	 * @param array $cols
	 *
	 * @return string
	 */
	private static function getTableHead( array $cols ) {
		$out = "{| class=\"wikitable sortable\"\n|-\n! ";
		$out .= implode( ' !! ', $cols );
		return $out;
	}

	public static function dbRowToWikiTable( IResultWrapper $resultWrapper, array $heads ) {
		$out = self::getTableHead( $heads );
		foreach ( $resultWrapper as $row ) {
			$out .= "\n|-";
			foreach ( $row as $col ) {
				$out .= "\n| " . $col;
			}
		}
		return $out . "\n|}\n";
	}
}
