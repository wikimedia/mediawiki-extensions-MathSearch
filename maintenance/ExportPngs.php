#!/usr/bin/env php
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

class ExportPngs extends Maintenance {
	const DEFAULT_TABLE = 'mathlatexml';
	const ERROR_CODE_TABLE_NAME = 1;
	const ERROR_CODE_DB_ERROR = 2;
	const ERROR_CODE_JSON = 3;
	private static $allowedTables = [ 'mathoid', 'mathlatexml' ];
	private static $inputColumns = [
		'mathoid' => 'math_input',
		'mathlatexml' => 'math_inputtex'
	];

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Exports png files for comparison to a directory.';
		$this->addArg( 'folder', 'Existing directory where the PNG files will be created', true );
		$this->addArg( 'table', "The math table to be used (mathoid or latexml).", false );
		$this->addOption( 'offset', "If set the first n equations on the table are skipped", false,
			true, "o" );
		$this->addOption( 'length', "If set the only n equations are exported processed", false,
			true, "l" );
		$this->addOption( 'sort', 'If set the result is sorted according to the input', false,
			false, 's' );
	}

	/**
	 * @param $folder
	 * @param $input
	 */
	private static function processImage( $folder, $input ) {
		$texvc = new MathTexvc( $input );
		$texvc->render();
		$mathML = new MathMathML( $input );
		$md5 = $mathML->getMd5();
		$path = self::makePath( $folder, $md5 );
		file_put_contents( "$path/old.png", $texvc->getPng() );
		if ( $mathML->render() ) {
			$o = MathObject::cloneFromRenderer( $mathML );
			file_put_contents( "$path/new.png", $o->getPng() );
			file_put_contents( "$path/new.mml", $o->getMathml() );
			file_put_contents( "$path/new.svg", $o->getSvg() );
			file_put_contents( "$path/tex.tex", $o->getUserInputTex() );
		} else {
			echo 'ERROR' . $mathML->getLastError();
			var_export( $mathML );
		}
	}

	/**
	 * @param $folder
	 * @param $md5
	 * @return string
	 */
	private static function makePath( $folder, $md5 ) {
		$subPath = join( '/', str_split( substr( $md5, 0, 3 ) ) );
		$path = $folder . '/' . $subPath . '/' . $md5;
		mkdir( $path, '0755', true );
		return $path;
	}

	/**
	 * @param $folder
	 * @param $table
	 * @param $offset
	 * @param $length
	 * @param $sort
	 * @return bool|ResultWrapper
	 */
	private static function getMathTagsFromDatabase( $folder, $table, $offset, $length, $sort ) {
		$out = [ ];
		$dbr = wfGetDB( DB_SLAVE );
		$inputColumn = self::$inputColumns[$table];
		$options = [
			'OFFSET' => $offset,
			'LIMIT' => $length
		];
		if ( $sort === true ) {
			$options['ORDER BY'] = $inputColumn;
		}
		$res =
			$dbr->select( [ 'm' => 'math', 'l' => $table ], [ 'm.math_inputhash', $inputColumn ],
				[ 'm.math_inputhash = l.math_inputhash' ], __METHOD__, $options );
		if ( $res === false ) {
			return false;
		}
		// Convert result wrapper to array
		foreach ( $res as $row ) {
			$input = $row->$inputColumn;
			self::processImage( $folder, $input );
		}
	}

	public function execute() {
		$folder = $this->getArg( 0, self::DEFAULT_TABLE );
		$table = $this->getArg( 1, self::DEFAULT_TABLE );
		if ( !in_array( $table, self::$allowedTables ) ) {
			$this->error( "Error:  '$table' is not allowed.", self::ERROR_CODE_TABLE_NAME );
		}
		$offset = $this->getOption( 'offset', 0 );
		$length = $this->getOption( 'length', PHP_INT_MAX );
		$sort = $this->hasOption( 'sort' );
		self::getMathTagsFromDatabase( $folder, $table, $offset, $length, $sort );
	}
}

$maintClass = "ExportPngs";
require_once RUN_MAINTENANCE_IF_MAIN;
