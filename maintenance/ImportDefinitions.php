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

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Class ImportDefinitions
 */
class ImportDefinitions extends Maintenance {
	private $dir;
	private $overwrite;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Batch imports definitions from a folder.' .
			" \n Processes mathosphere files that follow the naming convention: \n *.json" );
		$this->addArg( 'dir', 'The directory to be read', true );
		$this->addOption(
			'overwrite', 'Overwrite existing definitions with the same name.', false, false, "o"
		);
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$dbw = wfGetDB( DB_MASTER );
		$this->dir = $this->getArg( 0 );
		$this->overwrite = $this->getOption( 'overwrite' );
		if ( $this->overwrite ) {
			$this->output( "Loaded with option overwrite enabled .\n" );
		}
		if ( !is_dir( $this->dir ) ) {
			$this->output( "{$this->dir} is not a directory.\n" );
			exit( 1 );
		}
		$files = new GlobIterator( $this->dir . '/*.json' );
		foreach ( $files as $file ) {
			$handle = fopen( $file, 'r' );
			while ( !feof( $handle ) ) {
				$line = fgets( $handle );
				if ( preg_match( '/^[\s\[]*(?P<content>\{.*?\})[\s,\]]$/', $line, $matches ) ) {
					$oJson = json_decode( $matches['content'] );
					$title = Title::newFromText( $oJson->title );
					$dbw->begin( __METHOD__ );
					if ( $title->exists() ) {
						$revId = $title->getLatestRevID();
						foreach ( $oJson->relations as $relation ) {
							$dbw->insert( 'mathsemantics', [
								'revision_id' => $revId,
								'identifier'  => $relation->identifier,
								'noun'        => $relation->definition,
								'evidence'    => $relation->score
							] );
							$this->output( "{$title->getText()}: $relation->identifier is " .
								"$relation->definition certainty $relation->score\n" );
						}
					} else {
						$this->output( $title->getText() . " does not exist\n" );
					}
					$dbw->commit( __METHOD__ );
				}
			}
		}
	}
}

$maintClass = 'ImportDefinitions';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
