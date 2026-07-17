<?php

namespace MediaWiki\Extension\MathSearch\Wikidata;

use MediaWiki\Config\Config;
use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use Wikibase\Repo\Content\EntityContent;
use Wikibase\Repo\Hooks\WikibaseEditFilterMergedContentHook;

/**
 * Blocks local edits to configured low-numbered Wikibase items and properties.
 */
readonly class ProtectUpstreamEntitiesHook implements WikibaseEditFilterMergedContentHook {
	public function __construct(
		private Config $config
	) {
	}

	/**
	 * Prevent saving protected P/Q entities and direct editors to the upstream wiki instead.
	 *
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 * @param string $slotRole
	 * @return bool
	 */
	public function onEditFilterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit,
		string $slotRole = SlotRecord::MAIN
	): bool {
		if ( !$content instanceof EntityContent || $content->isRedirect() ) {
			return true;
		}

		$id = $content->getEntity()->getId();
		if ( $id === null ) {
			return true;
		}

		$serialization = $id->getSerialization();
		$prefix = $serialization[0] ?? '';
		if ( $prefix !== 'P' && $prefix !== 'Q' ) {
			return true;
		}

		$minimumEditableIds = $this->config->get( 'MathSearchMinimumEditableIds' );
		$minimumId = (int)( $minimumEditableIds[$prefix] ?? 0 );
		$numericId = (int)substr( $serialization, 1 );
		if ( $minimumId <= 0 || $numericId <= 0 || $numericId >= $minimumId ) {
			return true;
		}

		$upstreamUrl = rtrim( (string)$this->config->get( 'MathSearchWikidataUrl' ), '/' );
		$title = $prefix === 'P' ? "Property:{$serialization}" : "Item:{$serialization}";
		$status->fatal(
			'mathsearch-protected-entity-edit',
			$prefix === 'P' ? 'Property' : 'Item',
			$serialization,
			$minimumId,
			"{$upstreamUrl}/wiki/{$title}"
		);

		return true;
	}
}
