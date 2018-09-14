<?php
/**
 * Created by PhpStorm.
 * User: Moritz
 * Date: 03.01.2015
 * Time: 17:39
 */

class MathSearchUtils {
	private static function addExtensionTable( $name, $folder = '' ) {
		$dbw = wfGetDB( DB_MASTER );
		$sql = file_get_contents( __DIR__ . "/../db/wmc/${folder}/${name}.sql" );
		$dbw->query( $sql );
	}

	public static function createEvaluationTables() {
		self::addExtensionTable( 'math_wmc_formula_counts', 'topics' );
		self::addExtensionTable( 'math_wmc_topics_easy', 'topics' );
		self::addExtensionTable( 'math_wmc_topics_frequent', 'topics' );
		self::addExtensionTable( 'math_wmc_topics_variable', 'topics' );
		self::addExtensionTable( 'math_wmc_topics_hard', 'topics' );
		self::addExtensionTable( 'math_wmc_results_pages' );
		self::addExtensionTable( 'math_wmc_results_top' );
		self::addExtensionTable( 'math_wmc_queries_top_dist' );
		self::addExtensionTable( 'math_wmc_page_ranks' );
		self::addExtensionTable( 'math_wmc_query_summary' );
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

	static function dbRowToWikiTable( ResultWrapper $resultWrapper, array $heads ) {
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
