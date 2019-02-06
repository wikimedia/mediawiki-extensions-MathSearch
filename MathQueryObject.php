<?php
use MediaWiki\Logger\LoggerFactory;

class MathQueryObject extends MathObject {
	const MIN_DEPTH = 0;
	const SELECTIVITY_QVAR = 0.1;
	/** @var int */
	private $queryID = false;
	private $texquery = false;
	private $cquery = false;
	private $pquery = false;
	private $xQuery = '';
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

	private $pmmlSettings = [
		'format' => 'xml',
		'whatsin' => 'math',
		'whatsout' => 'math',
		'pmml',
		'nodefaultresources',
		'preload' => [
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
			// '[ids]latexml.sty',
			'texvc'
		],
	];

	/**
	 * @param string $texquery the TeX-like search input
	 */
	public function __construct( $texquery = '' ) {
		$this->texquery = $texquery;
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
	public function saveToDatabase( $overwrite = false ) {
		$fields = [
			'qId' => $this->queryID,
			'oldId' => $this->getRevisionID(),
			'fId' => $this->getAnchorID(),
			'texQuery' => $this->getTeXQuery(),
			'qVarCount' => $this->qVarCount,
			'isDraft' => true,
			'math_inputhash' => $this->getInputHash()
		]; // Store the inputhash just to be sure.
		$dbw = wfGetDB( DB_MASTER );
		// Overwrite draft queries only.
		if ( $dbw->selectField(
			'math_wmc_ref', 'isDraft', [ 'qId' => $this->queryID ]
		) && $overwrite ) {
			return $dbw->update( 'math_wmc_ref', $fields, [ 'qId' => $this->queryID ] );
		} else {
			return $dbw->insert( 'math_wmc_ref', $fields );
		}
	}

	public function exportTexDocument() {
		$texInput = htmlspecialchars( $this->getUserInputTex() );
		$texInputComment = preg_replace( "/[\n\r]/", "\n%", $texInput );
		$title = Title::newFromId( $this->getRevisionID() );
		$absUrl =
			$title->getFullURL( [ "oldid" => $title->getLatestRevID() ] ) .
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
		/** @var self $instance */
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
	 * If not set a query id will be generated.
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
					$level++;
					if ( $level >= self::MIN_DEPTH && $level < $qVarLevel ) {
						if ( ( mt_rand() / mt_getrandmax() ) < self::SELECTIVITY_QVAR * $level ) {
							$qVarLevel = $level;
							$out .= "{?{x" . $qVarNo++ . "}";
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
					$level--;
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
		$cSettings = array_diff( $wgMathDefaultLaTeXMLSetting, [ 'cmml' ] );
		$cSettings['preload'][] = 'mws.sty';
		$cSettings['stylesheet'] = 'MWSquery.xsl';
		return $cSettings;
	}

	public function generateContentQueryString() {
		$renderer = new MathLaTeXML( $this->getTexQuery() );
		$renderer->setLaTeXMLSettings( $this->getLaTeXMLCMMLSettings() );
		$renderer->setAllowedRootElements( [ 'query' ] );
		if ( $renderer->render( true ) ) {
			$this->cquery = $renderer->getMathml();
			return $this->cquery;
		} else {
			LoggerFactory::getInstance(
				'MathSearch'
			)->error( 'error during generation of query string' . $renderer->getLastError() );
		}
	}

	public function generatePresentationQueryString() {
		$renderer = new MathLaTeXML( $this->getTexQuery() );
		// $renderer->setXMLValidaton( false );
		// $renderer->setAllowedRootElements( array( 'query' ) );
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
	 * @return string
	 */
	public function getXQuery() {
		return $this->xQuery;
	}

	/**
	 * @param string $xQuery
	 */
	public function setXQuery( $xQuery ) {
		$this->xQuery = $xQuery;
	}
}
