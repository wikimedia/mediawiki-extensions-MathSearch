<?php
/**
 * Created by PhpStorm.
 * User: Moritz
 * Date: 03.01.2015
 * Time: 17:39
 */

class MathSearchUtils {
	/**
	 * @param array $cols
	 *
	 * @return string
	 */
	private static function getTableHead( array $cols ){
		$out = "{| class=\"wikitable sortable\"\n|-\n! ";
		$out .= implode( ' !! ', $cols );
		return $out;
	}

	static function dbRowToWikiTable(ResultWrapper $resultWrapper, array $heads) {
		$out = self::getTableHead($heads);
		foreach( $resultWrapper as $row ){
			$out .= "\n|-";
			foreach($row as $col){
				$out .= "\n| ". $col ;
			}
		}
		return $out . "\n|}\n";
	}
}