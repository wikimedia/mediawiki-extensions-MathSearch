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

require_once __DIR__ . '/IndexBase.php';

/**
 * @author Moritz Schubotz
 *
 */
class CreateDB2MathTable extends IndexBase {
	private $statment;
	private $conn;
	private $time;

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Exports a db2 compatible math index table.';
		$this->addArg( 'truncate', 'If true, db2 math table is deleted before import', false );
	}

	/**
	 * @param stdClass $row
	 *
	 * @return string
	 */
	protected function generateIndexString( $row ) {
		$mo = MathObject::constructformpagerow( $row );
		$out = '"' . $mo->getMd5() . '"';
		$out .= ',"' . $mo->getTex() . '"';
		$out .= ',' . $row->mathindex_revision_id . '';
		$out .= ',' . $row->mathindex_anchor . '';
		$out .= ',"' . str_replace( [ '"', "\n" ], [ '"', ' ' ], $mo->getMathml() ) . '"';
		$res =
			db2_execute( $this->statment, [
				$mo->getMd5(),
				$mo->getTex(),
				$row->mathindex_revision_id,
				$row->mathindex_anchor,
				$mo->getMathml()
			] );
		if ( !$res ) {
			echo db2_stmt_errormsg();
		}
		return $out . "\n";
	}

	/**
	 * @param string $fn
	 * @param int $min
	 * @param int $inc
	 *
	 * @return bool
	 */
	protected function wFile( $fn, $min, $inc ) {
		$res = db2_commit( $this->conn );
		if ( $res ) {
			echo db2_stmt_errormsg();
		}
		$delta = microtime( true ) - $this->time;
		$this->time = microtime( true );
		echo 'took ' . number_format( $delta, 1 ) . "s \n";
		return parent::wFile( $fn, $min, $inc );
	}

	public function execute() {
		global $wgMathSearchDB2ConnStr;
		$this->time = microtime( true );
		$this->conn = db2_connect( $wgMathSearchDB2ConnStr, '', '' );
		if ( $this->conn ) {
			if ( $this->getOption( 'truncate', false ) ) {
				db2_exec( $this->conn, 'DROP TABLE "math"' );
				// @codingStandardsIgnoreStart
				db2_exec( $this->conn,
					'CREATE TABLE "math" ("math_md5" CHAR(32), "math_tex" VARCHAR(1000), "mathindex_revision_id" INTEGER, "mathindex_anchord" INTEGER, "math_mathml" XML)' );
				// @codingStandardsIgnoreEnd
			}
			$this->statment =
			// @codingStandardsIgnoreStart
				db2_prepare( $this->conn,
					'INSERT INTO "math" ("math_md5", "math_tex", "mathindex_revision_id", "mathindex_anchord", "math_mathml") VALUES(?, ?, ?, ?, ?)' );
			// @codingStandardsIgnoreEnd
			// db2_autocommit($this->conn , DB2_AUTOCOMMIT_OFF);
		}
		parent::execute();
	}
}

$maintClass = 'CreateDB2MathTable';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
