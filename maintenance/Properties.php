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

use MediaWiki\Extension\MathSearch\Graph\Job\FetchIdsFromWd;
use MediaWiki\Extension\MathSearch\Graph\Job\NormalizeDoi;
use MediaWiki\Extension\MathSearch\Graph\Map;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class Properties extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Mass perform actions for Properties." );
		$this->addArg( 'action', 'Action to be performed. ' . $this->printAvailableActions() );
		$this->setBatchSize( 10000 );

		$this->requireExtension( 'MathSearch' );
		$this->requireExtension( 'LinkedWiki' );
	}

	public function execute() {
		$type = 'doi';
		$jobOptions = [];
		$action = $this->getArg( 'action' );
		if ( $action === 'case-normalize' ) {
			$jobType = NormalizeDoi::class;
		} elseif ( $action === 'fetch-wd' ) {
			$jobType = FetchIdsFromWd::class;
			$type = 'wd';
		} else {
			$this->error( "Unknown action to be performed.\n" );
			$this->error( $this->printAvailableActions() );
			return;
		}

		( new Map( null, $this->getBatchSize() ) )->getJobs(
			Closure::fromCallable( [ $this, 'output' ] ),
			$this->getOption( 'batchSize', $this->getBatchSize() ),
			$type,
			$jobType,
			$jobOptions
		);
	}

	public function printAvailableActions(): string {
		return "Available actions are: case-normalize, fetch-wd.\n";
	}

}

$maintClass = Properties::class;
require_once RUN_MAINTENANCE_IF_MAIN;
