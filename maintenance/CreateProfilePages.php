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

use MediaWiki\Extension\MathSearch\Graph\Map;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class CreateProfilePages extends Maintenance {
	private int $batch_size = 100000;
	private $overwrite;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Mass creates pages from the SPARQL endpoint " );
		$this->addArg( 'type', 'Type of profile to be created.', true, false );
		$this->addOption( 'batchSize',
			'Number of items to be retrieved per SPARQL query.', false, true, "b" );
		$this->addOption(
			'overwrite', 'Overwrite existing pages with the same name.', false, false, "o"
		);

		$this->requireExtension( 'MathSearch' );
		$this->requireExtension( 'LinkedWiki' );
	}

	public function execute() {
		global $wgMathProfileQueries;
		$type = $this->getArg( 'type' );
		if ( !isset( $wgMathProfileQueries[$type] ) ) {
			$this->error( "Unknown type of profile to be created.\n" );
			$this->error( "Available types are: " . implode( ', ', array_keys( $wgMathProfileQueries ) ) . "\n" );
			return;
		}

		if ( $this->overwrite ) {
			$this->output( "Loaded with option overwrite enabled .\n" );
		}
		( new Map() )->getJobs(
			\Closure::fromCallable( [ $this, 'output' ] ),
			$this->getOption( 'batchSize', $this->batch_size ),
			$type,
			PageCreationJob::class
		);
	}

}

$maintClass = CreateProfilePages::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
