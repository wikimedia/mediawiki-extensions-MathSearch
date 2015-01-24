<?php

class MathQueryObject extends MathObject {
	const MIN_DEPTH = 0;
	const SELECTIVITY_QVAR = .1;
	/**@var int */
	private $queryID = false;
	private $texquery = false;
	private $cquery = false;
	private $pquery = false;
	/** @var XQueryGenerator current instance of xQueryGenerator */
	private $xQuery = false;
	private $xQueryDialect = false;
	private $qVarCount = 0;
	/* ToDo: Update to new format
	<code>
		 latexmlc --whatsin=fragment --path=$(LLIB) \
	--preamble=$(LLIB)/pre.tex --postamble=$(LLIB)/post.tex \
	--format=xml --cmml --pmml --preload=[ids]latexml.sty \
	--stylesheet=$(LLIB)/ntcir11-topic.xsl \
	--destination=$@ --log=$(basename $<).ltxlog $<
	</code> see http://kwarc.info/kohlhase/event/NTCIR11/
	*/

	private $pmmlSettings = array(
		'format' => 'xml',
		'whatsin' => 'math',
		'whatsout' => 'math',
		'pmml',
		'nodefaultresources',
		'preload' => array(
			'LaTeX.pool',
			'article.cls',
			'amsmath.sty',
			'amsthm.sty',
			'amstext.sty',
			'amssymb.sty',
			'eucal.sty',
			'[dvipsnames]xcolor.sty',
			'url.sty',
			'hyperref.sty',
			'mws.sty',
			//		'[ids]latexml.sty',
			'texvc'
		),
	);

	/**
	 * @param string $texquery the TeX-like search input
	 * @param string $xQueryDialect e.g. db2 or basex
	 */
	public function __construct( $texquery = '', $xQueryDialect = 'db2' ) {
		$this->texquery = $texquery;
		$this->xQueryDialect = $xQueryDialect;
	}

	/**
	 * Set the query id
	 * @param int $id
	 */
	public function setQueryId( $id ) {
		$this->queryID = $id;
	}

	/**
	 * @param bool $overwrite
	 *
	 * @return bool
	 */
	public function saveToDatabase( $overwrite = false ){
		$fields = array(
			'qId' => $this->queryID,
			'oldId' => $this->getRevisionID(),
			'fId' => $this->getAnchorID(),
			'texQuery' => $this->getTeXQuery(),
			'qVarCount' => $this->qVarCount,
			'isDraft' => true,
			'math_inputhash' => $this->getInputHash() ); // Store the inputhash just to be sure.
		$dbw = wfGetDB( DB_MASTER );
		// Overwrite draft queries only.
		if ( $dbw->selectField( 'math_wmc_ref', 'isDraft', array( 'qId' =>  $this->queryID ) ) && $overwrite ){
			return $dbw->update( 'math_wmc_ref', $fields, array( 'qId' => $this->queryID ) );
		} else {
			return $dbw->insert( 'math_wmc_ref', $fields );
		}
	}

	public function exportTexDocument() {
		$texInput = htmlspecialchars( $this->getUserInputTex() );
		$texInputComment = preg_replace( "/[\n\r]/", "\n%", $texInput );
		$title = Title::newFromId( $this->getRevisionID() );
		$absUrl =
			$title->getFullURL( array( "oldid" => $title->getLatestRevID() ) ) .
			MathSearchHooks::generateMathAnchorString( $title->getLatestRevID(), $this->getAnchorID(), '' );
		return <<<TeX
\begin{topic}{{$this->getPageTitle()}-{$this->getAnchorID()}}
  \begin{fquery}\${$this->getTeXQuery()}\$\end{fquery}
	\begin{private}
	    \begin{relevance}
			find result similar to Formula {$this->getAnchorID()} on page {$this->getPageTitle()}:
			%\href{{$absUrl}}{\${$texInputComment}\$}
	    \end{relevance}
	    \examplehit{{$absUrl}}
	    \contributor{Moritz Schubotz}
	\end{private}
\end{topic}

TeX;

	}

	/**
	 *
	 * @param ResultWrapper $rpage
	 * @param bool|int $queryID
	 * @return \self
	 */
	public static function newQueryFromEquationRow( $rpage, $queryID = false ) {
		$instance = self::constructformpagerow( $rpage );
		$instance->setQueryId( $queryID );
		return $instance;
	}

	/**
	 * Returns the queryId. If not set a random query id will be generated.
	 * @return int
	 */
	public function getQueryId() {
		if ( $this->queryID === false ) {
			$this->queryID = rand();
		}
		return $this->queryID;
	}

	/**
	 * Returns the tex query string.
	 * If not set a a query id will be generated.
	 * @return string
	 */
	public function getTeXQuery() {
		if ( $this->texquery == false ) {
			$this->injectQvar();
		}
		return $this->texquery;
	}

	/**
	 * Returns the ContentMathML expression.
	 * If not set a random query id will be generated based on the TeXQuery.
	 * @return string
	 */
	public function getCQuery() {
		if ( $this->cquery === false ) {
			$this->generateContentQueryString();
		}
		return $this->cquery;
	}

	/**
	 * Returns the PresentationMathML expression.
	 * If not set a random query id will be generated based on the TeXQuery.
	 * @return string
	 */
	public function getPQuery() {
		if ( $this->pquery === false ) {
			$this->generatePresentationQueryString();
		}
		return $this->pquery;
	}


	public function injectQvar() {
		$out = "";
		$level = 0;
		$qVarLevel = PHP_INT_MAX;
		$qVarNo = 0;
		foreach ( str_split( $this->getTex() ) as $currentchar ) {
			switch ( $currentchar ) {
				case '{':
					$level ++;
					if ( $level >= self::MIN_DEPTH && $level < $qVarLevel ) {
						if ( ( mt_rand() / mt_getrandmax() ) < self::SELECTIVITY_QVAR * $level ) {
							$qVarLevel = $level;
							$out .= "{?{x" . $qVarNo ++ . "}";
						} else {
							$out .= '{';
						}
					} else {
						if ( $level < $qVarLevel ) {
							$out .= '{';
						}
					}
					break;
				case '}':
					$level --;
					if ( $level < $qVarLevel ) {
						$qVarLevel = PHP_INT_MAX;
						$out .= "}";
					}
					break;
				default:
					if ( $level < $qVarLevel ) {
						$out .= $currentchar;
					}
			}
		}
		$this->qVarCount = $qVarNo;
		$this->texquery = $out;
		return $out;
	}

	public function getLaTeXMLCMMLSettings() {
		global $wgMathDefaultLaTeXMLSetting;
		$cSettings = $wgMathDefaultLaTeXMLSetting;
		$cSettings['preload'][] = 'mws.sty';
		$cSettings['stylesheet'] = 'MWSquery.xsl';
		return $cSettings;
	}

	public function getLaTeXMLPMLSettings() {
		global $wgMathDefaultLaTeXMLSetting;
		$cSettings = array_diff( $wgMathDefaultLaTeXMLSetting, array( 'cmml' ) );
		$cSettings['preload'][] = 'mws.sty';
		$cSettings['stylesheet'] = 'MWSquery.xsl';
		return $cSettings;
	}

	public function generateContentQueryString() {
		$renderer = new MathLaTeXML( $this->getTexQuery() );
		$renderer->setLaTeXMLSettings( $this->getLaTeXMLCMMLSettings() );
		$renderer->setAllowedRootElements( array( 'query' ) );
		if ( $renderer->render( true ) ) {
			$this->cquery = $renderer->getMathml();
			return $this->cquery;
		} else {
			wfDebugLog( 'math', 'error during geration of query string' . $renderer->getLastError() );
		}
	}

	public function generatePresentationQueryString() {
		$renderer = new MathLaTeXML( $this->getTexQuery() );
		$renderer->setXMLValidaton( false );
		//$renderer->setAllowedRootElments(array('query'));
		$renderer->setLaTeXMLSettings( $this->pmmlSettings );
		if ( $renderer->render( true ) ) {
			$this->pquery = $renderer->getMathml();
			return $this->pquery;
		} else {
			echo $renderer->getLastError();
			return $renderer->getLastError();
		}
	}

	/**
	 *
	 * @param String ("DB2"|"BaseX") $dialect the name of the xQueryGenerator
	 * @return XQueryGenerator
	 * @throws Exception
	 */
	public function setXQueryDialect( $dialect = false ) {
		if ( $dialect === false ) {
			$dialect = $this->xQueryDialect;
		}
		switch ( $dialect ) {
			case 'db2':
				$this->xQuery = new XQueryGeneratorDB2( $this->getCQuery() );
				break;
			case 'basex':
				$this->xQuery = new XQueryGeneratorBaseX( $this->getCQuery() );
				break;
			default:
				throw new Exception( $dialect . 'is not a valid XQueryDialect' );
		}
		return $this->xQuery;
	}

	public function setXQueryGenerator( $xQueryGenerator ) {
		$this->xQuery = $xQueryGenerator;
	}

	public function getXQueryGenerator() {
		if ( $this->xQuery === false ) {
			$this->setXQueryDialect();
		}
		return $this->xQuery;
	}

	/**
	 * @see XQueryGenerator::getXQuery()
	 * @return String
	 */
	public function getXQuery() {
		return $this->getXQueryGenerator()->getXQuery();
	}

	/**
	 * Posts the query to mwsd and evaluates the result data
	 * @return boolean
	 */
	function postQuery() {
		global $wgMathSearchMWSUrl, $wgMathDebug;

		$numProcess = 30000;
		$tmp =
			str_replace( "answsize=\"30\"", "answsize=\"$numProcess\" totalreq=\"yes\"", $this->getCQuery() );
		$mwsExpr = str_replace( "m:", "", $tmp );
		wfDebugLog( 'mathsearch', 'MWS query:' . $mwsExpr );
		$res = Http::post( $wgMathSearchMWSUrl, array( "postData" => $mwsExpr, "timeout" => 60 ) );
		if ( $res == false ) {
			if ( function_exists( 'curl_init' ) ) {
				$handle = curl_init();
				$options = array(
					CURLOPT_URL => $wgMathSearchMWSUrl,
					CURLOPT_CUSTOMREQUEST => 'POST', // GET POST PUT PATCH DELETE HEAD OPTIONS
				);
				// TODO: Figure out how not to write the error in a message and not in top of the output page
				curl_setopt_array( $handle, $options );
				$details = curl_exec( $handle );
			} else {
				$details = "curl is not installed.";
			}
			wfDebugLog( "MathSearch",
				"Nothing retreived from $wgMathSearchMWSUrl. Check if mwsd is running. Error:" .
				var_export( $details, true ) );
			return false;
		}
		$xres = new SimpleXMLElement( $res );
		if ( $wgMathDebug ) {
			$out = $this->getOutput();
			$out->addWikiText( '<source lang="xml">' . $res . '</source>' );
		}
		$this->numMathResults = (int)$xres["total"];
		wfDebugLog( "MathSearch", $this->numMathResults . " results retreived from $wgMathSearchMWSUrl." );
		if ( $this->numMathResults == 0 ) {
			return true;
		}
		$this->relevantMathMap = array();
		$this->mathResults = array();
		$this->processMathResults( $xres );
		if ( $this->numMathResults >= $numProcess ) {
			ini_set( 'memory_limit', '256M' );
			for ( $i = $numProcess; $i <= $this->numMathResults; $i += $numProcess ) {
				$query = str_replace( "limitmin=\"0\" ", "limitmin=\"$i\" ", $mwsExpr );
				$res = Http::post( $wgMathSearchMWSUrl, array( "postData" => $query, "timeout" => 60 ) );
				wfDebugLog( 'mathsearch', 'MWS query:' . $query );
				if ( $res == false ) {
					wfDebugLog( "MathSearch",
						"Nothing retreived from $wgMathSearchMWSUrl. check if mwsd is running there" );
					return false;
				}
				$xres = new SimpleXMLElement( $res );
				$this->processMathResults( $xres );
			}
		}
		return true;
	}

}
