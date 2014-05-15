<?php
/**
 * Created by PhpStorm.
 * User: Moritz
 * Date: 13.05.14
 * Time: 21:33
 */
class MathSearchApi extends ApiBase {
	public function execute() {
		$mathPattern = $this->getMain()->getVal('mathpattern');
		$mathEngine = $this->getMain()->getVal('mathengine');
		$query = new MathQueryObject( $mathPattern );
		switch ( $mathEngine ){
			case 'db2':
				$query->setXQueryDialect( 'db2' );
				break;
			case 'basex':
				$query->setXQueryDialect( 'basex' );
		}
		//$cQuery = $query->getCQuery();
			$this->getResult()->addValue( null, 'xquery', array ( 'dialect' => $mathEngine ,
			'xQuery'=>$query->getXQuery()) );
	}

	// Description
	public function getDescription() {
		return 'Convert mathpattern to xquery';
	}

	// Describe the parameter
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'mathpattern' => 'mathpattern',
			'mathengine' => 'xquery dialect'
		) );
	}

	// Get examples
	public function getExamples() {
		return array(
			'api.php?action=mathquery&mathpattern=\sin(?x^2)&mathengine=basex'
			=> 'Generate the XQueryExpression for fquery $\sin(?x^2)$ in BaseX dialect.'
		);
	}
}