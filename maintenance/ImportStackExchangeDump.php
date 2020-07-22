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

use MathSearch\StackExchange\DumpReader;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class ImportStackExchangeDump extends Maintenance {
	private $dir;
	private $overwrite;
	const EXPRECTED_FILES = [
		'Badges',
		'Comments',
		'Posts',
		'PostLinks',
		'Tags',
		'Users',
		'Votes',
	];

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Imports StackExchangeDump. \n" .
			"Processes XML files in the StackExchangeDump dump format." );
		$this->addArg( 'dir', 'The directory to be read', true );
		$this->addArg( 'dir', 'The directory to write error files', false );
		$this->requireExtension( 'MathSearch' );
		// $this->requireExtension('Wikibase');
	}

	public function execute() {
		$this->dir = $this->getArg( 0 );
		$this->overwrite = $this->getOption( 'overwrite' );
		$errPath = $this->getArg( 1, realpath( $this->dir ) );
		if ( !is_dir( $this->dir ) ) {
			$this->output( "{$this->dir} is not a directory.\n" );
			exit( 1 );
		}
		$files = new GlobIterator( $this->dir . '*.xml' );
		foreach ( $files as $file ) {
			print "Processing {$file->getFilename()}\n";
			$dr = new DumpReader( $file, $errPath );
			$dr->run();
		}
	}
}

$maintClass = 'ImportStackExchangeDump';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
