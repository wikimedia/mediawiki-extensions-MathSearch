<?php

namespace MediaWiki\Extension\MathSearch\Graph;

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
	 * @return string|false
	 * @throws SparqlException
	 */
	private function de2q( string $de, bool $onlyCache = false ): string|false {
		if ( !isset( $this->qid_cache[$de] ) ) {
			$this->qid_cache[$de] = false;
			if ( !$onlyCache ) {
				$this->requestDes();
			}
		}
		return $this->qid_cache[$de];
	}

	public function getQ( string $key ): string|false {
		return $this->de2q( $key );
	}

	public function warmupFromKeys( array $rows ): void {
		foreach ( $rows as $seed => $row ) {
			$this->de2q( $seed, true );
			foreach ( $row as $de => $value ) {
				$this->de2q( $de, true );
			}
		}
		$this->requestDes();
	}

	/**
	 * Warms up the cache with the given values.
	 *
	 * @param string[] $values The values to warm up the cache with.
	 * @return void
	 * @throws SparqlException
	 */
	public function warmupFromValues( array $values ): void {
		foreach ( $values as $value ) {
			$this->de2q( $value, true );
		}
		$this->requestDes();
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

	public function count(): int {
		return count( $this->qid_cache );
	}

	public function overwrite( string $k, string $v ): void {
		$this->qid_cache[ $k ] = $v;
	}
}
