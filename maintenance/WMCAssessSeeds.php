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

use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class WMCAssessSeeds extends Maintenance {

	private $DEFAULT_ASSESSMENT = 2;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Automatically creates assessment for formulae and revisions ' .
			'that have been used to create the topics.' );
		$this->addArg( 'user', 'The user that is marked as assessor.', true );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$user = User::newFromName( $this->getArg( 0 ) );
		$uId = $user->getId();
		if ( $uId > 0 ) {
			$dbw = MediaWikiServices::getInstance()
				->getConnectionProvider()
				->getPrimaryDatabase();
			$this->output( "Insert formula assessments...\n" );
			$dbw->query( "INSERT IGNORE INTO math_wmc_assessed_formula "
				. "SELECT {$uId}, math_inputhash, qId, {$this->DEFAULT_ASSESSMENT} "
				. "FROM math_wmc_ref" );
			$this->output( "Inserted {$dbw->affectedRows()} formula assessments.\n" );
			$this->output( "Insert revision assessments...\n" );
			$dbw->query( "INSERT IGNORE INTO math_wmc_assessed_revision "
				. "SELECT {$uId}, oldId, qId, {$this->DEFAULT_ASSESSMENT} FROM math_wmc_ref" );
			$this->output( "Inserted {$dbw->affectedRows()} revision assessments.\n" );
		} else {
			$this->output( "User {$this->getArg( 0 )} is invalid.\n" );
		}
	}
}

$maintClass = WMCAssessSeeds::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
