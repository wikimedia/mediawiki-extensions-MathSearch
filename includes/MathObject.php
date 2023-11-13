<?php

use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;

class MathObject extends MathMathML {

	// DEBUG VARIABLES
	// Available, if Math extension runs in debug mode ($wgMathDebug = true) only.
	public const MODE_2_USER_OPTION = [
		'native' => 8,
		'latexml' => 7,
		'mathml' => 5,
		'source' => 3
	];
	/** @var int LaTeXML return code (will be available in future Mathoid versions as well) */
	private $statusCode = 0;
	/** @var string|null Timestamp of the last modification of the database entry */
	private $timestamp;
	/** @var string messages generated during conversion of mathematical content */
	private $log = '';

	/** @var string */
	protected $postData = '';
	/** @var string */
	protected $anchorID = 0;
	/** @var int */
	protected $revisionID = 0;
	/** @var string|null */
	protected $index_timestamp = null;
	/** @var string|null */
	protected $mathTableName = null;
	/** @var int */
	protected $renderingTime = 0;

	public static function hash2md5( $hash ) {
		// TODO: make MathRenderer::dbHash2md5 public
		$dbr = wfGetDB( DB_REPLICA );
		$xhash = unpack( 'H32md5', $dbr->decodeBlob( $hash ) . "                " );
		return $xhash['md5'];
	}

	public static function findSimilarPages( $pid ): void {
		global $wgOut;
		$out = "";
		$dbr = wfGetDB( DB_REPLICA );
		try {
			$res = $dbr->select( 'mathpagesimilarity',
				[
					'pagesimilarity_A as A',
					'pagesimilarity_B as B',
					'pagesimilarity_Value as V'
				],
				"pagesimilarity_A=$pid OR pagesimilarity_B=$pid", __METHOD__,
				[ "ORDER BY" => 'V DESC', "LIMIT" => 10 ]
			);
			$revisionLookup = MediaWikiServices::getInstance()
				->getRevisionLookup();

			foreach ( $res as $row ) {
				if ( $row->A == $pid ) {
					$other = $row->B;
				} else {
					$other = $row->A;
				}
				$revLinkTarget = $revisionLookup->getRevisionById( $other )
					->getPageAsLinkTarget();
				$revTitle = Title::newFromLinkTarget( $revLinkTarget );
				$out .= '# [[' . $revTitle . ']] similarity ' .
					$row->V * 100 . "%\n";
				// .' ( pageid'.$other.'/'.$row->A.')' );
			}
			$wgOut->addWikiTextAsInterface( $out );
		} catch ( Exception $e ) {
			$wgOut->addHTML( "DatabaseProblem" );
		}
	}

	public static function cloneFromRenderer( MathRenderer $renderer ): MathObject {
		$instance = new self( $renderer->getUserInputTex() );
		$instance->setMathml( $renderer->getMathml() );
		$instance->setSvg( $renderer->getSvg() );
		$instance->setMode( $renderer->getMode() );
		$instance->setMathStyle( $renderer->getMathStyle() );
		if ( $renderer->rbi ) {
			$instance->setRestbaseInterface( $renderer->rbi );
		}
		$instance->setInputType( $renderer->getInputType() );
		return $instance;
	}

	/**
	 * @param int $pid
	 * @param string $eid
	 * @return self instance
	 */
	public static function constructformpage( $pid, $eid ) {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->selectRow(
			[ 'mathindex' ], self::dbIndexFieldsArray(), 'mathindex_revision_id = ' . $pid
			. ' AND mathindex_anchor= "' . $eid . '"' );
		return self::constructformpagerow( $res );
	}

	/**
	 * @param int $oldId
	 * @param string $eid
	 *
	 * @return self
	 */
	public static function newFromRevisionText( $oldId, $eid ): MathObject {
		$gen = MathIdGenerator::newFromRevisionId( $oldId );
		$tag = $gen->getTagFromId( $eid );
		if ( !$tag ) {
			throw new RuntimeException( "$eid not found in revision text $oldId" );
		}
		$mo =
			new self( $tag[MathIdGenerator::CONTENT_POS], $tag[MathIdGenerator::ATTRIB_POS] );
		$mo->setRevisionID( $oldId );
		return $mo;
	}

	/**
	 * @return string[]
	 */
	private static function dbIndexFieldsArray(): array {
		global $wgMathDebug;
		$in = [
			'mathindex_revision_id',
			'mathindex_anchor',
			'mathindex_inputhash'
		];
		if ( $wgMathDebug ) {
			$debug_in = [
				'mathindex_timestamp'
			];
			$in = array_merge( $in, $debug_in );
		}
		return $in;
	}

	/**
	 * @param stdClass $res
	 * @return bool|self
	 */
	public static function constructformpagerow( $res ) {
		global $wgMathDebug;
		if ( $res && $res->mathindex_revision_id > 0 ) {
			$instance = new static();
			$instance->setRevisionID( $res->mathindex_revision_id );
			$instance->setAnchorID( $res->mathindex_anchor );
			if ( $wgMathDebug && isset( $res->mathindex_timestamp ) ) {
				$instance->index_timestamp = $res->mathindex_timestamp;
			}
			$instance->inputHash = $res->mathindex_inputhash;
			$instance->readFromDatabase();
			return $instance;
		} else {
			return false;
		}
	}

	public static function extractMathTagsFromWikiText( string $wikiText ): array {
		$idGenerator = new MathIdGenerator( $wikiText );
		return $idGenerator->getMathTags();
	}

	public static function updateStatistics() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->query( 'TRUNCATE TABLE `mathvarstat`' );
		// phpcs:disable Generic.Files.LineLength
		$dbw->query( "INSERT INTO `mathvarstat` (`varstat_featurename` , `varstat_featuretype`, `varstat_featurecount`) "
			.
			"SELECT `mathobservation_featurename` , `mathobservation_featuretype` , count( * ) AS CNT "
			. "FROM `mathobservation` "
			. "JOIN mathindex ON `mathobservation_inputhash` = mathindex_inputhash "
			. "GROUP BY `mathobservation_featurename` , `mathobservation_featuretype` "
			. "ORDER BY CNT DESC" );
		$dbw->query( 'TRUNCATE TABLE `mathrevisionstat`' );
		$dbw->query( 'INSERT INTO `mathrevisionstat`(`revstat_featureid`,`revstat_revid`,`revstat_featurecount`) '
			. 'SELECT varstat_id, mathindex_revision_id, count(*) AS CNT FROM `mathobservation` '
			. 'JOIN mathindex ON `mathobservation_inputhash` = mathindex_inputhash '
			.
			'JOIN mathvarstat ON varstat_featurename = `mathobservation_featurename` AND varstat_featuretype = `mathobservation_featuretype` '
			.
			'GROUP BY `mathobservation_featurename`, `mathobservation_featuretype`, mathindex_revision_id ORDER BY CNT DESC' );
		// phpcs:enable Generic.Files.LineLength
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function setStatusCode( int $statusCode ): MathObject {
		$this->statusCode = $statusCode;
		return $this;
	}

	public function getTimestamp(): ?string {
		return $this->timestamp;
	}

	public function setTimestamp( string $timestamp ): MathObject {
		$this->timestamp = $timestamp;
		return $this;
	}

	public function getLog(): string {
		return $this->log;
	}

	public function setLog( string $log ): MathObject {
		$this->changed = true;
		$this->log = $log;
		return $this;
	}

	public function getIndexTimestamp(): ?string {
		return $this->index_timestamp;
	}

	public function getObservations( bool $update = true ): void {
		global $wgOut;
		$dbr = wfGetDB( DB_REPLICA );
		try {
			$res = $dbr->select( [ "mathobservation", "mathvarstat", 'mathrevisionstat' ],
				[
					"mathobservation_featurename",
					"mathobservation_featuretype",
					'varstat_featurecount',
					'revstat_featurecount',
					"count(*) as localcnt"
				],
				[
					"mathobservation_inputhash" => $this->getInputHash(),
					'varstat_featurename = mathobservation_featurename',
					'varstat_featuretype = mathobservation_featuretype',
					'revstat_revid'             => $this->getRevisionID(),
					'revstat_featureid = varstat_id'
				],
				__METHOD__, [
					'GROUP BY' => [
						'mathobservation_featurename',
						'mathobservation_featuretype',
						'varstat_featurecount',
						'revstat_featurecount',
					],
					'ORDER BY' => 'varstat_featurecount'
				]
			);
		} catch ( Exception $e ) {
			$wgOut->addHTML( "Database problem" . $e->getMessage() );
			return;
		}
		$wgOut->addWikiTextAsInterface( $res->numRows() . 'results' );
		if ( $res->numRows() == 0 ) {
			if ( $update ) {
				$this->updateObservations();
				$this->getObservations( false );
			} else {
				$wgOut->addWikiTextAsInterface(
					"no statistics present please run the maintenance script ExtractFeatures.php"
				);
			}
		}
		$wgOut->addWikiTextAsInterface( $res->numRows() . ' results' );
		if ( $res ) {
			foreach ( $res as $row ) {
				$featureName = $row->mathobservation_featurename;
				if ( bin2hex( $featureName ) == 'e281a2' ) {
					$featureName = 'invisibe-times';
				}
				$wgOut->addWikiTextAsInterface( '*' . $row->mathobservation_featuretype . ' <code>' .
					$featureName . '</code> (' . $row->localcnt . '/' .
					$row->pagestat_featurecount .
					"/" . $row->varstat_featurecount . ')' );
				$identifiers = $this->getNouns( $row->mathobservation_featurename );
				if ( $identifiers ) {
					foreach ( $identifiers as $identifier ) {
						$wgOut->addWikiTextAsInterface( '**' . $identifier->noun . '(' .
							$identifier->evidence . ')' );
					}
				} else {
					$wgOut->addWikiTextAsInterface( '** not found' );
				}
			}
		}
	}

	public function getInputHash(): string {
		if ( $this->inputHash ) {
			return $this->inputHash;
		} else {
			return parent::getInputHash();
		}
	}

	public function getRevisionID(): int {
		return $this->revisionID;
	}

	public function setRevisionID( int $ID ): void {
		$this->revisionID = $ID;
	}

	public function updateObservations( $dbw = null ) {
		$this->readFromDatabase();
		preg_match_all(
			"#<(mi|mo|mtext)( ([^>].*?))?>(.*?)(<!--.*-->)?</\\1>#u", $this->getMathml(), $rule,
			PREG_SET_ORDER
		);

		$dbw = $dbw ?: wfGetDB( DB_MASTER );

		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( "mathobservation",
			[ "mathobservation_inputhash" => $this->getInputHash() ] );
		LoggerFactory::getInstance(
			'MathSearch'
		)->warning( 'delete obervations for ' . bin2hex( $this->getInputHash() ) );
		foreach ( $rule as $feature ) {
			$dbw->insert( "mathobservation", [
				"mathobservation_inputhash"   => $this->getInputHash(),
				"mathobservation_featurename" => trim( $feature[4] ),
				"mathobservation_featuretype" => $feature[1],
			] );
			LoggerFactory::getInstance(
				'MathSearch'
			)->warning( 'insert observation for ' . bin2hex( $this->getInputHash() )
				. trim( $feature[4] ) );
		}
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * @param string $identifier
	 * @return bool|ResultWrapper
	 */
	public function getNouns( string $identifier ) {
		$dbr = wfGetDB( DB_REPLICA );
		$pageName = $this->getPageTitle();
		if ( $pageName === false ) {
			return false;
		}
		return $dbr->select( 'mathidentifier',
			[ 'noun', 'evidence' ],
			[ 'pageTitle' => $pageName, 'identifier' => $identifier ],
			__METHOD__,
			[ 'ORDER BY' => 'evidence DESC', 'LIMIT' => 5 ]
		);
	}

	public function getPageTitle() {
		$revisionRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionById( $this->getRevisionID() );
		if ( $revisionRecord ) {
			$linkTarget = $revisionRecord->getPageAsLinkTarget();
			$title = Title::newFromLinkTarget( $linkTarget );
			return (string)$title;
		} else {
			return false;
		}
	}

	/**
	 * Gets all occurences of the tex.
	 *
	 * @param bool $currentOnly
	 *
	 * @return self[]
	 */
	public function getAllOccurrences( bool $currentOnly = true ): array {
		$out = [];
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'mathindex', self::dbIndexFieldsArray(),
			[ 'mathindex_inputhash' => $this->getInputHash() ]
		);

		foreach ( $res as $row ) {
			$var = self::constructformpagerow( $row );
			if ( $var ) {
				if ( $currentOnly === false || $var->isCurrent() ) {
					array_push( $out, $var );
				}
			}
		}
		return $out;
	}

	/**
	 * @return bool
	 */
	public function isCurrent(): bool {
		$revisionRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionById( $this->revisionID );
		if ( $revisionRecord === null ) {
			return false;
		} else {
			return $revisionRecord->isCurrent();
		}
	}

	/**
	 * @param bool $hidePage
	 *
	 * @return string
	 */
	public function printLink2Page( bool $hidePage = true ): string {
		$pageString = $hidePage ? "" : $this->getPageTitle() . " ";
		$anchor = MathSearchHooks::generateMathAnchorString(
			$this->getRevisionID(), $this->getAnchorID()
		);
		return "[[{$this->getPageTitle()}{$anchor}|{$pageString}Eq: {$this->getAnchorID()}]]";
	}

	public function getAnchorID(): string {
		return $this->anchorID;
	}

	public function setAnchorID( string $id ) {
		$this->anchorID = $id;
	}

	/** @inheritDoc */
	public function render( $forceReRendering = false ) {
	}

	/** @inheritDoc */
	public function getSvg( $render = 'render' ) {
		if ( $render === 'force' ) {
			$md = new MathoidDriver( $this->getUserInputTex(), $this->getInputType() );
			return $md->getSvg();
		}
		return parent::getSvg( $render ); // TODO: Change the autogenerated stub
	}

	public function addIdentifierTitle( $arg ): string {
		// return '<mi>X</mi>';
		$attribs = preg_replace( '/title\s*=\s*"(.*)"/', '', $arg[2] );
		$content = $arg[4];
		$nouns = $this->getNouns( $content );
		$title = 'not set';
		if ( $nouns ) {
			foreach ( $nouns as $identifier ) {
				$title .= '**' . $identifier->noun . '(' . $identifier->evidence . ')';
			}
		} else {
			$title = '** not found';
		}
		return '<' . $arg[1] . " title=\"$title\"" . $attribs . '>' . $arg[4] . '</' . $arg[1] .
		'>';
	}

	/**
	 * @return null|Revision
	 */
	public function getRevision(): ?RevisionRecord {
		$revisionRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionById( $this->revisionId );
		// TODO replace this public method with one returning RevisionRecord instead
		if ( $revisionRecord ) {
			return new Revision( $revisionRecord );
		}
		return null;
	}

	public function getRelations(): array {
		$dbr = wfGetDB( DB_REPLICA );
		$selection = $dbr->select( 'mathsemantics', [ 'identifier', 'evidence', 'noun' ],
			[ 'revision_id' => $this->revisionID ], __METHOD__,
			[ 'ORDER BY' => 'evidence desc' ] );
		if ( $selection ) {
			$result = [];
			foreach ( $selection as $row ) {
				$key = $row->identifier;
				if ( !isset( $result[$key] ) ) {
					$result[$key] = [];
				}
				$result[$key][] = (object)[ 'definition' => $row->noun ];
			}
			return $result;
		} else {
			$m = new MathosphereDriver( $this->revisionID );
			if ( $m->analyze() ) {
				return $m->getRelations();
			} else {
				LoggerFactory::getInstance( 'MathSearch' )
					->error( 'Error contacting mathosphere.' );
				return [];
			}
		}
	}

	protected function getMathTableName(): string {
		return 'mathlog';
	}

	/**
	 * @return MathoidDriver|false
	 */
	public function getTexInfo() {
		$m = new MathoidDriver( $this->userInputTex );
		if ( $m->texvcInfo() ) {
			return $m;
		} else {
			return false;
		}
	}

	public function getWikiText(): string {
		$attributes = '';
		foreach ( $this->params as $key => $value ) {
			if ( $key ) {
				$attributes .= " $key=\"$value\"";
			} else {
				$attributes .= " $value";
			}
		}
		return "<math$attributes>{$this->userInputTex}</math>";
	}

	public function getMathMlAltText() {
		$mml = $this->getMathml();
		preg_match( '/<math.+alttext="(.*?)".*>/', $mml, $res );
		if ( count( $res ) ) {
			return $res[1];
		}
		return '';
	}

	public static function getSvgWidth( $svg ) {
		if ( preg_match( "/width=\"(.*?)(ex|px|em)?\"/", $svg, $matches ) ) {
			return $matches;
		}
		return 0;
	}

	public static function getSvgHeight( $svg ) {
		if ( preg_match( "/height=\"(.*?)(ex|px|em)?\"/", $svg, $matches ) ) {
			return $matches;
		}
		return 0;
	}

	/**
	 * @param MathRenderer $renderer
	 * @param float $factor
	 *
	 * @return string
	 */
	public static function getReSizedSvgLink( MathRenderer $renderer, $factor = 2 ): string {
		$width = self::getSvgWidth( $renderer->getSvg() );
		$width = $width[1] * $factor . $width[2];
		$height = self::getSvgHeight( $renderer->getSvg() );
		$height = $height[1] * $factor . $height[2];
		$reflector = new ReflectionObject( $renderer );
		$method = $reflector->getMethod( 'getFallbackImage' );
		$method->setAccessible( true );
		$fbi = $method->invoke( $renderer );
		$fbi = preg_replace( "/width: (.*?)(ex|px|em)/", "width: $width", $fbi );
		return preg_replace( "/height: (.*?)(ex|px|em)/", "height: $height", $fbi );
	}

	public function getRbi(): \MediaWiki\Extension\Math\MathRestbaseInterface {
		return $this->rbi;
	}

	/**
	 * @return string
	 */
	public function getPostData(): string {
		return $this->postData;
	}

	/**
	 * @param string $postData
	 */
	public function setPostData( $postData ) {
		$this->postData = $postData;
	}

	/**
	 * @return int time in ms
	 */
	public function getRenderingTime(): int {
		return $this->renderingTime;
	}

	/**
	 * @param int|float $renderingTime either in ms as int or seconds as float
	 */
	public function setRenderingTime( $renderingTime ) {
		if ( is_float( $renderingTime ) ) {
			$this->renderingTime = (int)( $renderingTime * 1000 );
		} elseif ( is_int( $renderingTime ) ) {
			$this->renderingTime = $renderingTime;
		} else {
			throw new MWException( __METHOD__ . ': does not support type ' . gettype( $renderingTime ) );
		}
	}

	/**
	 * Gets an array that matches the variables of the class to the debug database columns
	 * @return array
	 */
	protected function dbDebugOutArray(): array {
		return [
			'math_log' => $this->getLog(),
			'math_mode' => self::MODE_2_USER_OPTION[ $this->getMode() ],
			'math_post' => $this->getPostData(),
			'math_rederingtime' => $this->getRenderingTime(),
		];
	}

	public function writeToDatabase( $dbw = null ) {
		# Now save it back to the DB:
		if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
			return;
		}
		$outArray = $this->dbOutArray();
		$mathTableName = 'mathlog';
		$fname = __METHOD__;
		if ( $this->isInDatabase() ) {
			$this->debug( 'Update database entry' );
			$inputHash = $this->getInputHash();
			DeferredUpdates::addCallableUpdate( function () use (
				$dbw, $outArray, $inputHash, $mathTableName, $fname
			) {
				$dbw = $dbw ?: MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getPrimaryDatabase();

				$dbw->update( $mathTableName, $outArray,
					[ 'math_inputhash' => $inputHash ], $fname );
				$this->logger->debug(
					'Row updated after db transaction was idle: ' .
					var_export( $outArray, true ) . " to database" );
			} );
		} else {
			$this->storedInDatabase = true;
			$this->debug( 'Store new entry in database' );
			DeferredUpdates::addCallableUpdate( function () use (
				$dbw, $outArray, $mathTableName, $fname
			) {
				$dbw = $dbw ?: MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getPrimaryDatabase();
				$dbw->insert( $mathTableName, $outArray, $fname, [ 'IGNORE' ] );
				LoggerFactory::getInstance( 'Math' )->debug(
					'Row inserted after db transaction was idle {out}.',
					[
						'out' => var_export( $outArray, true ),
					]
				);
				if ( $dbw->affectedRows() == 0 ) {
					// That's the price for the delayed update.
					$this->logger->warning(
						'Entry could not be written. Might be changed in between.' );
				}
			} );
		}
	}

	public function readFromDatabase(): bool {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
		$rpage = $dbr->selectRow( 'mathlog',
			$this->dbInArray(),
			[ 'math_inputhash' => $this->getInputHash() ],
			__METHOD__ );
		if ( $rpage !== false ) {
			$this->initializeFromDatabaseRow( $rpage );
			$this->storedInDatabase = true;
			return true;
		} else {
			# Missing from the database and/or the render cache
			$this->storedInDatabase = false;
			return false;
		}
	}

	protected function dbInArray() {
		$out = MathRenderer::dbInArray();
		$out = array_diff( $out, [ 'math_inputtex' ] );
		$out[] = 'math_input';
		return $out;
	}

	protected function dbOutArray() {
		$out = MathRenderer::dbOutArray();
		$out['math_input'] = $out['math_inputtex'];
		unset( $out['math_inputtex'] );
		$out += $this->dbDebugOutArray();
		return $out;
	}

	/**
	 * Reads the values from the database but does not overwrite set values with empty values
	 * @param stdClass $rpage (a database row)
	 */
	protected function initializeFromDatabaseRow( $rpage ) {
		$this->inputHash = $rpage->math_inputhash; // MUST NOT BE NULL
		$this->md5 = '';
		if ( !empty( $rpage->math_mathml ) ) {
			$this->mathml = $rpage->math_mathml;
		}
		if ( !empty( $rpage->math_input ) ) {
			// in the current database the field is probably not set.
			$this->userInputTex = $rpage->math_input;
		}
		if ( !empty( $rpage->math_tex ) ) {
			$this->tex = $rpage->math_tex;
		}
		if ( !empty( $rpage->math_svg ) ) {
			$this->svg = $rpage->math_svg;
		}
		$this->changed = false;
	}
}
