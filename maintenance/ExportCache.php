#!/usr/bin/env php
<?php
/**
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

class ExportMathCache extends Maintenance {

	private const DEFAULT_TABLE = 'mathoid';
	private const ERROR_CODE_TABLE_NAME = 1;
	private const ERROR_CODE_DB_ERROR = 2;
	private const ERROR_CODE_JSON = 3;

	private static $allowedTables = [ 'mathoid' , 'mathlatexml' ];
	private static $inputColumns = [
		'mathoid' => 'math_input' ,
		'mathlatexml' => 'math_inputtex'
	];

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Exports a json file that consists of the input hashes and ' .
			'the texvc input from the database cache.' );
		$this->addArg( 'table', "The math table to be used (mathoid or latexml).", false );
		$this->addOption(
			'offset', "If set the first n equations on the table are skipped", false, true, "o"
		);
		$this->addOption(
			'length', "If set the only n equations are exported processed", false, true, "l"
		);
		$this->addOption(
			'sort', 'If set the result is sorted according to the input', false, false, 's'
		);
		$this->requireExtension( 'MathSearch' );
	}

	/**
	 * @param string $table
	 * @param int $offset
	 * @param int $length
	 * @param bool $sort
	 * @return array|false
	 */
	private static function getMathTagsFromDatabase( $table, $offset, $length, $sort ) {
		$out = [];
		$dbr = wfGetDB( DB_REPLICA );
		$inputColumn = self::$inputColumns[ $table ];
		$options = [
			'OFFSET'   => $offset,
			'LIMIT'    => $length
		];
		if ( $sort === true ) {
			$options['ORDER BY'] = $inputColumn;
		}
		$res = $dbr->select(
			$table,
			[ 'math_inputhash', $inputColumn ],
			'',
			__METHOD__,
			$options );
		if ( $res === false ) {
			return false;
		}
		// Convert result wrapper to array
		foreach ( $res as $row ) {
			$out[] = [
				// the binary encoded input-hash is no valid json output
				'inputhash' => MathObject::hash2md5( $row->math_inputhash ),
				'input'     => $row->$inputColumn
			];
		}
		return $out;
	}

	public function execute() {
		$table = $this->getArg( 0, self::DEFAULT_TABLE );
		if ( !in_array( $table, self::$allowedTables ) ) {
			$this->error( "Error:  '$table' is not allowed.", self::ERROR_CODE_TABLE_NAME );
		}
		$offset = $this->getOption( 'offset', 0 );
		$length = $this->getOption( 'length', PHP_INT_MAX );
		$sort = $this->hasOption( 'sort' );
		$allEquations = self::getMathTagsFromDatabase( $table, $offset, $length, $sort );
		if ( !is_array( $allEquations ) ) {
			$this->error( "Could not get equations from table '$table'", self::ERROR_CODE_DB_ERROR );
		}
		$out = FormatJson::encode( $allEquations, true );
		if ( $out === false ) {
			$this->error( "Could not encode result as json string '$table'", self::ERROR_CODE_JSON );
		}
		$this->output( "$out\n" );
	}
}

$maintClass = ExportMathCache::class;
require_once RUN_MAINTENANCE_IF_MAIN;
