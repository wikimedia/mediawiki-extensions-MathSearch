<?php

class MathObject extends MathMathML {

	protected $anchorID = 0;
	protected $revisionID = 0;
	protected $index_timestamp = null;
	protected $dbLoadTime= 0;
	protected $mathTableName = null;

	private static function DebugPrint( $s ) {
		// $s= Sanitizer::safeEncodeAttribute($s);
		wfDebugLog( "MathSearch", $s );
	}

	public function getAnchorID() {
		return $this->anchorID;
	}

	public function setAnchorID( $ID ) {
		$this->anchorID = $ID;
	}

	public function getRevisionID() {
		return $this->revisionID;
	}

	public function setRevisionID( $ID ) {
		$this->revisionID = $ID;
	}

	public function getIndexTimestamp() {
		return $this->index_timestamp;
	}

	public function getInputHash() {
		//wfDebugLog( 'MathSearch', 'Debugger dies here' );
		// die('end of debug toolbar');
		if ( $this->inputHash ) {
			return $this->inputHash;
		} else {
			return parent::getInputHash();
		}
	}

	public static function hash2md5( $hash ){
		//TODO: make MathRenderer::dbHash2md5 public
		$dbr = wfGetDB( DB_SLAVE );
		$xhash = unpack( 'H32md5', $dbr->decodeBlob( $hash ) . "                " );
		return $xhash['md5'];
	}
	/**
	 *
	 * @global boolean $wgMathDebug
	 * @param stdClass $res
	 * @return boolean|\self
	 */
	public static function constructformpagerow( $res ) {
		global $wgMathDebug;
		if ( $res && $res->mathindex_revision_id > 0 ) {
			$class = get_called_class();
			/** @type MathObject $instance */
			$instance = new $class;
			$instance->setRevisionID( $res->mathindex_revision_id );
			$instance->setAnchorID( $res->mathindex_anchor );
			if ( $wgMathDebug && isset($res->mathindex_timestamp) ) {
				$instance->index_timestamp = $res->mathindex_timestamp;
			}
			$instance->inputHash = $res->mathindex_inputhash;
			$instance->readFromDatabase();
			return $instance;
		} else {
			return false;
		}
	}

	public static function findSimilarPages( $pid ) {
		global $wgOut;
		$out = "";
		$dbr = wfGetDB( DB_SLAVE );
		try {
			$res = $dbr->select( 'mathpagesimilarity',
				array( 'pagesimilarity_A as A', 'pagesimilarity_B as B', 'pagesimilarity_Value as V' ),
				"pagesimilarity_A=$pid OR pagesimilarity_B=$pid", __METHOD__,
				array( "ORDER BY" => 'V DESC', "LIMIT" => 10 )
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
		} catch ( Exception $e ) {
			return "DatabaseProblem";
		}
	}

	public function getObservations( $update = true) {
		global $wgOut;
		$dbr = wfGetDB( DB_SLAVE );
		try {
			$res = $dbr->select( array( "mathobservation", "mathvarstat", 'mathrevisionstat' )
				, array( "mathobservation_featurename", "mathobservation_featuretype", 'varstat_featurecount',
					'revstat_featurecount', "count(*) as localcnt" ),
					array( "mathobservation_inputhash" => $this->getInputHash(),
					'varstat_featurename = mathobservation_featurename',
					'varstat_featuretype = mathobservation_featuretype',
					'revstat_revid' => $this->getRevisionID(),
					'revstat_featureid = varstat_id'
				)
				, __METHOD__, array( 'GROUP BY' => 'mathobservation_featurename',
					'ORDER BY' => 'varstat_featurecount' )
			);
		} catch ( Exception $e ) {
			return "Database problem";
		}
		$wgOut->addWikiText($res->numRows(). 'results');
		if ($res->numRows() == 0){
			if ( $update ){
				$this->updateObservations();
				$this->getObservations( false );
			} else {
				$wgOut->addWikiText("no statistics present please run the maintenance script ExtractFeatures.php");
			}
		}
		$wgOut->addWikiText($res->numRows(). ' results');
		if ( $res ) {
			foreach ( $res as $row ) {
				$featureName = utf8_decode( $row->mathobservation_featurename );
				if ( bin2hex($featureName)==  'e281a2') {
					$featureName = 'invisibe-times';
				}
				$wgOut->addWikiText( '*' . $row->mathobservation_featuretype . ' <code>' .
					$featureName . '</code> (' . $row->localcnt . '/' . $row->pagestat_featurecount .
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

	/**
	 * @param $identifier
	 * @return bool|ResultWrapper
	 */
	public function getNouns($identifier){
		$dbr = wfGetDB( DB_SLAVE );
		$pageName = $this->getPageTitle();
		if( $pageName === false ) { return false; }
		$identifiers = $dbr->select('mathidentifier',
			array( 'noun', 'evidence' ),
			array(  'pageTitle' => $pageName, 'identifier' => utf8_encode( $identifier )),
			__METHOD__ ,
			array('ORDER BY' => 'evidence DESC', 'LIMIT' => 5)
		);
		return $identifiers;

	}

	public function updateObservations( $dbw = null ) {
		$this->readFromDatabase();
		preg_match_all( "#<(mi|mo|mtext)( ([^>].*?))?>(.*?)</\\1>#u", $this->getMathml(), $rule, PREG_SET_ORDER );
		if ( $dbw == null ) {
			$dbgiven = false;
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
		} else {
			$dbgiven = true;
		}
		$dbw->delete( "mathobservation", array( "mathobservation_inputhash" => $this->getInputHash() ) );
		wfDebugLog('MathSearch', 'delete obervations for '.bin2hex($this->getInputHash()));
		foreach ( $rule as $feature ) {
			$dbw->insert( "mathobservation", array(
				"mathobservation_inputhash" => $this->getInputHash(),
				"mathobservation_featurename" => utf8_encode( trim( $feature[ 4 ] ) ),
				"mathobservation_featuretype" => utf8_encode( $feature[ 1 ] ),
			) );
			wfDebugLog('MathSearch', 'insert observation for '.bin2hex($this->getInputHash())
			. utf8_encode( trim( $feature[ 4 ] )		));
		}
		if ( !$dbgiven ) {
			$dbw->commit();
		}
	}
	public static function cloneFromRenderer(MathRenderer $renderer){
		$instance = new MathObject( $renderer->getTex() );
		$instance->setMathml( $renderer->getMathml() );
		$instance->setSvg( $renderer->getSvg() );
		$instance->setMode( $renderer->getMode() );
		$instance->setMathStyle( $renderer->getMathStyle() );
		return $instance;
	}

	/**
	 *
	 * @param int $pid
	 * @param int $eid
	 * @return self instance
	 */
	public static function constructformpage( $pid, $eid ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow(
			array( 'mathindex' ), self::dbIndexFieldsArray(), 'mathindex_revision_id = ' . $pid
			. ' AND mathindex_anchor= "' . $eid . '"' );
		$start = microtime(true);
		$o = self::constructformpagerow( $res );
		wfDebugLog("MathSearch", "Fetched in ". (microtime(true)-$start) );
		return $o;
	}

	/**
	 * Gets all occurences of the tex.
	 *
	 * @param bool $currentOnly
	 *
	 * @return array
	 */
	public function getAllOccurences( $currentOnly = true ) {
		$out = array( );
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'mathindex', self::dbIndexFieldsArray(), array( 'mathindex_inputhash' => $this->getInputHash() )
		);

		foreach ( $res as $row ) {
			$var = self::constructformpagerow( $row );
			if ( $var ) {
				if ( $currentOnly === false || $var->isCurrent() ){
				array_push( $out, $var );
			}
		}
		}
		return $out;
	}

	public function getPageTitle() {
		$revision = Revision::newFromId( $this->getRevisionID() );
		if ( $revision ) {
			return (string) $revision->getTitle();
		} else {
			return false;
		}
	}

	/**
	 * @param bool $hidePage
	 *
	 * @return string
	 */public function printLink2Page( $hidePage = true ) {
		$pageString = $hidePage ? "" : $this->getPageTitle() . " ";
		$anchor = MathSearchHooks::generateMathAnchorString( $this->getRevisionID(), $this->getAnchorID() );
		return "[[{$this->getPageTitle()}{$anchor}|{$pageString}Eq: {$this->getAnchorID()}]]";
	}

	/**
	 * @return array
	 */
	private static function dbIndexFieldsArray() {
		global $wgMathDebug;
		$in = array(
			'mathindex_revision_id',
			'mathindex_anchor',
			'mathindex_inputhash' );
		if ( $wgMathDebug ) {
			$debug_in = array(
				'mathindex_timestamp' );
			$in = array_merge( $in, $debug_in );
		}
		return $in;
	}

	public function render( $purge = false ) {

	}
	public function getPng() {
		$texvc = MathTexvc::newFromMd5($this->getMd5());
		$texvc->readFromDatabase();
		return $texvc->getPng();
	}

	public function addIdentifierTitle($arg){
		//return '<mi>X</mi>';
		$attribs = preg_replace('/title\s*=\s*"(.*)"/','',$arg[2]);
		$content = $arg[4];
		$nouns=$this->getNouns( $content );
		$title ='not set';
		if ( $nouns ){
			foreach($nouns as $identifier){
				$title .= '**'.$identifier->noun .'('.$identifier->evidence.')';
			}
		} else {
			$title = '** not found';
		}
		return '<'.$arg[1]." title=\"$title\"".$attribs.'>'.$arg[4].'</'.$arg[1].'>';
	}
	protected function getMathTableName() {
		global $wgMathAnalysisTableName;
		if ( is_null( $this->mathTableName ) ){
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
	 * @param $wikiText
	 * @return mixed
	 */
	public static function extractMathTagsFromWikiText( $wikiText ) {
		$wikiText = Sanitizer::removeHTMLcomments( $wikiText );
		//TODO:Check if this is necessary?
		$wikiText = preg_replace( '#<nowiki>(.*)</nowiki>#', '', $wikiText );
		$matches = array();
		Parser::extractTagsAndParams( array( 'math' ), $wikiText, $matches );
		return $matches;
	}

	public static function updateStatistics(){
		$dbw = wfGetDB( DB_MASTER );
		$dbw->query( 'TRUNCATE TABLE `mathvarstat`' );
		$dbw->query("INSERT INTO `mathvarstat` (`varstat_featurename` , `varstat_featuretype`, `varstat_featurecount`)\n"
			. "SELECT `mathobservation_featurename` , `mathobservation_featuretype` , count( * ) AS CNT\n"
			. "FROM `mathobservation`\n"
			. "JOIN mathindex ON `mathobservation_inputhash` = mathindex_inputhash\n"
			. "GROUP BY `mathobservation_featurename` , `mathobservation_featuretype`\n"
			. "ORDER BY CNT DESC");
		$dbw->query( 'TRUNCATE TABLE `mathrevisionstat`' );
		$dbw->query( 'INSERT INTO `mathrevisionstat`(`revstat_featureid`,`revstat_revid`,`revstat_featurecount`) '
			. 'SELECT varstat_id, mathindex_revision_id, count(*) AS CNT FROM `mathobservation` '
			. 'JOIN mathindex ON `mathobservation_inputhash` = mathindex_inputhash '
			. 'JOIN mathvarstat ON varstat_featurename = `mathobservation_featurename` AND varstat_featuretype = `mathobservation_featuretype` '
			. 'GROUP BY `mathobservation_featurename`, `mathobservation_featuretype`, mathindex_revision_id ORDER BY CNT DESC' );
	}

	/**
	 * @return null|Revision
	 */
	public function getRevision(){
		return Revision::newFromId( $this->revisionID );
	}

	/**
	 * @return bool
	 */
	public function isCurrent(){
		$rev = Revision::newFromId( $this->revisionID );
		if ( is_null( $rev ) ){
			return false;
		} else {
			return $rev->isCurrent();
		}
	}
}
