<?php

class MathQueryObject extends MathObject {
	const MIN_DEPTH = 0;
	const SELECTIVITY_QVAR = .1;
	/**@var int */
	private $queryID = false;
	private $texquery = false;
	private $cquery = false;
	private $pquery = false;
	private $pmmlSettings = array('format' => 'xml',
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
		'texvc'),
	);
	/**
	 * Set the query id
	 * @param int $id
	 */
	public function setQueryId($id){
		$this->queryID = $id;
	}

	/**
	 * 
	 * @param type $rpage
	 * @param int $queryID
	 * @return \self
	 */
	public static function newQueryFromEquationRow($rpage, $queryID = false) {
		$instance = self::constructformpagerow($rpage);
		$instance->setQueryId($queryID);
		return $instance;
	}
	/**
	 * Returns the queryId. If not set a random query id will be generated.
	 * @return int
	 */
	public function getQueryId(){
		if ($this->queryID === false ){
			$this->queryID = rand();
		}
		return $this->queryID;
	}

	/**
	 * Returns the tex query string.
	 * If not set a a query id will be generated.
	 * @return string
	 */
	public function getTeXQuery(){
		if ($this->texquery === false ){
			$this->injectQvar();
		}
		return $this->texquery;
	}

	/**
	 * Returns the ContentMathML expression.
	 * If not set a random query id will be generated based on the TeXQuery.
	 * @return string
	 */
	public function getCQuery(){
		if ($this->cquery === false ){
			$this->generateContentQueryString();
		}
		return $this->cquery;
	}

	/**
	 * Returns the PresentationMathML expression.
	 * If not set a random query id will be generated based on the TeXQuery.
	 * @return string
	 */
	public function getPQuery(){
		if ($this->pquery === false ){
			$this->generatePresentationQueryString();
		}
		return $this->pquery;
	}
	public function serlializeToXML(  ){
		$cx = simplexml_load_string($this->getCQuery());
		$xCore = preg_replace("/\\n/","\n\t\t", $cx->children('mws',TRUE)->children('m',TRUE)->asXML());
		$px = simplexml_load_string($this->getPQuery());
		$pmml = preg_replace('#<mi (.*) mathcolor="red">(.*)</mi>#',
			'<mws:qvar xmlns:mws="http://www.mathweb.org/mws/ns" name="$2"/>',
			$px->children()->asXML())."\n";
		$out = '<topic xmlns="http://ntcir-math.nii.ac.jp/">';
		$out .= "\n\t<num>FSE-GC-". $this->getQueryId() ."</num>";
		$out .= "\n\t<type>Content-Query</type>";
		$out .= "\n\t<title>Query ".$this->getQueryId()." (".$this->getPageTitle().")<title>";
		$out .= "\n\t<query>";
		$out.= "\n\t\t<TeXquery>{$this->getTeXQuery()}</TeXquery>";
		$out.= "\n\t\t<cquery><m:math>{$pmml}</m:math></cquery>";
		$out.= "\n\t\t<cquery><m:math>{$xCore}</m:math></cquery>";
		$out .= "\n\t</query>";
		$out .= "\n\t<relevance>find result similar to "
			. "<a href=\"http://demo.formulasearchengine.com/index.php?"
			. "curid={$this->getPageID()}#math{$this->getAnchorID()}\">"
			. htmlspecialchars( $this->getUserInputTex()) ."</a></relevance>";
		$out.="\n</topic>\n";
		return $out;
		}

	public function injectQvar() {
		$out = "";
		$level = 0;
		$qVarLevel = PHP_INT_MAX;
		$qVarNo = 0;
		foreach (str_split($this->getTex()) as $currentchar) {
			switch ($currentchar) {
				case '{':
					$level++;
					if ($level >= self::MIN_DEPTH && $level < $qVarLevel) {
						if ((mt_rand() / mt_getrandmax()) < self::SELECTIVITY_QVAR * $level) {
							$qVarLevel = $level;
							$out.="{?{x" . $qVarNo++ . "}";
						} else {
							$out .='{';
						}
					} else {
						if ($level < $qVarLevel) {
							$out.='{';
						}
					}
					break;
				case '}':
					$level--;
					if ($level < $qVarLevel) {
						$qVarLevel = PHP_INT_MAX;
						$out.="}";
					}
					break;
				default:
					if ($level < $qVarLevel) {
						$out.=$currentchar;
					}
			}
		}
		$this->texquery = $out;
		return $out;
	}

	public function generateContentQueryString(){
		$renderer = new MathLaTeXML($this->getTexQuery());
		$renderer->setLaTeXMLSettings('profile=mwsquery');
		$renderer->setAllowedRootElments(array('query'));
		$renderer->render(true);
		$this->cquery = $renderer->getMathml();
		return $this->cquery;
	}

	public function generatePresentationQueryString(){
		$renderer = new MathLaTeXML($this->getTexQuery());
		$renderer->setXMLValidaton(false);
		//$renderer->setAllowedRootElments(array('query'));
		$renderer->setLaTeXMLSettings($this->pmmlSettings);
		if ($renderer->render(true) ) {
			$this->pquery = $renderer->getMathml();
			return $this->pquery;
		} else {
			echo $renderer->getLastError();
			return $renderer->getLastError();
		}
	}
}
