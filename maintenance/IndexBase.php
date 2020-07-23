<?php
/**
 * Generates harvest files for the MathWebSearch Daemon.
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

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

use Wikimedia\Rdbms\IResultWrapper;

/**
 * @author Moritz Schubotz
 *
 */
abstract class IndexBase extends Maintenance {
	/** @var IResultWrapper $res */
	protected $res;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Exports data' );
		$this->addArg( 'dir', 'The directory where the harvest files go to.' );
		$this->addArg( 'ffmax', 'The maximal number of formula per file.', false );
		$this->addArg( 'min', 'If set processing is started at the page with rank(pageID)>min',
			false );
		$this->addArg( 'max', 'If set processing is stopped at the page with rank(pageID)<=max',
			false );
		$this->addOption( 'limit', 'The maximal number of database entries to be considered', false,
			true, "L" );
		$this->requireExtension( 'MathSearch' );
	}

	/**
	 * @param stdClass $row
	 *
	 * @return string
	 */
	abstract protected function generateIndexString( $row );

	/**
	 * @param string $fn
	 * @param int $min
	 * @param int $inc
	 *
	 * @return bool
	 */
	protected function wFile( $fn, $min, $inc ) {
		$out = $this->getHead();
		$max = min( $min + $inc, $this->res->numRows() );
		for ( $i = $min; $i < $max; $i++ ) {
			$this->res->seek( $i );
			$out .= $this->generateIndexString( $this->res->fetchObject() );
			restore_error_handler();
		}
		$out .= "\n" . $this->getFooter();
		$fh = fopen( $fn, 'w' );
		// echo $out;
		// die ("test");
		fwrite( $fh, $out );
		fclose( $fh );
		echo "written file $fn with entries($min ... $max)\n";
		if ( $max < $this->res->numRows() - 1 ) {
			return true;
		} else {
			return false;
		}
	}

	public function execute() {
		libxml_use_internal_errors( true );
		$i = 0;
		$inc = $this->getArg( 1, 100 );
		$db = wfGetDB( DB_REPLICA );
		echo "getting list of all equations from the database\n";
		$this->res =
			$db->select( [ 'mathindex', 'mathlatexml' ], [
					'mathindex_revision_id',
					'mathindex_anchor',
					'math_mathml',
					'math_inputhash',
					'mathindex_inputhash'
			], [
					'math_inputhash = mathindex_inputhash',
					'mathindex_revision_id >= ' . $this->getArg( 2, 0 ),
					'mathindex_revision_id <= ' . $this->getArg( 3, PHP_INT_MAX )
			], __METHOD__, [
					'LIMIT' => $this->getOption( 'limit', PHP_INT_MAX ),
					'ORDER BY' => 'mathindex_revision_id'
			] );
		echo "write " . $this->res->numRows() . " results to index\n";
		$dir = $this->getArg( 0 );
		if ( !file_exists( $dir ) ) {
			mkdir( $dir, '0755', true );
		}
		do {
			$fn = $dir . '/math' . sprintf( '%012d', $i ) . '.xml';
			$res = $this->wFile( $fn, $i, $inc );
			$i += $inc;
		} while ( $res );
		echo ( 'done' );
	}

	/**
	 * @return string
	 */
	protected function getHead() {
		return '';
	}

	/**
	 * @return string
	 */
	protected function getFooter() {
		return '';
	}
}
