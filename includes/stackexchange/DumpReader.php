<?php

namespace MathSearch\StackExchange;

use MediaWiki\Logger\LoggerFactory;
use Title;

class DumpReader {
	private $file;
	/**
	 * @var string
	 */
	private $fileName;
	private $errPath;

	private static function getLog() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	/**
	 * DumpReader constructor.
	 * @param resource $file
	 */
	public function __construct( $file, $errPath ) {
		$this->normalizeFilename( $file->getFilename() );
		$this->file = fopen( $file, 'r' );
		$this->errPath = $errPath;
	}

	private function normalizeFilename( $fileName ) {
		// some posts file from arq20 math task were modified with additional version
		// information by appending either .V1.0 or _V1_0
		$fileparts = preg_split( "/[._]/", $fileName );
		$normalized_fn = strtolower( $fileparts[0] );
		$this->fileName = $normalized_fn;
		self::getLog()->debug( "'$fileName' is normalized to '$normalized_fn'." );
	}

	public function run() {
		$batchSize = 1000;
		$jobs = [];
		$rows = [];
		while ( !feof( $this->file ) ) {
			$line = trim( fgets( $this->file ) );
			if ( strpos( $line, '<row' ) === 0 ) {
				$rows [] = $line;
				if ( count( $rows ) >= $batchSize ) {
					$this->addJob( $jobs, $rows );
					$rows = [];
				}
			} else {
				self::getLog()->info( "Skip line: {line}", [ 'line' => $line ] );
			}
		}
		$this->addJob( $jobs, $rows );
		\JobQueueGroup::singleton()->push( $jobs );
	}

	/**
	 * @param array &$jobs
	 * @param array $rows
	 */
	private function addJob( array &$jobs, array $rows ) {
		$part = count( $jobs ) + 1;
		$title = Title::newFromText( "SE reader '$this->fileName' part $part" );
		$jobs[] = new LineReaderJob( $title, [
			'rows' => $rows,
			'fileName' => $this->fileName,
			'errFile' => $this->errPath . "/$this->fileName-$part-err.xml",
		] );
	}
}
