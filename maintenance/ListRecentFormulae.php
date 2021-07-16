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

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class ListRecentFormulae extends Maintenance {

	private const DEFAULT_URL = "https://stream.wikimedia.org/v2/stream/mediawiki.recentchange";
	private const END_OF_MESSAGE = "\n";
	private const DEFAULT_WIKIS = [ "enwiki" ];
	private const DEFAULT_NAMESPACES = [ 0 ];
	private const DEFAULT_MAX_EVENT_LAG = 300;
	private const PROGRESS_INTERVAL = 1000;
	private const RECONNECT_DELAY = 1;
	private const USER_AGENT = 'MathSearch/0.2 (https://www.mediawiki.org/wiki/Extension:MathSearch)';

	/** @var string[] */
	private $wikis;

	/** @var int[] */
	private $namespaces;

	/** @var string */
	private $data = '';

	/**
	 * @var Client
	 */
	private $client;

	/** @var int */
	private $maxEventLag = self::DEFAULT_MAX_EVENT_LAG;

	/** @var string|null */
	private $loginUsername;

	/** @var string|null */
	private $loginPassword;

	/** @var array<string,true> */
	private $loggedInHosts = [];

	/** @var int */
	private $messagesConsumed = 0;

	/** @var int */
	private $matchingEdits = 0;

	/** @var int */
	private $revisionsInspected = 0;

	/** @var int */
	private $revisionsWithMath = 0;

	/** @var int */
	private $mathChanges = 0;

	/** @var int */
	private $errors = 0;

	/** @var string|null */
	private $lastEventId;

	/** @var string|null */
	private $pendingEventId;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Lists recent formula changes from EventStream.' );
		$this->addOption( 'entrypoint', 'URL of recent-changes endpoint.', false, true, 'u' );
		$this->addOption( 'wikis', 'wiki code to be fetched of recent-changes endpoint.', false,
			true, 'w', true );
		$this->addOption( 'namespaces', 'namespace codes to be considered.', false, true, 'n',
			true );
		$this->addOption(
			'login-user',
			'Username for optional API login. Prefer a bot-password style username.',
			false,
			true
		);
		$this->addOption(
			'login-password',
			'Password for optional API login. Prefer using the MATHSEARCH_LOGIN_PASSWORD env var.',
			false,
			true
		);
		$this->addOption(
			'maxlag',
			'Maximum allowed event lag in seconds before stopping after rate limiting.',
			false,
			true,
			'l'
		);
	}

	/**
	 * Fetch both revisions and report the page, diff, and changed math fragments.
	 *
	 * @param string $serverUrl
	 * @param int|null $oldid
	 * @param int $newid
	 * @param string $title
	 * @param string|null $titleUrl
	 * @param string|null $notifyUrl
	 * @param int|null $eventTimestamp
	 */
	public function getDiff(
		$serverUrl,
		$oldid,
		$newid,
		$title,
		$titleUrl = null,
		$notifyUrl = null,
		$eventTimestamp = null
	) {
		if ( $oldid === null ) {
			return;
		}

		$newHtml = $this->fetchRevisionHtml(
			$serverUrl,
			$title,
			$newid,
			$eventTimestamp
		);
		$oldHtml = $this->fetchRevisionHtml(
			$serverUrl,
			$title,
			$oldid,
			$eventTimestamp
		);
		if ( $oldHtml === null && $newHtml === null ) {
			return;
		}

		$oldMath = $oldHtml === null ? [] : $this->extractMathFragments( $oldHtml );
		$newMath = $newHtml === null ? [] : $this->extractMathFragments( $newHtml );
		$addedMath = array_values( array_diff( $newMath, $oldMath ) );
		$removedMath = array_values( array_diff( $oldMath, $newMath ) );
		if ( !$addedMath && !$removedMath ) {
			return;
		}
		$this->mathChanges++;

		$pageTitle = str_replace( ' ', '_', $title );
		$serverUrl = rtrim( $serverUrl, '/' );
		$diffUrl = $notifyUrl ?? "{$serverUrl}/w/index.php?title={$pageTitle}&oldid={$oldid}&diff={$newid}";
		$pageUrl = $titleUrl ?? "{$serverUrl}/wiki/{$pageTitle}";
		$summary = [];
		if ( $addedMath ) {
			$summary[] = '  added:   ' . $this->formatMathFragments( $addedMath );
		}
		if ( $removedMath ) {
			$summary[] = '  removed: ' . $this->formatMathFragments( $removedMath );
		}
		$this->output(
			"{$title}\n"
			. "  page: {$pageUrl}\n"
			. "  diff: {$diffUrl}\n"
			. implode( "\n", $summary )
			. "\n\n"
		);
	}

	/**
	 * Fetch one revision as HTML and ignore pages without math.
	 *
	 * @param string $serverUrl
	 * @param string $title
	 * @param int $revisionId
	 * @param int|null $eventTimestamp
	 * @return string|null
	 */
	private function fetchRevisionHtml( $serverUrl, $title, $revisionId, $eventTimestamp = null ) {
		$requestTitle = rawurlencode( $title );
		$serverUrl = rtrim( $serverUrl, '/' );
		$this->ensureLoggedIn( $serverUrl );
		$url = "{$serverUrl}/api/rest_v1/page/html/{$requestTitle}/{$revisionId}";
		while ( true ) {
			try {
				$res = $this->client->request( 'GET', $url, [ 'followRedirects' => true ] );
				$body = (string)$res->getBody();
				$this->revisionsInspected++;
				if ( $body === '' || strpos( $body, "</math>" ) === false ) {
					return null;
				}
				$this->revisionsWithMath++;
				return $body;
			} catch ( ClientException $e ) {
				if ( $e->getResponse() === null || $e->getResponse()->getStatusCode() !== 429 ) {
					throw $e;
				}

				$retryAfter = $this->getRetryAfterSeconds( $e );
				$lag = $this->getEventLag( $eventTimestamp ) + $retryAfter;
				$this->output(
					"Rate limited for {$retryAfter}s while fetching {$title} revision {$revisionId}.\n"
				);
				if ( $lag > $this->maxEventLag ) {
					throw new RuntimeException(
						"Stopping because event lag would reach {$lag}s (max {$this->maxEventLag}s)."
					);
				}

				$this->sleepSeconds( $retryAfter );
			}
		}
	}

	/**
	 * Log in to one wiki host if credentials were configured.
	 *
	 * @param string $serverUrl
	 */
	private function ensureLoggedIn( $serverUrl ): void {
		if ( !$this->loginUsername || !$this->loginPassword ) {
			return;
		}

		$serverUrl = rtrim( $serverUrl, '/' );
		if ( isset( $this->loggedInHosts[$serverUrl] ) ) {
			return;
		}

		$apiUrl = "{$serverUrl}/w/api.php";
		$tokenResponse = $this->client->request( 'GET', $apiUrl, [
			'query' => [
				'action' => 'query',
				'meta' => 'tokens',
				'type' => 'login',
				'format' => 'json',
			],
		] );
		$tokenData = json_decode( (string)$tokenResponse->getBody() );
		$loginToken = $tokenData->query->tokens->logintoken ?? null;
		if ( !is_string( $loginToken ) || $loginToken === '' ) {
			throw new RuntimeException( "Could not get login token for {$serverUrl}." );
		}

		$loginResponse = $this->client->request( 'POST', $apiUrl, [
			'form_params' => [
				'action' => 'login',
				'lgname' => $this->loginUsername,
				'lgpassword' => $this->loginPassword,
				'lgtoken' => $loginToken,
				'format' => 'json',
			],
		] );
		$loginData = json_decode( (string)$loginResponse->getBody() );
		$loginResult = $loginData->login->result ?? null;
		if ( $loginResult !== 'Success' ) {
			throw new RuntimeException(
				"Login failed for {$serverUrl}: " . ( is_string( $loginResult ) ? $loginResult : 'unknown result' )
			);
		}

		$this->loggedInHosts[$serverUrl] = true;
	}

	/**
	 * Extract distinct math snippets from Parsoid HTML.
	 *
	 * @param string $html
	 * @return string[]
	 */
	private function extractMathFragments( $html ) {
		$dom = new DOMDocument();
		$oldUseErrors = libxml_use_internal_errors( true );
		$dom->loadHTML( $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $oldUseErrors );

		$fragments = [];
		$xpath = new DOMXPath( $dom );
		foreach ( $xpath->query( '//math' ) as $mathNode ) {
			$fragment = '';
			if ( $mathNode->attributes !== null && $mathNode->attributes->getNamedItem( 'alttext' ) ) {
				$fragment = $mathNode->attributes->getNamedItem( 'alttext' )->nodeValue;
			}
			if ( $fragment === '' ) {
				$fragment = trim( preg_replace( '/\s+/', ' ', $mathNode->textContent ) );
			}
			if ( $fragment !== '' ) {
				$fragments[$fragment] = $fragment;
			}
		}

		return array_values( $fragments );
	}

	/**
	 * Format at most three changed math snippets for terminal output.
	 *
	 * @param string[] $fragments
	 * @return string
	 */
	private function formatMathFragments( $fragments ) {
		$fragments = array_slice( $fragments, 0, 3 );
		$fragments = array_map(
			static fn ( $fragment ) => '"' . str_replace( "\n", ' ', $fragment ) . '"',
			$fragments
		);
		return implode( ', ', $fragments );
	}

	/**
	 * Read revision ids from the event payload or recover them from the diff URL.
	 *
	 * @param stdClass $json
	 * @param string|null $notifyUrl
	 * @return array{0:int|null,1:int|null}
	 */
	private function getRevisionIds( $json, $notifyUrl ) {
		$oldid = $json->revision->old ?? null;
		$newid = $json->revision->new ?? null;
		if ( $oldid !== null && $newid !== null ) {
			return [ $oldid, $newid ];
		}
		if ( $notifyUrl === null ) {
			return [ $oldid, $newid ];
		}

		$query = parse_url( $notifyUrl, PHP_URL_QUERY );
		if ( !is_string( $query ) ) {
			return [ $oldid, $newid ];
		}

		parse_str( $query, $params );
		return [
			$oldid ?? ( $params['oldid'] ?? null ),
			$newid ?? ( $params['diff'] ?? null ),
		];
	}

	/**
	 * Determine the delay requested by a 429 response.
	 *
	 * @param ClientException $e
	 * @return int
	 */
	private function getRetryAfterSeconds( ClientException $e ) {
		$retryAfter = $e->getResponse()->getHeaderLine( 'Retry-After' );
		if ( ctype_digit( $retryAfter ) ) {
			return max( 1, (int)$retryAfter );
		}

		return 5;
	}

	/**
	 * Return the lag in seconds for the event currently being processed.
	 *
	 * @param int|null $eventTimestamp
	 * @return int
	 */
	private function getEventLag( $eventTimestamp ) {
		if ( $eventTimestamp === null ) {
			return 0;
		}

		return max( 0, $this->getCurrentUnixTime() - (int)$eventTimestamp );
	}

	/**
	 * @return int
	 */
	protected function getCurrentUnixTime() {
		return time();
	}

	/**
	 * @param int $seconds
	 */
	protected function sleepSeconds( $seconds ): void {
		sleep( $seconds );
	}

	/**
	 * Report enough counters to distinguish quiet periods from request failures.
	 */
	private function reportProgress(): void {
		$this->output(
			"Progress: {$this->messagesConsumed} messages, {$this->matchingEdits} matching edits, "
			. "{$this->revisionsInspected} revisions inspected, {$this->revisionsWithMath} with math, "
			. "{$this->mathChanges} math changes, {$this->errors} errors.\n"
		);
	}

	/**
	 * Consume one chunk from the server-sent event stream.
	 *
	 * @param string $content
	 * @return int
	 */
	public function onData( $content ): int {
		$this->data .= $content;
		$parts = explode( self::END_OF_MESSAGE, $this->data );
		$this->data = (string)array_pop( $parts );
		foreach ( $parts as $part ) {
			if ( str_starts_with( $part, "id:" ) ) {
				$this->pendingEventId = trim( substr( $part, 3 ) );
				continue;
			}
			if ( !str_starts_with( $part, "data:" ) ) {
				continue;
			}
			$this->messagesConsumed++;
			try {
				$json = json_decode( substr( $part, 5 ) );
				if ( !$json instanceof stdClass ) {
					continue;
				}
				$wiki = $json->wiki ?? null;
				$titleUrl = $json->title_url ?? null;
				$notifyUrl = $json->notify_url ?? null;
				$serverUrl = $json->server_url ?? null;
				$type = $json->type ?? null;
				$namespace = $json->namespace ?? null;
				$eventTimestamp = $json->timestamp ?? null;
				[ $oldid, $newid ] = $this->getRevisionIds( $json, $notifyUrl );
				$title = $json->title ?? null;
				if (
					$type === "edit" && $serverUrl !== null && $newid !== null && $title !== null &&
					in_array( $wiki, $this->wikis ) &&
					in_array( $namespace, $this->namespaces )
				) {
					$this->matchingEdits++;
					$this->getDiff(
						$serverUrl,
						$oldid,
						$newid,
						$title,
						$titleUrl,
						$notifyUrl,
						$eventTimestamp
					);
				}
			} catch ( Throwable $e ) {
				$this->errors++;
				$this->output( "{$e->getMessage()}\n{$content}\n" );
			} finally {
				if ( $this->pendingEventId !== null ) {
					$this->lastEventId = $this->pendingEventId;
				}
				$this->pendingEventId = null;
				if ( $this->messagesConsumed % self::PROGRESS_INTERVAL === 0 ) {
					$this->reportProgress();
				}
			}
		}

		return strlen( $content );
	}

	/**
	 * Consume the stream continuously, resuming after disconnects.
	 */
	public function execute() {
		$url = $this->getOption( 'entrypoint', self::DEFAULT_URL );
		$this->loginUsername = $this->getOption( 'login-user', getenv( 'MATHSEARCH_LOGIN_USER' ) ?: null );
		$this->loginPassword = $this->getOption( 'login-password', getenv( 'MATHSEARCH_LOGIN_PASSWORD' ) ?: null );
		$this->maxEventLag = (int)$this->getOption( 'maxlag', self::DEFAULT_MAX_EVENT_LAG );
		$this->wikis = $this->getOption( 'wikis', self::DEFAULT_WIKIS );
		$this->namespaces = array_map(
			'intval',
			$this->getOption( 'namespaces', self::DEFAULT_NAMESPACES )
		);
		$this->client = new Client( [
			'cookies' => new CookieJar(),
			'headers' => [
				'Accept' => 'text/event-stream',
				'User-Agent' => self::USER_AGENT,
			],
		] );
		$this->output( "Initialized\n" );
		while ( true ) {
			$options = [ 'stream' => true ];
			if ( $this->lastEventId !== null ) {
				$options['headers']['Last-Event-ID'] = $this->lastEventId;
			}
			try {
				$response = $this->client->request( 'GET', $url, $options );
				$body = $response->getBody();
				while ( !$body->eof() ) {
					$this->onData( $body->read( 1024 ) );
				}
			} catch ( GuzzleException | RuntimeException $e ) {
				$this->output( "Stream disconnected: {$e->getMessage()}\n" );
			}
			$this->reportProgress();
			$this->output( "Reconnecting in " . self::RECONNECT_DELAY . "s.\n" );
			$this->sleepSeconds( self::RECONNECT_DELAY );
		}
	}
}

$maintClass = ListRecentFormulae::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
