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

use MediaWiki\Extension\MathSearch\Graph\Job\PageCreation;
use MediaWiki\Extension\MathSearch\Graph\Job\SetProfileType;
use MediaWiki\Extension\MathSearch\Graph\Map;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class ProfilePages extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Mass perform actions for profile pages." );
		$this->addArg( 'action', 'Action to be performed. ' . $this->printAvailableActions() );
		$this->addArg( 'type', 'Type of profile to be addressed. ' );
		$this->setBatchSize( 100000 );
		$this->addOption(
			'overwrite', 'Overwrite existing pages with the same name.', false, false, "o"
		);
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$profileTypeQIds = $this->getConfig()->get( 'MathString2QMap' )[
			$this->getConfig()->get( 'MathSearchPropertyProfileType' )];
		$type = $this->getArg( 'type' );
		if ( !isset( $profileTypeQIds[$type] ) ) {
			$this->error( "Unknown type of profile to be created.\n" );
			$this->error( $this->printProfileTypes() );
			return;
		}
		$jobOptions = [
			'overwrite' => $this->getOption( 'overwrite' )
		];
		$action = $this->getArg( 'action' );
		if ( $action === 'create' || $action === 'recreate' ) {
			$jobType = PageCreation::class;
			if ( $action === 'recreate' ) {
				$jobOptions['filter'] = '';
			}
		} elseif ( $action === 'load' ) {
			$jobType = SetProfileType::class;
			$jobOptions['qType'] = $profileTypeQIds[$type];
		} else {
			$this->error( "Unknown action to be performed.\n" );
			$this->error( $this->printAvailableActions() );
			return;
		}

		( new Map() )->scheduleJobs(
			\Closure::fromCallable( [ $this, 'output' ] ),
			$this->getOption( 'batchSize', $this->getBatchSize() ),
			$type,
			$jobType,
			$jobOptions
		);
	}

	public function printProfileTypes(): string {
		return "Available types are: " . implode( ', ', array_keys( $this->getConfig()->get( 'MathString2QMap' )[
			$this->getConfig()->get( 'MathSearchPropertyProfileType' )] ) ) . "\n";
	}

	public function printAvailableActions(): string {
		return "Available actions are: create, recreate, load, recreate.\n";
	}

}

$maintClass = ProfilePages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
