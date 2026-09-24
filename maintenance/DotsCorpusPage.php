#!/usr/bin/env php
<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @ingroup Maintenance
 */

use MediaWiki\Extension\Math\WikiTexVC\Nodes\Big;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\DQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\FQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun1;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun2;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Literal;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Lr;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexNode;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\UQ;
use MediaWiki\Extension\Math\WikiTexVC\TexUtil;
use MediaWiki\Extension\Math\WikiTexVC\TexVC;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Wikitext report of every \dots in a formula corpus, grouped by the next token
 * and the code path through TexArray::checkForDots.
 */
class DotsCorpusPage extends Maintenance {
	private const CORPUS = 'User:Texvc2LaTeXBot/Corpus_dots_1';
	private const AMSMATH = 'https://archive.softwareheritage.org/swh:1:cnt:e05c33e5d589cd1cb1ab6d74840bb02ea6f08273;'
		. 'origin=https://github.com/latex3/latex2e;visit=swh:1:snp:0d7220eaad70408fcc65d952ca5b0490c0198a81;'
		. 'anchor=swh:1:rev:37156372ade4d0aee1bf66f6fb1300dbae2f3aa7;'
		. 'path=/required/amsmath/amsmath.dtx;lines=1080-1253';
	private const SCRIPT = 'https://gerrit.wikimedia.org/r/q/I79b8878a33d5c8528c7717e6834c072924853d1b';
	private const EXPANSION = [
		'dotsb' => '\\dotsb@ → \\@cdots',
		'dotsi' => '\\dotsi → \\!\\@cdots',
		'rightdelim' => '\\dotso@ → \\@ldots\\,',
	];

	/** @var array<string,int> occurrences per "token\tpath\tresult" */
	private array $count = [];
	/** @var array<string,string> shortest formula per key */
	private array $example = [];

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Print a wikitext report of the \\dots cases in an enwiki formula corpus' );
		$this->addOption( 'corpus', 'enwiki page listing the formulae', false, true );
		$this->requireExtension( 'Math' );
	}

	public function execute() {
		$corpus = $this->getOption( 'corpus', self::CORPUS );
		$raw = $this->getServiceContainer()->getHttpRequestFactory()->get(
			'https://en.wikipedia.org/w/index.php?action=raw&title=' . $corpus );
		preg_match_all( '/<math[^>]*>(.*?)<\/math>/s', $raw, $m );
		$texvc = new TexVC();
		foreach ( $m[1] as $tex ) {
			$tex = trim( str_replace( "\n", ' ', $tex ) );
			$r = $texvc->check( $tex );
			if ( $r['status'] === '+' ) {
				$this->walk( $r['input'], false, $tex );
			}
		}
		$this->output( $this->page( $corpus, count( $m[1] ) ) );
	}

	/**
	 * @param TexNode|string $node
	 * @param bool $beforeRight
	 * @param string $tex
	 */
	private function walk( $node, bool $beforeRight, string $tex ): void {
		if ( !$node instanceof TexNode ) {
			return;
		}
		if ( $node instanceof TexArray ) {
			$args = $node->getArgs();
			foreach ( $args as $i => $cur ) {
				$next = $args[$i + 1] ?? null;
				$result = $node->checkForDots( $cur, $next, $beforeRight );
				if ( $result === null ) {
					$this->walk( $cur, false, $tex );
					continue;
				}
				$key = implode( "\t", [ ...$this->classify( $next, $beforeRight ), $result ] );
				$this->count[$key] = ( $this->count[$key] ?? 0 ) + 1;
				if ( !isset( $this->example[$key] ) || strlen( $tex ) < strlen( $this->example[$key] ) ) {
					$this->example[$key] = $tex;
				}
			}
			return;
		}
		foreach ( $node->getArgs() as $arg ) {
			$this->walk( $arg, $node instanceof Lr && $arg instanceof TexArray, $tex );
		}
	}

	/** Mirrors the branches of TexArray::checkForDots. */
	private function classify( ?TexNode $next, bool $beforeRight ): array {
		if ( $next === null ) {
			return $beforeRight ? [ '\\right', 'end of \\left..\\right array' ] : [ '<end>', 'end of array' ];
		}
		$prefix = '';
		if ( $next instanceof DQ || $next instanceof FQ || $next instanceof UQ ) {
			$next = $next->getBase();
			$prefix = 'base of sub/sup, ';
		}
		if ( $next instanceof Literal ) {
			$token = trim( $next->getArg() );
			$kind = 'Literal';
		} elseif ( $next instanceof Big || $next instanceof Fun1 || $next instanceof Fun2 ) {
			$token = trim( $next->getFname() );
			$kind = ( $next instanceof Big ? 'Big' : ( $next instanceof Fun1 ? 'Fun1' : 'Fun2' ) ) . ' fname';
		} else {
			$class = ( new ReflectionClass( $next ) )->getShortName();
			return [ $class, $prefix . 'other node ' . $class ];
		}
		$data = TexUtil::getInstance()->dots_lookahead( $token ) ? 'dots_lookahead' : 'no dots_lookahead';
		return [ $token, "$prefix$kind, $data" ];
	}

	private static function expansion( string $token, string $result ): string {
		return self::EXPANSION[$result] ?? match ( $token ) {
			',' => '\\dotsc → \\@ldots',
			'<end>' => '\\dotso@ → \\@ldots (\\@ldots\\, before $)',
			default => '\\dotso@ → \\@ldots',
		};
	}

	private static function nw( string $text ): string {
		return "<code><nowiki>$text</nowiki></code>";
	}

	private function row( int $id, string $key, string $count ): array {
		[ $token, $path, $result ] = explode( "\t", $key );
		return [ '|-', "| id=\"$id\" | $id", '| ' . self::nw( $token ), "| $count", "| <nowiki>$path</nowiki>",
			'| ' . self::nw( self::expansion( $token, $result ) ), '| ' . self::nw( $this->example[$key] ) ];
	}

	private function page( string $corpus, int $formulae ): string {
		$byPath = [];
		foreach ( $this->count as $key => $n ) {
			$byPath[explode( "\t", $key, 2 )[1]][$key] = $n;
		}
		$total = array_map( 'array_sum', $byPath );
		arsort( $total );
		// The most frequent token of a code path is its tested row.
		$heads = [];
		foreach ( array_keys( $total ) as $path ) {
			arsort( $byPath[$path] );
			$heads[$path] = array_key_first( $byPath[$path] );
		}
		$rest = array_diff_key( $this->count, array_flip( $heads ) );
		arsort( $rest );

		$header = '! # !! Next token !! Count !! Code path !! amsmath expansion !! Example';
		$out = [
			'= \\dots by next token: enwiki corpus (T438388) =', '',
			"All $formulae formulae of [[:en:$corpus]], grouped by the token right after "
			. '<code>\\dots</code>. The token and code path come from the WikiTexVC parse tree, the result from '
			. '<code>TexArray::checkForDots</code>. The expansion column quotes <code>\\mdots@@</code> in ['
			. self::AMSMATH . ' amsmath.dtx]; WikiTexVC should render each row like it.', '',
			'== Tested cases ==',
			'One row per code path, texutil.json lookup included. The count in brackets includes '
			. 'the rows in the second table that link here.', '',
			'{| class="wikitable"', "$header !! Native MathML !! Client MathJax !! Server-side MathJax",
		];
		$id = 0;
		$headIds = [];
		foreach ( $heads as $path => $key ) {
			$headIds[$path] = ++$id;
			array_push( $out, ...$this->row( $id, $key, "{$this->count[$key]} ({$total[$path]})" ) );
			foreach ( [ 'native', 'mathjax', 'mathml' ] as $mode ) {
				$out[] = "| <math forcemathmode=\"$mode\">{$this->example[$key]}</math>";
			}
		}
		array_push( $out, '|}', '', '== Same code path ==', '', '{| class="wikitable"',
			"$header !! Same code path as" );
		foreach ( $rest as $key => $n ) {
			$head = $headIds[explode( "\t", $key, 2 )[1]];
			array_push( $out, ...$this->row( ++$id, $key, (string)$n ) );
			$out[] = "| [[#$head|#$head]]";
		}
		array_push( $out, '|}', '', '== Checks ==',
			'* Native MathML matches the amsmath expansion in every tested row: <code>\\@cdots</code> is ⋯, '
			. '<code>\\@ldots</code> is …, <code>\\,</code> and <code>\\!</code> are ±0.167em.',
			'* Client MathJax is the reference for centred vs low; it ignores the thin space.', '',
			'== Code ==', 'Generated by [' . self::SCRIPT . ' maintenance/DotsCorpusPage.php] in MathSearch.' );
		return implode( "\n", $out ) . "\n";
	}
}

$maintClass = DotsCorpusPage::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
