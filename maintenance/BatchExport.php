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

use UtfNormal\Utils;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Class BatchExport
 */
class BatchExport extends Maintenance {
	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Exports  submissions to a folder. \n Each run is named after the " .
			"following convention: \n \$userName-\$runName-\$runId.csv" );
		$this->addArg( 'dir', 'The output directory', true );
		$this->requireExtension( 'MathSearch' );
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
		$dbr = wfGetDB( DB_REPLICA );
		// runId INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
		// runName VARCHAR(45),
		// userId INT UNSIGNED,
		// isDraft TINYINT NOT NULL,
		$res = $dbr->select( 'math_wmc_runs', '*' );
		// TODO: Implement support for isDraft.
		foreach ( $res as $row ) {
			$user = User::newFromId( $row->userId );
			$username = $user->getName();
			$runName = preg_replace( "#/#", "_", Utils::escapeSingleString( $row->runName ) );
			$fn = "$dir/$username-$runName-{$row->runId}.csv";
			$this->output( "Export to file $fn.\n" );
			$fh = fopen( $fn, 'w' );
			fwrite( $fh, SpecialMathDownloadResult::run2CSV( $row->runId ) );
			fclose( $fh );
		}
	}
}

$maintClass = 'BatchExport';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
