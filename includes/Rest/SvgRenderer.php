<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use RuntimeException;

/**
 * Renders MathML to SVG with MathJax in a headless browser, the way Math's
 * FixNativeReferences does: QUnit steers the browser and the SVG comes back on
 * the log. swh:1:cnt:ccb5aca6bf0cda1d9d44116283f262511f9558cb;lines=101-144
 */
class SvgRenderer {

	private const string PENDING_FILE = 'mathsearch-svg-pending.json';
	private const string KEY = 'formula';

	private string $server;
	private string $scriptPath;
	private string $coreDir;

	public function __construct( string $server, string $scriptPath, string $coreDir ) {
		$this->server = $server;
		$this->scriptPath = $scriptPath;
		$this->coreDir = $coreDir;
	}

	private static function pendingPath(): string {
		return wfTempDir() . '/' . self::PENDING_FILE;
	}

	/**
	 * Package file callback of the QUnit module: the MathML waiting for a browser.
	 * @return array<string,string>
	 */
	public static function getPending(): array {
		$path = self::pendingPath();
		$pending = is_readable( $path ) ? json_decode( file_get_contents( $path ), true ) : null;
		return is_array( $pending ) ? $pending : [];
	}

	/** One browser at a time: the lock on the pending file covers the whole run. */
	public function render( string $mathml ): ?string {
		$handle = fopen( self::pendingPath(), 'c+' );
		if ( !$handle || !flock( $handle, LOCK_EX ) ) {
			throw new RuntimeException( 'Could not lock ' . self::pendingPath() );
		}
		try {
			ftruncate( $handle, 0 );
			fwrite( $handle, json_encode( [ self::KEY => $mathml ] ) );
			fflush( $handle );
			return self::parseLog( $this->runBrowser() )[self::KEY] ?? null;
		} finally {
			ftruncate( $handle, 0 );
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}

	private function runBrowser(): string {
		$env = getenv() + [
			'MW_SERVER' => $this->server,
			'MW_SCRIPT_PATH' => $this->scriptPath === '' ? '/' : $this->scriptPath,
		];
		$descriptors = [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.proc_open
		$process = proc_open(
			[ 'npm', 'run', 'qunit', '--', '--qunit-component=MathSearch' ],
			$descriptors, $pipes, $this->coreDir, $env
		);
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.is_resource
		if ( !is_resource( $process ) ) {
			throw new RuntimeException( 'Could not start npm' );
		}
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exitCode = proc_close( $process );
		if ( !preg_match( '/[1-9]\d* tests? completed/', $stdout ) ) {
			throw new RuntimeException(
				"npm run qunit exited with code $exitCode without a completed test: "
				. substr( $stderr ?: $stdout, -500 )
			);
		}
		return $stdout;
	}

	/**
	 * @param string $log Karma output
	 * @return array<string,string> SVG by key
	 */
	public static function parseLog( string $log ): array {
		$svgs = [];
		foreach ( explode( "\n", $log ) as $line ) {
			if ( !preg_match( "/^LOG: 'MATHSEARCH_SVG:(.*)'\$/", $line, $matches ) ) {
				continue;
			}
			$data = json_decode( $matches[1], true );
			if ( is_array( $data ) && is_string( $data['key'] ?? null ) && is_string( $data['svg'] ?? null ) ) {
				$svgs[$data['key']] = $data['svg'];
			}
		}
		return $svgs;
	}
}
