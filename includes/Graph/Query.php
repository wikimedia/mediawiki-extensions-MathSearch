<?php

namespace MediaWiki\Extension\MathSearch\Graph;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Sparql\SparqlClient;
use MediaWiki\Sparql\SparqlException;
use Wikibase\Repo\WikibaseRepo;

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

	/**
	 * @throws SparqlException
	 */
	public static function getResults( string $query ): array {
		$endPoint = self::getEndpoint();
		if ( !$endPoint ) {
			throw new SparqlException( 'SPARQL endpoint not defined' );
		}
		$client = new SparqlClient( $endPoint, MediaWikiServices::getInstance()->getHttpRequestFactory() );
		$client->appendUserAgent( __CLASS__ );
		return $client->query( $query );
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

	public static function getQueryForWdId(): string {
		return <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>
PREFIX wikidata_wdt: <http://www.wikidata.org/prop/direct/>

SELECT DISTINCT
(REPLACE(STR(?mardi_item), ".*Q", "") AS ?qid)
(REPLACE(STR(?wikidata), ".*Q", "Q") AS ?P12)
WHERE {
  SERVICE bd:sample { ?mardi_item wdt:P27 ?doi . bd:serviceParam bd:sample.limit 10000 }
  BIND(UCASE(?doi) AS ?DOI)
  FILTER NOT EXISTS { ?mardi_item wdt:P12 ?WikidataQID }
  service <https://query.wikidata.org/sparql> {
    ?wikidata wikidata_wdt:P356 ?DOI
  }
}
SPARQL;
	}

	public static function getEndpoint(): mixed {
		$repoSettings = WikibaseRepo::getSettings();
		return $repoSettings->getSetting( 'sparqlEndpoint' );
	}

	/**
	 * @param array &$rows
	 * @return array<string,string>
	 * @throws SparqlException
	 */
	public static function getDeQIdMap( array &$rows ): array {
		$logger = LoggerFactory::getInstance( 'MathSearch' );

		$des = '"' . implode( '" "', array_keys( $rows ) ) . '"';
		$query = self::getQidFromDe( $des );
		$rs = self::getResults( $query );
		$qIdMap = [];
		foreach ( $rs as $row ) {
			$de = $row['de'];
			if ( isset( $qIdMap[$de] ) ) {
				$logger->error( "Multiple Qid found for Zbl $de." );
				unset( $rows[$de] );
				continue;
			}
			$qIdMap[$de] = $row['qid'];
		}

		return $qIdMap;
	}

}
