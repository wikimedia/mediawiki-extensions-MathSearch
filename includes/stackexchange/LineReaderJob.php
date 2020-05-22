<?php

namespace MathSearch\StackExchange;

use MediaWiki\Logger\LoggerFactory;
use SimpleXMLElement;

class LineReaderJob extends \Job {
	public function __construct( $title, $params ) {
		parent::__construct( 'SeLineReader', $title, $params );
	}

	private static function getLog() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	/**
	 * Run the job
	 * @return bool Success
	 */
	public function run() {
		$filename = $this->params['fileName'];
		foreach ( $this->params['rows'] as $row ) {
			try {
				$reader = new Row( $row, $filename );
				$reader->processBody();
			}
			catch ( \Throwable $e ) {
				self::getLog()
					->error( "While processing\n{row}\n the following exception appeared:\n{e} ", [
						'row' => $row,
						'e' => $e,
					] );
				$xml = new SimpleXMLElement( '<row/>' );
				$rowTransposed = array_flip( $row );
				array_walk( $rowTransposed, [ $xml,'addAttribute' ] );
				file_put_contents( $this->params['errFile'], "{$xml->asXML()}\n", FILE_APPEND );

			}

		}

		return true;
	}

}
