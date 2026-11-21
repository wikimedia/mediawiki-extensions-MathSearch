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

use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class AddIpfs extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Uploads a http URL to IPFS and returns the CID" );
		$this->addArg( 'url', 'The url to be downloaded.', true );
	}

	public function execute() {
		$url = $this->getArg( 0 );
		$content = $this->getServiceContainer()->getHttpRequestFactory()
			->get( $url, [ 'followRedirects' => true ], __METHOD__ );
		if ( !$content ) {
			$this->fatalError( "Failed to download $url." );
		}
		$file = tempnam( wfTempDir(), 'AddIpfs' );
		file_put_contents( $file, $content );
		try {
			$ipfs = $this->getServiceContainer()->getService( 'MathSearch.IpfsClient' );
			$this->output( $ipfs->add( $file, basename( parse_url( $url, PHP_URL_PATH ) ?: 'file' ) ) . "\n" );
		} finally {
			unlink( $file );
		}
	}
}

$maintClass = AddIpfs::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
