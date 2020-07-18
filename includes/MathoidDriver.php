<?php
use MediaWiki\Logger\LoggerFactory;

class MathoidDriver {
	private $success;
	private $checked;
	private $identifiers;
	private $requiredPackages;
	private $q;
	private $type;
	private $version;
	private $error;

	/**
	 * @return mixed
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 * @return mixed
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * @param string $q
	 * @param string $type
	 */
	function __construct( $q = '', $type = 'tex' ) {
		$this->q = $q;
		if ( $type === 'pmml' ) {
			$type = 'mml';
		}
		$this->type = $type;
	}

	/**
	 * @return mixed
	 */
	public function getSuccess() {
		return $this->success;
	}

	/**
	 * @return mixed
	 */
	public function getChecked() {
		return $this->checked;
	}

	/**
	 * @return mixed
	 */
	public function getIdentifiers() {
		return $this->identifiers;
	}

	/**
	 * @return mixed
	 */
	public function getRequiredPackages() {
		return $this->requiredPackages;
	}

	public function texvcInfo() {
		$url = trim( $this->getBackendUrl(), " \n\t/" ) . "/texvcinfo";
		return $this->processResults( self::doPost( $url, $this->getPostData() ) );
	}

	protected function processResults( $res ) {
		$jsonResult = json_decode( $res );
		if ( $jsonResult && json_last_error() === JSON_ERROR_NONE ) {
			if ( isset( $jsonResult->texvcinfo ) ) {
				// mathoid 0.2.9
				$texvcinfo = $jsonResult->texvcinfo;
			} else {
				// mathoid 0.2.10
				$texvcinfo = $jsonResult;
			}
			$this->success = $texvcinfo->success;
			if ( $this->success ) {
				$this->checked = $texvcinfo->checked;
				$this->identifiers = $texvcinfo->identifiers;
				$this->requiredPackages = $texvcinfo->requiredPackages;
			} else {
				if ( isset( $texvcinfo->error->error ) ) {
					// mathoid 0.2.9
					$this->error = $texvcinfo->error;
				} else {
					// mathoid 0.2.10
					$this->error = $texvcinfo->detail->error;
				}
			}
			return true;
		} else {
			return false;
		}
	}

	protected static function doPost( $url, $postData ) {
		$options = [
			"postData" => $postData,
			"timeout"  => 60,
			"method"   => 'POST'
		];
		$req = MWHttpRequest::factory( $url, $options, __METHOD__ );
		$status = $req->execute();

		if ( $status->isOK() || $req->getStatus() === 400 ) {
			return $req->getContent();
		} else {
			$errors = $status->getErrorsByType( 'error' );
			$logger = LoggerFactory::getInstance( 'http' );
			$logger->warning( $status->getWikiText(),
				[ 'error' => $errors, 'caller' => __METHOD__, 'content' => $req->getContent() ] );
			return false;
		}
	}

	/**
	 * @return string
	 */
	public function getBackendUrl() {
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'main' );
		return $config->get( "MathMathMLUrl" );
	}

	protected function getPostData() {
		$post = [
			'q' => $this->q,
			'type' => $this->type
		];
		return wfArrayToCgi( $post );
	}

	public function checkBackend() {
		$res = HTTP::get( $this->getBackendUrl() . '/_info' );
		if ( $res ) {
			$res = json_decode( $res );
			if ( $res && json_last_error() === JSON_ERROR_NONE ) {
				if ( isset( $res->name ) && $res->name === 'mathoid' ) {
					$this->version = $res->version;
					if ( preg_match( "/(0\\.2\\.(9|10)|1.0.0.*|0.6.1)/", $this->version ) ) {
						return true;
					} else {
						echo $this->version;
						return false;
					}
				}
			}
		}
		$logger = LoggerFactory::getInstance( 'MathSearch' );
		$logger->warning( "Mathoid server backend does not point to mathoid.", [
			'detail' => $res
		] );
		return false;
	}

	public function getSpeech() {
		return $this->getFormat( 'speech' );
	}

	public function getSvg() {
		return $this->getFormat( 'svg' );
	}

	public function getPng() {
		return $this->getFormat( 'png' );
	}

	/**
	 * @param string $format
	 * @return bool|string
	 */
	private function getFormat( $format ) {
		return $this->doPost( $this->getBackendUrl() . '/' . $format, $this->getPostData() );
	}
}
