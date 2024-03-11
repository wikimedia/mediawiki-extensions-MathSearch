<?php

namespace MediaWiki\Extension\MathSearch\Graph;

class Query {
	public static function getQueryFromConfig( string $type, int $offset, int $limit ) {
		global $wgMathProfileQueries;
		return <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>
PREFIX wd: <https://portal.mardi4nfdi.de/entity/>
SELECT ?qid WHERE {
    BIND (REPLACE(STR(?item), "^.*/Q([^/]*)$", "$1") as ?qid)
${wgMathProfileQueries[$type]}
}
LIMIT $limit
OFFSET $offset
SPARQL;
	}
}