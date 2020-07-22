<?php
/**
 * Generates harvest files for the MathWebSearch Deamon.
 * Example: php CreateMathIndex.php ~/mws_harvest_files
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
 */
class GenerateWorkload extends IndexBase {
	private $id = 0;
	private $selectivity = PHP_INT_MAX;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Generates a workload of sample queries.' );
		$this->addOption( 'selectivity', 'Specifies the selectivity for each individual equation',
			false, true, 'S' );
		$this->addOption(
			'lastId',
			'Specifies to start the ID counter after the given id. ' .
				'For example \'-l 1\' would start with id 2.',
			false, true, 'l'
		);
		$this->addOption( 'overwrite', 'Overwrite existing draft queries ', false, false, "o" );
	}

	/**
	 * @param stdClass $row
	 *
	 * @return string
	 */
	protected function generateIndexString( $row ) {
		if ( mt_rand() <= $this->selectivity ) {
			$q = MathQueryObject::newQueryFromEquationRow( $row, ++$this->id );
			$q->saveToDatabase( $this->getOption( 'overwrite', false ) );
			$out = $q->exportTexDocument();
			if ( $out == false ) {
				echo 'problem with ' . var_export( $q, true ) . "\n";
				$out = '';
			}
			return $out;
		} else {
			return '';
		}
	}

	public function execute() {
		$i = 0;
		$inc = $this->getArg( 1, 100 );
		$this->id = $this->getOption( 'lastId', 0 );
		$sel = $this->getOption( 'selectivity', 0.1 );
		$this->selectivity = (int)( $sel * mt_getrandmax() );
		$db = wfGetDB( DB_REPLICA );
		echo "getting list of all equations from the database\n";
		$this->res =
			$db->select( [ 'mathindex' ],
				[ 'mathindex_revision_id', 'mathindex_anchor', 'mathindex_inputhash' ], true,
				__METHOD__, [
					'LIMIT' => $this->getOption( 'limit', (int)( 100 / $sel ) ),
					'ORDER BY' => 'mathindex_inputhash'
				] );
		do {
			$fn = $this->getArg( 0 ) . '/math' . sprintf( '%012d', $i ) . '.tex';
			$res = $this->wFile( $fn, $i, $inc );
			$i += $inc;
		} while ( $res );
		echo "last id used: {$this->id}\n";
		echo ( 'done' );
	}
}

$maintClass = 'GenerateWorkload';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
