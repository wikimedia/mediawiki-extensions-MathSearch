<?php
use MediaWiki\Logger\LoggerFactory;

/**
 * Class MathObject
 */
class MathObject extends MathMathML {
	// DEBUG VARIABLES
	// Available, if Math extension runs in debug mode ($wgMathDebug = true) only.
	/** @var int LaTeXML return code (will be available in future Mathoid versions as well) */
	protected $statusCode = 0;
	/** @var timestamp of the last modification of the database entry */
	protected $timestamp;
	/** @var log messages generated during conversion of mathematical content */
	protected $log = '';
	protected $anchorID = 0;
	protected $revisionID = 0;
	protected $index_timestamp = null;
	protected $dbLoadTime = 0;
	protected $mathTableName = null;

	public static function hash2md5( $hash ) {
		// TODO: make MathRenderer::dbHash2md5 public
		$dbr = wfGetDB( DB_REPLICA );
		$xhash = unpack( 'H32md5', $dbr->decodeBlob( $hash ) . "                " );
		return $xhash['md5'];
	}

	public static function findSimilarPages( $pid ) {
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
			foreach ( $res as $row ) {
				if ( $row->A == $pid ) {
					$other = $row->B;
				} else {
					$other = $row->A;
				}
				$out .= '# [[' . Revision::newFromId( $other )->getTitle() . ']] similarity ' .
					$row->V * 100 . "%\n";
				// .' ( pageid'.$other.'/'.$row->A.')' );
			}
			$wgOut->addWikiText( $out );
		}
		catch ( Exception $e ) {
			return "DatabaseProblem";
		}
	}

	public static function cloneFromRenderer( MathRenderer $renderer ) {
		$instance = new MathObject( $renderer->getUserInputTex() );
		$instance->setMathml( $renderer->getMathml() );
		$instance->setSvg( $renderer->getSvg() );
		$instance->setMode( $renderer->getMode() );
		$instance->setMathStyle( $renderer->getMathStyle() );
		$instance->setRestbaseInterface( $renderer->rbi );
		$instance->setInputType( $renderer->getInputType() );
		return $instance;
	}

	/**
	 *
	 * @param int $pid
	 * @param int $eid
	 * @return self instance
	 */
	public static function constructformpage( $pid, $eid ) {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->selectRow(
			[ 'mathindex' ], self::dbIndexFieldsArray(), 'mathindex_revision_id = ' . $pid
			. ' AND mathindex_anchor= "' . $eid . '"' );
		$o = self::constructformpagerow( $res );
		return $o;
	}

	public static function newFromRevisionText( $oldId, $eid ) {
		$gen = MathIdGenerator::newFromRevisionId( $oldId );
		$tag = $gen->getTagFromId( $eid );
		if ( !$tag ) {
			throw new MWException( "$eid not found in revision text $oldId" );
		}
		$mo =
			new MathObject( $tag[MathIdGenerator::CONTENT_POS], $tag[MathIdGenerator::ATTRIB_POS] );
		$mo->setRevisionID( $oldId );
		return $mo;
	}

	/**
	 * @return array
	 */
	private static function dbIndexFieldsArray() {
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
	 *
	 * @global boolean $wgMathDebug
	 * @param stdClass $res
	 * @return bool|\self
	 */
	public static function constructformpagerow( $res ) {
		global $wgMathDebug;
		if ( $res && $res->mathindex_revision_id > 0 ) {
			$class = get_called_class();
			/** @type MathObject $instance */
			$instance = new $class;
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

	/**
	 * @param string $wikiText
	 * @return mixed
	 */
	public static function extractMathTagsFromWikiText( $wikiText ) {
		$idGenerator = new MathIdGenerator( $wikiText );
		return $idGenerator->getMathTags();
	}

	public static function updateStatistics() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->query( 'TRUNCATE TABLE `mathvarstat`' );
		// @codingStandardsIgnoreStart
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
		// @codingStandardsIgnoreEnd
	}

	/**
	 * @return int
	 */
	public function getStatusCode() {
		return $this->statusCode;
	}

	/**
	 * @param int $statusCode
	 * @return MathObject
	 */
	public function setStatusCode( $statusCode ) {
		$this->statusCode = $statusCode;
		return $this;
	}

	/**
	 * @return timestamp
	 */
	public function getTimestamp() {
		return $this->timestamp;
	}

	/**
	 * @param timestamp $timestamp
	 * @return MathObject
	 */
	public function setTimestamp( $timestamp ) {
		$this->timestamp = $timestamp;
		return $this;
	}

	/**
	 * @return log
	 */
	public function getLog() {
		return $this->log;
	}

	/**
	 * @param log $log
	 * @return MathObject
	 */
	public function setLog( $log ) {
		$this->log = $log;
		return $this;
	}

	public function getIndexTimestamp() {
		return $this->index_timestamp;
	}

	public function getObservations( $update = true ) {
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
					'GROUP BY' => 'mathobservation_featurename',
					'ORDER BY' => 'varstat_featurecount'
				]
			);
		}
		catch ( Exception $e ) {
			return "Database problem";
		}
		$wgOut->addWikiText( $res->numRows() . 'results' );
		if ( $res->numRows() == 0 ) {
			if ( $update ) {
				$this->updateObservations();
				$this->getObservations( false );
			} else {
				$wgOut->addWikiText(
					"no statistics present please run the maintenance script ExtractFeatures.php"
				);
			}
		}
		$wgOut->addWikiText( $res->numRows() . ' results' );
		if ( $res ) {
			foreach ( $res as $row ) {
				$featureName = utf8_decode( $row->mathobservation_featurename );
				if ( bin2hex( $featureName ) == 'e281a2' ) {
					$featureName = 'invisibe-times';
				}
				$wgOut->addWikiText( '*' . $row->mathobservation_featuretype . ' <code>' .
					$featureName . '</code> (' . $row->localcnt . '/' .
					$row->pagestat_featurecount .
					"/" . $row->varstat_featurecount . ')' );
				$identifiers = $this->getNouns( $row->mathobservation_featurename );
				if ( $identifiers ) {
					foreach ( $identifiers as $identifier ) {
						$wgOut->addWikiText( '**' . $identifier->noun . '(' .
							$identifier->evidence . ')' );
					}
				} else {
					$wgOut->addWikiText( '** not found' );
				}
			}
		}
	}

	public function getInputHash() {
		if ( $this->inputHash ) {
			return $this->inputHash;
		} else {
			return parent::getInputHash();
		}
	}

	public function getRevisionID() {
		return $this->revisionID;
	}

	public function setRevisionID( $ID ) {
		$this->revisionID = $ID;
	}

	public function updateObservations( $dbw = null ) {
		$this->readFromDatabase();
		preg_match_all(
			"#<(mi|mo|mtext)( ([^>].*?))?>(.*?)</\\1>#u", $this->getMathml(), $rule, PREG_SET_ORDER
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
				"mathobservation_featurename" => utf8_encode( trim( $feature[4] ) ),
				"mathobservation_featuretype" => utf8_encode( $feature[1] ),
			] );
			LoggerFactory::getInstance(
				'MathSearch'
			)->warning( 'insert observation for ' . bin2hex( $this->getInputHash() )
				. utf8_encode( trim( $feature[4] ) ) );
		}
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * @param string $identifier
	 * @return bool|ResultWrapper
	 */
	public function getNouns( $identifier ) {
		$dbr = wfGetDB( DB_REPLICA );
		$pageName = $this->getPageTitle();
		if ( $pageName === false ) {
			return false;
		}
		$identifiers = $dbr->select( 'mathidentifier',
			[ 'noun', 'evidence' ],
			[ 'pageTitle' => $pageName, 'identifier' => utf8_encode( $identifier ) ],
			__METHOD__,
			[ 'ORDER BY' => 'evidence DESC', 'LIMIT' => 5 ]
		);
		return $identifiers;
	}

	public function getPageTitle() {
		$revision = Revision::newFromId( $this->getRevisionID() );
		if ( $revision ) {
			return (string)$revision->getTitle();
		} else {
			return false;
		}
	}

	/**
	 * Gets all occurences of the tex.
	 *
	 * @param bool $currentOnly
	 *
	 * @return array
	 */
	public function getAllOccurences( $currentOnly = true ) {
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
	public function isCurrent() {
		$rev = Revision::newFromId( $this->revisionID );
		if ( is_null( $rev ) ) {
			return false;
		} else {
			return $rev->isCurrent();
		}
	}

	/**
	 * @param bool $hidePage
	 *
	 * @return string
	 */
	public function printLink2Page( $hidePage = true ) {
		$pageString = $hidePage ? "" : $this->getPageTitle() . " ";
		$anchor = MathSearchHooks::generateMathAnchorString(
			$this->getRevisionID(), $this->getAnchorID()
		);
		return "[[{$this->getPageTitle()}{$anchor}|{$pageString}Eq: {$this->getAnchorID()}]]";
	}

	public function getAnchorID() {
		return $this->anchorID;
	}

	public function setAnchorID( $ID ) {
		$this->anchorID = $ID;
	}

	public function render( $purge = false ) {
	}

	public function getSvg( /** @noinspection PhpUnusedParameterInspection */
		$render = 'render' ) {
		if ( $render === 'force' ) {
			$md = new MathoidDriver( $this->getUserInputTex(), $this->getInputType() );
			return $md->getSvg();
		}
		return parent::getSvg( $render ); // TODO: Change the autogenerated stub
	}

	public function getPng() {
		if ( $this->mode == 'mathml' || $this->mode == 'latexml' ) {
			if ( $this->getInputType() !== 'pmml' && $this->getRbi() ) {
				$pngUrl = preg_replace( '#/svg/#', '/png/', $this->getRbi()->getFullSvgUrl() );
				return file_get_contents( $pngUrl );
			} else {
				$md = new MathoidDriver( $this->getUserInputTex(), $this->getInputType() );
				return $md->getPng();
			}
		}
		$texvc = MathTexvc::newFromMd5( $this->getMd5() );
		$texvc->readFromDatabase();
		return $texvc->getPng();
	}

	public function addIdentifierTitle( $arg ) {
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
	public function getRevision() {
		return Revision::newFromId( $this->revisionID );
	}

	public function getRelations() {
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

	protected function getMathTableName() {
		global $wgMathAnalysisTableName;
		if ( is_null( $this->mathTableName ) ) {
			return $wgMathAnalysisTableName;
		} else {
			return $this->mathTableName;
		}
	}

	/**
	 * @param string $tableName mathoid or mathlatexml
	 */
	public function setMathTableName( $tableName ) {
		$this->mathTableName = $tableName;
	}

	/**
	 * @return MathoidDriver
	 */
	public function getTexInfo() {
		$m = new MathoidDriver( $this->userInputTex );
		if ( $m->texvcInfo() ) {
			return $m;
		} else {
			return false;
		}
	}

	public function getWikiText() {
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

	public static function getReSizedSvgLink( MathRenderer $renderer, $factor = 2 ) {
		$width = self::getSvgWidth( $renderer->getSvg() );
		$width = $width[1] * $factor . $width[2];
		$height = self::getSvgHeight( $renderer->getSvg() );
		$height = $height[1] * $factor . $height[2];
		$reflector = new ReflectionObject( $renderer );
		$method = $reflector->getMethod( 'getFallbackImage' );
		$method->setAccessible( true );
		$fbi = $method->invoke( $renderer );
		$fbi = preg_replace( "/width: (.*?)(ex|px|em)/", "width: $width", $fbi );
		$fbi = preg_replace( "/height: (.*?)(ex|px|em)/", "height: $height", $fbi );
		return $fbi;
	}

	public function getRbi() {
		return $this->rbi;
	}
}
