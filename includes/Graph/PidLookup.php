<?php

namespace MediaWiki\Extension\MathSearch\Graph;

use Exception;
use MediaWiki\Sparql\SparqlException;

class PidLookup {

	private array $qid_cache = [];
	private string $pid;

	public function __construct( $pid = 'P1451' ) {
		$this->pid = $pid;
	}

	/**
	 * @param string $de
	 * @param bool $onlyCache
	 * @return void
	 * @throws SparqlException
	 * @throws Exception
	 */
	private function de2q( string $de, bool $onlyCache = false ): string {
		if ( !isset( $this->qid_cache[$de] ) ) {
			$this->qid_cache[$de] = false;
			if ( !$onlyCache ) {
				$this->requestDes();
			}
		}
		return $this->qid_cache[$de];
	}

	public function getQ( string $key ): string {
		return $this->de2q( $key );
	}

	public function warmup( $rows ): void {
		foreach ( $rows as $seed => $row ) {
			$this->de2q( $seed, true );
			foreach ( $row as $de => $value ) {
				$this->de2q( $de, true );
			}
		}
	}

	/**
	 * @throws SparqlException
	 */
	private function requestDes(): void {
		$rows = array_filter( $this->qid_cache, static function ( $qid ) {
			return $qid == false;
		} );
		$map = Query::getDeQIdMap( $rows, $this->pid );
		$this->qid_cache = $map + $this->qid_cache;
	}

}
