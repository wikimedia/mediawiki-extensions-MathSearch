<?php

namespace MediaWiki\Extension\MathSearch\Rest\ArqTask;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\ILoadBalancer;

class GetPostId extends SimpleHandler {

	/** @var DBConnRef */
	private $dbr;

	public function __construct( ILoadBalancer $lb ) {
		$this->dbr = $lb->getConnectionRef( DB_REPLICA );
	}

	public function getParamSettings() {
		return [
			'fId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	public function run( int $fId ) {
		// Example query:
		//SELECT t.math_body, m.math_external_id, m.math_local_qid
		//FROM wiki_arqmath20.math_wbs_text_store t, math_wbs_entity_map m
		//WHERE math_body LIKE '% id=\'238553\'%' and m.math_local_qid = t.math_local_qid;
		return $this->dbr->selectRow(
			[
				'm' => 'math_wbs_entity_map',
				't' => 'math_wbs_text_store',
			],
			[
				'm.math_external_id',
				'm.math_local_qid'
			],
			[
				"t.math_body LIKE '% id=\\'{$fId}\\'%'",
				'm.math_local_qid = t.math_local_qid'
			]
		);
	}

}
