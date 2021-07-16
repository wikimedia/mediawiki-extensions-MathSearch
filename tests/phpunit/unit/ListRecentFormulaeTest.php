<?php

namespace MediaWiki\Extension\MathSearch\Tests\Unit;

require_once dirname( __DIR__, 3 ) . '/maintenance/ListRecentFormulae.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use MediaWikiUnitTestCase;

/**
 * @covers \ListRecentFormulae
 */
class ListRecentFormulaeTest extends MediaWikiUnitTestCase {
	public function testOnDataDispatchesEditPayloadWithRevisionIds(): void {
		$script = new RecordingListRecentFormulae();
		$this->setPrivateProperty( $script, 'wikis', [ 'nnwiki' ] );
		$this->setPrivateProperty( $script, 'namespaces', [ 0 ] );

		$content = $this->wrapSseData( $this->newEditPayload( [
			'meta' => [
				'uri' => 'https://nn.wikipedia.org/wiki/John_Taverner',
				'request_id' => 'e44742df-c2b6-4447-a8b4-e398dbb44ad8',
				'id' => 'fb9f8652-e6e8-49a4-b0bd-e98d33047e4b',
				'domain' => 'nn.wikipedia.org',
				'dt' => '2026-07-19T09:57:17.307Z',
				'offset' => 6343297942,
			],
			'id' => 18016688,
			'wiki' => 'nnwiki',
			'title' => 'John Taverner',
			'title_url' => 'https://nn.wikipedia.org/wiki/John_Taverner',
			'notify_url' => 'https://nn.wikipedia.org/w/index.php?diff=3662664&oldid=3662663&rcid=18016688',
			'minor' => true,
			'patrolled' => true,
			'length' => [ 'old' => 3685, 'new' => 3708 ],
			'revision' => [ 'old' => 3662663, 'new' => 3662664 ],
			'server_url' => 'https://nn.wikipedia.org',
			'server_name' => 'nn.wikipedia.org',
			'wiki_user' => 'Ranveig',
		] ) );
		$consumed = $script->onData( $content );

		$this->assertSame( strlen( $content ), $consumed );
		$this->assertSame(
			[
				'https://nn.wikipedia.org',
				3662663,
				3662664,
				'John Taverner',
				'https://nn.wikipedia.org/wiki/John_Taverner',
				'https://nn.wikipedia.org/w/index.php?diff=3662664&oldid=3662663&rcid=18016688',
			],
			$script->capturedDiffCall
		);
	}

	public function testOnDataIgnoresEditOutsideConfiguredNamespacesAndWikis(): void {
		$script = new RecordingListRecentFormulae();
		$this->setPrivateProperty( $script, 'wikis', [ 'enwiki' ] );
		$this->setPrivateProperty( $script, 'namespaces', [ 0 ] );

		$script->onData( $this->wrapSseData( $this->newEditPayload( [
			'meta' => [
				'uri' => 'https://commons.wikimedia.org/wiki/File:Portrait_of_Taran_Chowdhury_(2026).jpg',
				'request_id' => '7d5541a5-0baa-4cf5-b463-f8cfcdf96797',
				'id' => '9b5a4621-401a-4dd9-85e0-6f802206444b',
				'domain' => 'commons.wikimedia.org',
				'dt' => '2026-07-19T09:57:17.400Z',
				'offset' => 6343297943,
			],
			'id' => 3398713059,
			'namespace' => 6,
			'title' => 'File:Portrait of Taran Chowdhury (2026).jpg',
			'title_url' => 'https://commons.wikimedia.org/wiki/File:Portrait_of_Taran_Chowdhury_(2026).jpg',
			'comment' => '/* wbeditentity-update-languages-and-other-short:0||en */',
			// phpcs:ignore Generic.Files.LineLength
			'notify_url' => 'https://commons.wikimedia.org/w/index.php?diff=1249139928&oldid=1249139913&rcid=3398713059',
			'length' => [ 'old' => 429, 'new' => 1584 ],
			'revision' => [ 'old' => 1249139913, 'new' => 1249139928 ],
			'server_url' => 'https://commons.wikimedia.org',
			'server_name' => 'commons.wikimedia.org',
			'wiki' => 'commonswiki',
			'wiki_user' => 'Mishu Tiwari',
			'parsedcomment' => 'Changed label, description and/or aliases in en, and other parts',
		] ) ) );

		$this->assertNull( $script->capturedDiffCall );
	}

	public function testProgressIsReportedEveryThousandMessages(): void {
		$script = new OutputRecordingListRecentFormulae();
		$this->setPrivateProperty( $script, 'wikis', [ 'enwiki' ] );
		$this->setPrivateProperty( $script, 'namespaces', [ 0 ] );
		$this->setPrivateProperty( $script, 'messagesConsumed', 999 );

		$script->onData( $this->wrapSseData( $this->newEditPayload( [
			'wiki' => 'dewiki',
		] ) ) );

		$this->assertStringContainsString(
			'Progress: 1000 messages, 0 matching edits, 0 revisions inspected',
			$script->output
		);
	}

	public function testReportsRemovalOfLastFormula(): void {
		$script = new OutputRecordingListRecentFormulae();
		$mockHandler = new MockHandler( [
			new Response( 200, [], '<html><body>No formula</body></html>' ),
			new Response( 200, [], '<html><body><math alttext="{x}"></math></body></html>' ),
		] );
		$this->setPrivateProperty( $script, 'client', new Client( [ 'handler' => $mockHandler ] ) );

		$script->getDiff( 'https://en.wikipedia.org', 1, 2, 'Test' );

		$this->assertStringContainsString( 'removed: "{x}"', $script->output );
		$this->assertSame( 0, $mockHandler->count() );
	}

	private function wrapSseData( string $json ): string {
		return "event: message\nid: []\ndata: {$json}\n";
	}

	private function newEditPayload( array $overrides ): string {
		$payload = array_replace_recursive(
			[
				'$schema' => '/mediawiki/recentchange/1.0.0',
				'meta' => [
					'uri' => 'https://example.org/wiki/Test',
					'request_id' => 'request-id',
					'id' => 'event-id',
					'domain' => 'example.org',
					'stream' => 'mediawiki.recentchange',
					'dt' => '2026-07-19T09:57:17.307Z',
					'topic' => 'eqiad.mediawiki.recentchange',
					'partition' => 0,
					'offset' => 0,
				],
				'id' => 1,
				'type' => 'edit',
				'namespace' => 0,
				'title' => 'Test',
				'title_url' => 'https://example.org/wiki/Test',
				'comment' => '',
				'timestamp' => 1784455036,
				'user' => $overrides['wiki_user'] ?? 'User',
				'bot' => false,
				'notify_url' => 'https://example.org/w/index.php?diff=2&oldid=1&rcid=1',
				'minor' => false,
				'patrolled' => false,
				'length' => [ 'old' => 1, 'new' => 2 ],
				'revision' => [ 'old' => 1, 'new' => 2 ],
				'server_url' => 'https://example.org',
				'server_name' => 'example.org',
				'server_script_path' => '/w',
				'wiki' => 'examplewiki',
				'parsedcomment' => '',
			],
			$overrides
		);

		unset( $payload['wiki_user'] );
		return json_encode( $payload, JSON_UNESCAPED_SLASHES );
	}

	private function setPrivateProperty( object $object, string $property, $value ): void {
		$reflection = new \ReflectionProperty( \ListRecentFormulae::class, $property );
		$reflection->setValue( $object, $value );
	}
}

class RecordingListRecentFormulae extends \ListRecentFormulae {
	public ?array $capturedDiffCall = null;

	public function getDiff(
		$serverUrl,
		$oldid,
		$newid,
		$title,
		$titleUrl = null,
		$notifyUrl = null,
		$eventTimestamp = null
	) {
		$this->capturedDiffCall = [ $serverUrl, $oldid, $newid, $title, $titleUrl, $notifyUrl ];
	}
}

class OutputRecordingListRecentFormulae extends \ListRecentFormulae {
	public string $output = '';

	protected function output( $out, $channel = null ) {
		$this->output .= $out;
	}
}
