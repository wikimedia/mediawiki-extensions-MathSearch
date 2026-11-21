<?php

namespace MediaWiki\Extension\MathSearch\Specials;

use GuzzleHttp\Exception\GuzzleException;
use MediaWiki\Extension\MathSearch\Ipfs\IpfsClient;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;

/**
 * Adds a file to the IPFS node of the wiki.
 */
class SpecialIpfsUpload extends FormSpecialPage {

	private string $cid = '';

	public function __construct( private readonly IpfsClient $ipfs ) {
		parent::__construct( 'IpfsUpload' );
	}

	public function getRestriction(): string {
		return 'mathtrustedkeys';
	}

	/** @inheritDoc */
	protected function getFormFields() {
		return [
			'file' => [
				'type' => 'file',
				'label-message' => 'ipfsupload-file',
				'required' => true,
			],
		];
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/** @inheritDoc */
	public function onSubmit( array $data ) {
		$upload = $this->getRequest()->getUpload( 'wpfile' );
		if ( !$upload->exists() ) {
			return Status::newFatal( 'ipfsupload-nofile' );
		}
		try {
			$this->cid = $this->ipfs->add( $upload->getTempName(), $upload->getName() );
		} catch ( GuzzleException $e ) {
			return Status::newFatal( 'ipfsupload-error', $e->getMessage() );
		}
		return true;
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'ipfsupload-done', $this->cid );
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'media';
	}
}
