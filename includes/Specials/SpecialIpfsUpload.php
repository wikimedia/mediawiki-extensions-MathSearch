<?php

namespace MediaWiki\Extension\MathSearch\Specials;

use GuzzleHttp\Exception\GuzzleException;
use MediaWiki\Extension\MathSearch\Ipfs\Cid;
use MediaWiki\Extension\MathSearch\Ipfs\IpfsClient;
use MediaWiki\Extension\MathSearch\Keys\CidSignatures;
use MediaWiki\Extension\MathSearch\Keys\ManagedKeys;
use MediaWiki\Extension\MathSearch\Keys\SigningKey;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;

/**
 * Adds a file to the IPFS node of the wiki, signed with the user's managed key.
 */
class SpecialIpfsUpload extends FormSpecialPage {

	private string $cid = '';
	private string $key = '';

	public function __construct(
		private readonly IpfsClient $ipfs,
		private readonly ManagedKeys $managedKeys,
		private readonly CidSignatures $signatures
	) {
		parent::__construct( 'IpfsUpload' );
	}

	public function getRestriction(): string {
		return 'mathtrustedkeys';
	}

	/** @inheritDoc */
	protected function getFormFields() {
		$key = $this->managedKeys->getKey( $this->getUser() );
		return [
			'file' => [
				'type' => 'file',
				'label-message' => 'ipfsupload-file',
				'section' => 'section-file',
			],
			'key' => [
				'type' => 'info',
				'label-message' => 'ipfsupload-key',
				'default' => $key ? (string)$key : $this->msg( 'ipfsupload-nokey' )->text(),
				'section' => 'section-key',
			],
			'passphrase' => [
				'type' => 'password',
				'label-message' => 'ipfsupload-passphrase',
				'help-message' => 'ipfsupload-passphrase-help',
				'section' => 'section-key',
			],
			'newkey' => [
				'type' => 'check',
				'label-message' => 'ipfsupload-newkey',
				'section' => 'section-key',
			],
		];
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitTextMsg( 'ipfsupload-submit' );
		$form->addButton( [ 'name' => 'wpdownload', 'value' => '1', 'label-message' => 'ipfsupload-download' ] );
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/** @inheritDoc */
	public function onSubmit( array $data ) {
		$user = $this->getUser();
		$passphrase = (string)$data['passphrase'];
		if ( $this->getRequest()->getCheck( 'wpdownload' ) ) {
			$signingKey = $this->managedKeys->unlock( $user, $passphrase );
			return $signingKey ? $this->download( $signingKey ) : Status::newFatal( 'ipfsupload-badpassphrase' );
		}
		$upload = $this->getRequest()->getUpload( 'wpfile' );
		if ( !$upload->exists() ) {
			return Status::newFatal( 'ipfsupload-nofile' );
		}
		// A resubmitted form must not replace the key it just created.
		$signingKey = $this->managedKeys->unlock( $user, $passphrase );
		if ( !$signingKey ) {
			if ( !$data['newkey'] && $this->managedKeys->getKey( $user ) ) {
				return Status::newFatal( 'ipfsupload-badpassphrase' );
			}
			$signingKey = $this->managedKeys->create( $user, $passphrase );
		}
		try {
			$this->cid = $this->ipfs->add( $upload->getTempName(), $upload->getName() );
		} catch ( GuzzleException $e ) {
			return Status::newFatal( 'ipfsupload-error', $e->getMessage() );
		}
		$cid = Cid::fromString( $this->cid );
		if ( !$cid ) {
			return Status::newFatal( 'ipfsupload-error', "unexpected CID $this->cid" );
		}
		$this->signatures->add( $cid, $signingKey->getKey(), $signingKey->sign( CidSignatures::message( $cid ) ) );
		$this->key = (string)$signingKey->getKey();
		return true;
	}

	private function download( SigningKey $signingKey ): bool {
		$this->getOutput()->disable();
		$response = $this->getRequest()->response();
		$response->header( 'Content-Type: application/octet-stream' );
		$response->header( "Content-Disposition: attachment; filename=\"{$signingKey->getKey()}.key\"" );
		$response->header( 'Cache-Control: no-store' );
		print $signingKey->export();
		return true;
	}

	public function onSuccess() {
		if ( $this->cid !== '' ) {
			$this->getOutput()->addWikiMsg( 'ipfsupload-done', $this->cid, $this->key );
		}
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
