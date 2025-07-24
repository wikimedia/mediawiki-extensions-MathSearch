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

use MediaWiki\Extension\MathSearch\Engine\BaseX;
use MediaWiki\Maintenance\Maintenance;

require_once __DIR__ . '/IndexBase.php';

/**
 * @author Moritz Schubotz
 */
class CreateBaseXMathTable extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Setup and seeds baseX math search database.' );
		$this->addOption( 'overwrite', 'Overwrite existing databases or users.' );
		$this->addDefaultParams();
	}

	public function execute() {
		global $wgMathSearchBaseXDatabaseName, $wgMathSearchBaseXRequestOptionsReadonly;
		$b = new BaseX();
		$dbs = iterator_to_array( $b->getDatabases() );
		if ( !$dbs ) {
			echo "ERROR: No BaseX Databases found.\n";
			echo "Please check your BaseX configuration.\n";
			echo "Check that basexhttp is running and the password matches your configuration.\n";
			echo "You can start basexhttp with the adminpassword 'admin' via:\n";
			echo "  basexhttp -c\"PASSWORD admin\"\n\n";
			echo "See https://www.mediawiki.org/wiki/Extension:MathSearch#Prerequisites for more information.\n";
			exit( 1 );
		}
		$db_exists = in_array( $wgMathSearchBaseXDatabaseName, $dbs );
		if ( $db_exists ) {
			echo "Database $wgMathSearchBaseXDatabaseName exists.\n";
		}
		$isOverwrite = $this->getOption( 'overwrite', false );
		if ( !$db_exists || $isOverwrite ) {
			echo "Creating database $wgMathSearchBaseXDatabaseName.\n";
			$res = $b->executeQuery( "db:create('$wgMathSearchBaseXDatabaseName')" );
			if ( !$res ) {
				echo "ERROR: Could not create database $wgMathSearchBaseXDatabaseName. Error:\n";
				echo $b->getContent();
				exit( 1 );
			}
		}
		// db setup complete
		$readUser = $wgMathSearchBaseXRequestOptionsReadonly['username'];
		$b->executeQuery( "user:list-details()[@name = '$readUser']" );
		if ( $b->getContent() === '' ) {
			echo "Warning: Could not find user $readUser.\n";
			if ( !$isOverwrite ) {
				echo "Please create user $readUser. Or use the option overwrite to have the user created.\n";
			}
			echo "Creating user $readUser.\n";
			$readPwd = $wgMathSearchBaseXRequestOptionsReadonly['password'];
			$b->executeQuery( "user:create('$readUser', '$readPwd', 'read', '$wgMathSearchBaseXDatabaseName')" );
		}
		$b->executeQuery(
			"<result>{count(//*)}</result>",
			$wgMathSearchBaseXDatabaseName,
			$wgMathSearchBaseXRequestOptionsReadonly );
		$xml = simplexml_load_string( $b->getContent() );
		if ( (string)$xml !== '0' ) {
			echo "Warning: Database $wgMathSearchBaseXDatabaseName is not empty.\n";
			echo "Exiting.\n";
			exit( 1 );
		}
	}

}

$maintClass = CreateBaseXMathTable::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
