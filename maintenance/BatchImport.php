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

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class BatchImport extends Maintenance {

	/** @var string */
	private $dir;
	/** @var bool */
	private $overwrite;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Batch imports submissions from a folder. \n Processes CSV files " .
			"that follow the naming convention: \n \$userName-\$runName.csv" );
		$this->addArg( 'dir', 'The directory to be read', true );
		$this->addOption(
			'overwrite', 'Overwrite existing runs with the same name.', false, false, "o"
		);
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$this->dir = $this->getArg( 0 );
		$this->overwrite = $this->getOption( 'overwrite' );
		if ( $this->overwrite ) {
			$this->output( "Loaded with option overwrite enabled .\n" );
		}
		if ( !is_dir( $this->dir ) ) {
			$this->output( "{$this->dir} is not a directory.\n" );
			exit( 1 );
		}
		$files = new GlobIterator( $this->dir . '/*-*.csv' );
		foreach ( $files as $file ) {
			$fn = $file->getFilename();
			if ( preg_match( '/(?P<user>.*?)-(?P<runName>.*?)\\.csv/', $fn, $matches ) ) {
				$user = User::newFromName( $matches['user'] );
				if ( $user->getId() > 0 ) {
					$this->output( "Importing filename $fn for userId {$user->getId()}.\n" );
					$importer = new ImportCsv( $user );
					$result =
						$importer->execute( fopen( $file, 'r' ), $matches['runName'],
							$this->overwrite );
					foreach ( $importer->getWarnings() as $warning ) {
						$this->output( "warning: $warning \n" );
					}
					if ( $result !== true ) {
						$this->output( "$result\n" );
					} else {
						$this->output( "File $fn imported as {$importer->getRunId()} \n" );
					}
				} else {
					$this->output( "User {$matches['user']} is invalid. Skipping file $fn.\n" );
				}
			}
		}
	}
}

$maintClass = 'BatchImport';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
