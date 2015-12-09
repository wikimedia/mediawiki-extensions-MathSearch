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

require_once ( __DIR__ . '/../../../maintenance/Maintenance.php' );
class MathPerformance extends Maintenance {
	const RTI_CHUNK_SIZE = 10000;
	public $purge = false;
	/** @var boolean */
	private $verbose;
	/** @var DatabaseBase */
	public $dbw;
	/** @var DatabaseBase */
	private $db;
	/** @var MathRenderer */
	private $current;
	private $time = 0.0; // microtime( true );
	private $performance = array();
	private $renderingMode = 'mathml';

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Run math performance tests.';
		$this->addArg( 'action', 'Selects what should be done.', false );
		$this->addArg( 'shares', 'How many pieces should be used.', false );
		$this->addArg( 'share', 'Which piece should be used. Starting from 0.', false );
		$this->addOption( 'table', 'table to load the formulae from', false );
		$this->addOption( 'input', 'field that contains the input', false );
		$this->addOption( 'hash', 'field that contains the hash', false );
		$this->addOption( 'min', 'If set, processing is started at formula>min', false );
		$this->addOption( 'max', 'If set, processing is stopped at formula<=max', false );
		$this->addOption( 'output', 'The destination of the output defaults to stdout.', false );
		$this->addOption( 'verbose', 'If set, output for successful rendering will produced', false,
			false, 'v' );
	}

	/**
	 * Measures time in ms.
	 * In order to have a formula centric evaluation, we can not just the build in profiler
	 * @param string $category
	 *
	 * @return int
	 */
	private function time( $category = 'default' ) {
		global $wgMathDebug;
		$delta = ( microtime( true ) - $this->time ) * 1000;
		if ( isset( $this->performance[$category] ) ) {
			$this->performance[$category] += $delta;
		} else {
			$this->performance[$category] = $delta;
		}
		if ( $wgMathDebug ) {
			$this->db->insert( 'mathperformance', array(
				'math_inputhash'       => $this->current->getInputHash(),
				'mathperformance_name' => substr( $category, 0, 10 ),
				'mathperformance_time' => $delta,
				'mathperformance_mode' => MathHooks::mathModeToHashKey( $this->renderingMode )
			) );
		}
		$this->time = microtime( true );

		return (int)$delta;
	}


	public function execute() {
		global $wgMathValidModes;
		$this->dbw = wfGetDB( DB_MASTER );
		$this->db = wfGetDB( DB_MASTER );
		$wgMathValidModes[] = $this->renderingMode;
		$this->output( "Loaded.\n" );
		$this->time = microtime( true );
		if ( $this->getArg( 0, 'export' ) == 'export' ) {
			$this->renderFromTable();
		}
	}

	private function renderFromTable() {
		$min = $this->getOption( 'min', 0 );
		$max = $this->getOption( 'max', 0 );
		$options = array();
		if ( $max ) {
			$options['LIMIT'] = $max - $min;
			$options['OFFSET'] = $min;
		}
		$table = $this->getOption( 'table', 'mathoid' );
		$tex = $this->getOption( 'input', 'math_input' );
		$hash = $this->getOption( 'hash', 'math_inputhash' );
		$shares = $this->getArg( 1, false ); // 'shares'
		$share = $this->getArg( 2, 0 ); // 'share'
		if ( $shares ) {
			// echo "I'm share $share of $shares";
			$counts = $this->db->selectField( $table, 'count(*)' );
			$bucket = ceil( $counts / $shares );
			$min = $share * $bucket;
			$max = $min + $bucket;
			$options['LIMIT'] = $max - $min;
			$options['OFFSET'] = $min;
		}
		$formulae = $this->db->select(
			$table,
			array( $hash, $tex ),
			'',
			__METHOD__,
			$options
		);
		$out = array();
		foreach ( $formulae as $formula ) {
			$out[] = array( $hash => base64_encode( $formula->$hash ), $tex => $formula->$tex );
		}
		$output = $this->getOption( 'output', 'php://stdout' );
		file_put_contents( $output, json_encode( $out, JSON_PRETTY_PRINT ) );
	}
}

$maintClass = "MathPerformance";
/** @noinspection PhpIncludeInspection */
require_once ( RUN_MAINTENANCE_IF_MAIN );
