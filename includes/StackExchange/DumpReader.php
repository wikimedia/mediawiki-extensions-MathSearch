<?php

namespace MediaWiki\Extension\MathSearch\StackExchange;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

class DumpReader {

	/**
	 * @var XMLReader
	 */
	private $file;
	/**
	 * @var string
	 */
	private $fileName;
	/** @var string */
	private $errPath;
	/** @var int */
	private $part = 0;

	private static function getLog() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	/**
	 * @param \SplFileObject $file
	 * @param string $errPath
	 */
	public function __construct( $file, $errPath ) {
		$this->file = new XMLReader();
		XMLReader::open( $file->getRealPath() );
		$this->normalizeFilename( $file->getFilename() );
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
		$rows = [];
		$xml = $this->file;
		while ( $xml->read() ) {
			if ( $xml->name === 'row' && $xml->nodeType == XMLReader::ELEMENT ) {
				$attribs = [];
				if ( $xml->hasAttributes ) {
					while ( $xml->moveToNextAttribute() ) {
						$attribs[$xml->name] = $xml->value;
					}
					$rows[] = $attribs;
					if ( count( $rows ) >= $batchSize ) {
						$this->addJob( $rows );
						$rows = [];
					}
				}
			} elseif ( $xml->nodeType == XMLReader::ELEMENT ) {
				self::getLog()->info( "Skip element: {line}", [ 'line' => $xml->name ] );
			}
		}
		$this->addJob( $rows );
	}

	/**
	 * @param array $rows
	 */
	private function addJob( array $rows ) {
		$part = ++$this->part;
		$title = Title::newFromText( "SE reader '$this->fileName' part $part" );
		$job = new LineReaderJob( $title, [
			'rows' => $rows,
			'fileName' => $this->fileName,
			'errFile' => $this->errPath . "/$this->fileName-$part-err.xml",
		] );
		MediaWikiServices::getInstance()->getJobQueueGroup()->push( $job );
	}
}
