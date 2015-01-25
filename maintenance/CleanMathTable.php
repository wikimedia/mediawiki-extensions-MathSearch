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

require_once( __DIR__ . '/../../../maintenance/Maintenance.php' );

/**
 * Class CleanMathTable
 */
class CleanMathTable extends Maintenance {
	const RTI_CHUNK_SIZE = 10;
	public $purge = false;

	/**
	 * @var DatabaseBase
	 */
	private $db;

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Outputs page text to stdout';
		$this->addOption( 'purge',
			'If set all formulae are rendered again from strech. (Very time consuming!)', false,
			false, 'f' );
	}

	/**
	 * The idea is basically to select the math elements that do not have a corresponding mathindex entry.
	 * Basically that means:
	 * <code>DELETE math FROM (`math` LEFT OUTER JOIN `mathindex` ON ( (`mathindex`.`mathindex_inputhash` = `math`.`math_inputhash`) )) WHERE mathindex_inputhash IS NULL </code>
	 */
	public function execute() {
		// FIXME: this does not work at all
		$this->purge = $this->getOption( 'purge', false );
		$this->db = wfGetDB( DB_MASTER );
		$this->db->query( 'DELETE math FROM (`math` LEFT OUTER JOIN `mathindex` ON ( (`mathindex`.`mathindex_inputhash` = `math`.`math_inputhash`) )) WHERE mathindex_inputhash IS NULL ' );
		$this->output( "Done.\n" );
	}
}

$maintClass = 'CleanMathTable';
/** @noinspection PhpIncludeInspection */
require_once( RUN_MAINTENANCE_IF_MAIN );
