<?php

namespace MediaWiki\Extension\MathSearch\Graph;

use MediaWiki\MediaWikiServices;
use ToolsParser;

class Query {
	public static function getQueryFromConfig( string $type, int $offset, int $limit ) {
		global $wgMathProfileQueries;
		return <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>
PREFIX wd: <https://portal.mardi4nfdi.de/entity/>
SELECT ?qid WHERE {
    BIND (REPLACE(STR(?item), "^.*/Q([^/]*)$", "$1") as ?qid)
{$wgMathProfileQueries[$type]}
}
LIMIT $limit
OFFSET $offset
SPARQL;
	}

	public static function getQueryFromProfileType( string $type, int $offset, int $limit ) {
		global $wgMathSearchPropertyProfileType, $wgMathProfileQIdMap;
		return <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>
PREFIX wd: <https://portal.mardi4nfdi.de/entity/>
SELECT ?qid WHERE {
    BIND (REPLACE(STR(?item), "^.*/Q([^/]*)$", "$1") as ?qid)
    ?item wdt:P$wgMathSearchPropertyProfileType wd:{$wgMathProfileQIdMap[$type]} .
    ?item wikibase:sitelinks ?sitelinks .
    FILTER (?sitelinks < 1 ).
}
LIMIT $limit
OFFSET $offset
SPARQL;
	}

	public static function getQidFromDe( string $des ) {
		return /** @lang Sparql */ <<<SPARQL
SELECT
  (REPLACE(STR(?item), ".*Q", "") AS ?qid)
  ?de
WHERE {
  VALUES ?de  { $des }
?item wdt:P1451 ?de
}
SPARQL;
	}

	public static function getQidFromConcept( string $concepts ) {
		return /** @lang Sparql */ <<<SPARQL
SELECT
  (REPLACE(STR(?item), ".*Q", "") AS ?qid)
  ?de
WHERE {
  VALUES ?de  { $concepts }
?item wdt:P1511 ?de
}
SPARQL;
	}

	public static function getResults( string $query ) {
		$configFactory =
			MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'wgLinkedWiki' );
		$configDefault = $configFactory->get( "SPARQLServiceByDefault" );
		$arrEndpoint = ToolsParser::newEndpoint( $configDefault, null );
		$sp = $arrEndpoint["endpoint"];
		$rs = $sp->query( $query );
		if ( !$rs ) {
			return [];
		} else {
			return $rs['result']['rows'];
		}
	}

	public static function getQueryForDoi( int $offset, int $limit ) {
		global $wgMathSearchPropertyDoi;
		return <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>
SELECT ?qid ?doi WHERE {
  BIND (REPLACE(STR(?item), "^.*/Q([^/]*)$", "$1") as ?qid) .
  ?item wdt:P$wgMathSearchPropertyDoi ?doi .
  FILTER REGEX(?doi, "[a-z]")
}
LIMIT $limit
OFFSET $offset
SPARQL;
	}
}
