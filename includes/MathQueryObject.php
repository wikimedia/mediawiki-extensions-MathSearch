<?php

use MediaWiki\Extension\Math\MathLaTeXML;
use MediaWiki\Extension\Math\MathNativeMML;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class MathQueryObject extends MathObject {

	private const MIN_DEPTH = 0;
	private const SELECTIVITY_QVAR = 0.1;

	/** @var int */
	private $queryID = false;
	/** @var string|null|false */
	private $cquery = false;
	/** @var string|false */
	private $pquery = false;
	/** @var string */
	private $xQuery = '';
	/** @var int */
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
	private const PMML_SETTINGS = [
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
	 * @param string|false $texquery the TeX-like search input
	 */
	public function __construct(
		private string|bool $texquery = '',
	) {
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
		global $wgMathWmcServer;
		// If $wgMathWmcServer is unset there's no math_wmc_ref table to update
		if ( !$wgMathWmcServer ) {
			return false;
		}

		$fields = [
			'qId' => $this->queryID,
			'oldId' => $this->getRevisionID(),
			'fId' => $this->getAnchorID(),
			'texQuery' => $this->getTeXQuery(),
			'qVarCount' => $this->qVarCount,
			'isDraft' => true,
			'math_inputhash' => $this->getInputHash()
		]; // Store the inputhash just to be sure.
		$dbw = MediaWikiServices::getInstance()
			->getConnectionProvider()
			->getPrimaryDatabase();
		// Overwrite draft queries only.
		if ( $dbw->selectField(
			'math_wmc_ref', 'isDraft', [ 'qId' => $this->queryID ], __METHOD__
		) && $overwrite ) {
			return $dbw->update( 'math_wmc_ref', $fields, [ 'qId' => $this->queryID ], __METHOD__ );
		} else {
			return $dbw->insert( 'math_wmc_ref', $fields, __METHOD__ );
		}
	}

	public function exportTexDocument() {
		$texInput = htmlspecialchars( $this->getUserInputTex() );
		$texInputComment = preg_replace( "/[\n\r]/", "\n%", $texInput );
		$title = Title::newFromID( $this->getRevisionID() );
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
	 * @param stdClass $rpage
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
	 * @return string|null
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
						if ( ( self::SELECTIVITY_QVAR * $level ) > ( mt_rand() / mt_getrandmax() ) ) {
							$qVarLevel = $level;
							$out .= "{?{x" . $qVarNo++ . "}";
						} else {
							$out .= '{';
						}
					} elseif ( $level < $qVarLevel ) {
						$out .= '{';
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

	/**
	 * @return string|null
	 */
	public function generateContentQueryString() {
		$renderer = new MathLaTeXML( $this->getTeXQuery() );
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

	/**
	 * @return string
	 */
	public function generatePresentationQueryString() {
		$renderer = new MathNativeMML( $this->getUserInputTex() );
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
