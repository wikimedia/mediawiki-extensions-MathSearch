<?php

class ImportCsv {

	/**
	 * @var array
	 */
	private static $columnHeaders = [ 'queryId', 'formulaId' ];
	/**
	 * @var bool|int|string
	 */
	private $runId = false;
	/**
	 * @var string[]
	 */
	private $warnings = [];
	/**
	 * @var array[]
	 */
	private $results = [];
	/**
	 * @var bool[] Array mapping numeric qId values to either true (exists) or false (doesn't exist)
	 */
	private $validQIds = [];
	/**
	 * @var bool
	 */
	private $overwrite = false;
	/**
	 * @var User
	 */
	private $user;

	/**
	 * @param User $user
	 */
	function __construct( User $user ) {
		$this->user = $user;
	}

	/**
	 * @param resource $csvFile
	 * @param int|null $runId
	 * @param bool $overwrite
	 *
	 * @return string|true|null
	 */
	public function execute( $csvFile, $runId = null, $overwrite = false ) {
		$this->overwrite = $overwrite;
		$runId = $this->validateRunId( $runId );
		if ( $runId !== false ) {
			$success = $this->importFromFile( $csvFile );
			if ( $success == true ) {
				return $this->processInput();
			}
		} else {
			return "Error: Invalid runId.";
		}
	}

	/**
	 * @param string $run
	 * @return bool|int|string
	 */
	function validateRunId( $run ) {
		if ( $run == '' ) {
			return date( 'Y-m-d H:i:s (e)' );
		}
		$dbw = wfGetDB( DB_MASTER );
		$uID = $this->getUser()->getId();
		if ( is_int( $run ) ) {
			$runId = $dbw->selectField( 'math_wmc_runs', 'runId',
				[ 'isDraft' => true, 'userID' => $uID, 'runId' => $run ] );
		} else {
			$runId = $dbw->selectField( 'math_wmc_runs', 'runId',
				[ 'isDraft' => true, 'userID' => $uID, 'runName' => $run ] );
		}
		if ( !$runId ) {
			$exists = $dbw->selectField( 'math_wmc_runs', 'runId',
					[ 'userID' => $uID, 'runName' => $run ] );
			if ( !$exists ) {
				$success = $dbw->insert( 'math_wmc_runs',
						[ 'isDraft' => true, 'userID' => $uID, 'runName' => $run ] );
				if ( $success ) {
					$this->runId = $dbw->insertId();
					$this->warnings[] = wfMessage( 'math-wmc-RunAdded', $run, $this->runId )->text();
				} else {
					$this->runId = false;
					$this->warnings[] = wfMessage( 'math-wmc-RunAddError', $run )->text();
				}
			} else {
				$this->warnings[] = wfMessage( 'math-wmc-RunAddExist', $run, $exists )->text();
				$this->runId = false;
			}
		} else {
			$this->runId = $runId;
		}
		return $this->runId;
	}

	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @param User $user
	 */
	public function setUser( $user ) {
		$this->user = $user;
	}

	/**
	 * @param resource|null $csv_file
	 * @return null
	 * @throws Exception
	 */
	public function importFromFile( $csv_file ) {
		if ( $csv_file === null ) {
			return wfMessage( 'emptyfile' )->text();
		}

		$table = [];

		$line = fgetcsv( $csv_file );
		if ( $line === null ) {
			throw new Exception( "Problem processing the csv file." );
		}
		while ( $line !== false ) {
			array_push( $table, $line );
			$line = fgetcsv( $csv_file );
		}
		fclose( $csv_file );

		// Get rid of the "byte order mark", if it's there - this is
		// a three-character string sometimes put at the beginning
		// of files to indicate its encoding.
		// Code copied from:
		// http://www.dotvoid.com/2010/04/detecting-utf-bom-byte-order-mark/
		$byteOrderMark = pack( "CCC", 0xef, 0xbb, 0xbf );
		if ( strncmp( $table[0][0], $byteOrderMark, 3 ) == 0 ) {
			$table[0][0] = substr( $table[0][0], 3 );
			// If there were quotation marks around this value,
			// they didn't get removed, so remove them now.
			$table[0][0] = trim( $table[0][0], '"' );
		}
		return $this->importFromArray( $table );
	}

	/**
	 * @param array $table
	 * @return null|string
	 */
	public function importFromArray( $table ) {
		global $wgMathWmcMaxResults;

		// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName
		define( 'ImportPattern', '/math\.(\d+)\.(\d+)/' );

		// check header line
		$uploadedHeaders = $table[0];
		if ( $uploadedHeaders != self::$columnHeaders ) {
			return wfMessage( 'math-wmc-bad-header' )->text();
		}
		$rank = 0;
		$lastQueryID = 0;
		foreach ( $table as $i => $line ) {
			if ( $i == 0 ) {
				continue;
			}
			$pId = false;
			$eId = false;
			$fHash = false;
			$qId = trim( $line[0] );
			$fId = trim( $line[1] );
			$qValid = $this->isValidQId( $qId );
			if ( $qValid == false ) {
				$this->warnings[] = wfMessage( 'math-wmc-wrong-query-reference', $i, $qId )->text();
			}
			if ( preg_match( ImportPattern, $fId, $m ) ) {
				$pId = (int)$m[1];
				$eId = (int)$m[2];
				$fHash = $this->getInputHash( $pId, $eId );
				if ( $fHash == false ) {
					$this->warnings[] = wfMessage( 'math-wmc-wrong-formula-reference', $i, $pId, $eId )->text();
				}
			} else {
				$this->warnings[] = wfMessage( 'math-wmc-malformed-formula-reference', $i, $fId,
					ImportPattern )->text();
			}
			if ( $qValid === true && $fHash !== false ) {
				// a valid result has been submitted
				if ( $qId == $lastQueryID ) {
					$rank++;
				} else {
					$lastQueryID = $qId;
					$rank = 1;
				}
				if ( $rank <= $wgMathWmcMaxResults ) {
					$this->addValidatedResult( $qId, $pId, $eId, $fHash, $rank );
				} else {
					$this->warnings[] = wfMessage( 'math-wmc-too-many-results', $i, $qId, $fId, $rank,
						$wgMathWmcMaxResults )->text();
				}
			}
		}
		return true;
	}

	/**
	 * @param int $qId
	 * @return bool
	 */
	private function isValidQId( $qId ) {
		if ( array_key_exists( $qId, $this->validQIds ) ) {
			return $this->validQIds[$qId];
		}
		$dbr = wfGetDB( DB_REPLICA );
		if ( $dbr->selectField( 'math_wmc_ref', 'qId', [ 'qId' => $qId ] ) ) {
			$this->validQIds[$qId] = true;
			return true;
		} else {
			$this->validQIds[$qId] = false;
			return false;
		}
	}

	/**
	 * @param int $pId
	 * @param string $eId
	 * @return string|false
	 */
	private function getInputHash( $pId, $eId ) {
		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->selectField( 'mathindex', 'mathindex_inputhash',
			[ 'mathindex_revision_id' => $pId, 'mathindex_anchor' => $eId ] );
	}

	/**
	 * @param int $qId
	 * @param int $pId
	 * @param string $eId
	 * @param string $fHash
	 * @param int $rank
	 */
	private function addValidatedResult( $qId, $pId, $eId, $fHash, $rank ) {
		$this->results[] = [
			'runId' => $this->runId,
			'qId' => $qId,
			'oldId' => $pId,
			'fId' => $eId,
			'rank' => $rank,
			'math_inputhash' => $fHash
		];
	}

	/**
	 * @return string
	 */
	public static function getCsvColumnHeader() {
		return implode( ',', self::$columnHeaders );
	}

	/**
	 * @return true
	 */
	function processInput() {
		$this->deleteRun( $this->runId );
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin( __METHOD__ );
		foreach ( $this->results as $result ) {
			$dbw->insert( 'math_wmc_results', $result );
		}
		$dbw->commit( __METHOD__ );
		return true;
	}

	/**
	 * @param string $runID
	 */
	public function deleteRun( $runID ) {
		if ( $this->overwrite ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete( 'math_wmc_results', [ 'runId' => $runID ] );
		}
	}

	/**
	 * @return string[]
	 */
	public function getWarnings() {
		return $this->warnings;
	}

	/**
	 * @return bool
	 */
	public function isOverwrite() {
		return $this->overwrite;
	}

	/**
	 * @param bool $overwrite
	 */
	public function setOverwrite( $overwrite ) {
		$this->overwrite = $overwrite;
	}

	/**
	 * @return array[]
	 */
	public function getResults() {
		return $this->results;
	}

	/**
	 * @return int
	 */
	public function getRunId() {
		return $this->runId;
	}

}
