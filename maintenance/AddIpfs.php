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

class AddIpfs extends Maintenance {

	private function downloadPdf( string $url ): string {
		// Use MediaWiki's HttpRequestFactory to download the PDF
		$httpRequestFactory = $this->getServiceContainer()->getHttpRequestFactory();

		$request = $httpRequestFactory->create( $url, [ 'followRedirects' => true ] );
		$request->execute();
		$response = $request->getContent();
		if ( !$response ) {
			throw new Exception( 'Failed to download the PDF.' );
		}

		return $response;
	}

	private function uploadToIPFS( string $pdfData ): string {
		// Define the local IPFS API endpoint
		$ipfsApiUrl = 'http://127.0.0.1:5001/api/v0/add';

		// Use cURL to upload the PDF to IPFS
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $ipfsApiUrl );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		// Create a temporary file for the PDF
		$tempFile = tmpfile();
		fwrite( $tempFile, $pdfData );
		fseek( $tempFile, 0 );

		// Get the file's metadata
		$metaData = stream_get_meta_data( $tempFile );
		$tempFilePath = $metaData['uri'];

		// Prepare the file to be uploaded
		$file = new CURLFile( $tempFilePath, 'application/pdf', 'document.pdf' );
		$postFields = [ 'file' => $file ];

		// Attach the file to the request
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields );

		// Execute the request
		$response = curl_exec( $ch );
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		if ( $httpCode !== 200 || $response === false ) {
			die( 'Failed to upload to IPFS. HTTP Code: ' . $httpCode . '. Error: ' . curl_error( $ch ) );
		}

		curl_close( $ch );

		// Parse the IPFS response to get the CID
		$json = json_decode( $response, true );

		// Close and delete the temporary file
		fclose( $tempFile );

		// Return the CID
		return $json['Hash'];
	}

	private function pinToIPFS( string $cid ): string {
		// Define the IPFS API endpoint for pinning
		$ipfsPinApiUrl = 'http://127.0.0.1:5001/api/v0/pin/add?arg=' . $cid;

		// Use MediaWiki's HttpRequestFactory to pin the CID
		$httpRequestFactory = $this->getServiceContainer()->getHttpRequestFactory();

		// Create the request
		$request = $httpRequestFactory->create( $ipfsPinApiUrl, [ 'method' => 'POST' ] );

		// Execute the request
		$request->execute();

		// Get the content of the response
		$response = $request->getContent();

		if ( $response === false ) {
			throw new Exception( 'Failed to pin CID.' );
		}

		// Parse the IPFS response to confirm pinning
		$json = json_decode( $response, true );
		if ( isset( $json['Pins'] ) ) {
			return 'CID ' . $cid . ' successfully pinned on your local IPFS node.';
		} else {
			throw new Exception( 'Failed to pin CID: ' . $cid );
		}
	}

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Uploads a http URL to IPFS and returns the CID" );
		$this->addArg( 'url', 'The url to be downloaded.', true );
	}

	public function execute() {
		// Step 1: Download the PDF from the provided URL
		$pdfData = $this->downloadPdf( $this->getArg( 0 ) );

		// Step 2: Upload the PDF to IPFS and get the CID
		$cid = $this->uploadToIPFS( $pdfData );

		// Step 3: Pin the CID on your local IPFS node
		$pinStatus = $this->pinToIPFS( $cid );

		// Step 4: Return the CID and pin status
		var_dump( [
			'cid' => $cid,
			'pinStatus' => $pinStatus,
		] );
	}
}

$maintClass = AddIpfs::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
