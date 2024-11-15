<?php
namespace MediaWiki\Extension\WebToolsManager;

use MediaWiki\MediaWikiServices;

/**
 * Settings SpecialPage for WebToolsManager extension
 *
 * @file
 * @ingroup Extensions
 */
class SpecialWebToolsManager extends \FormSpecialPage {
	const PAGE_NAME = 'WebToolsManager';

	public function __construct() {
		parent::__construct( self::PAGE_NAME, '', false );
	}

	/**
	 * Show the page to the user
	 *
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub ) {
		parent::execute( $sub );

		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'webtoolsmanager-specialpage-title' ) );
		$out->addModuleStyles( [
			'mediawiki.widgets.TagMultiselectWidget.styles',
			'ext.webToolsManager.specialPage.styles',
		] );
	}

	/**
	 * Checks that the has the correct right to access the page
	 *
	 * @param User $user
	 * @throws ErrorPageError
	 */
	protected function checkExecutePermissions( \User $user ) {
		parent::checkExecutePermissions( $user );

		if (
			!MediaWikiServices::getInstance()
				->getPermissionManager()
				->userHasRight( $user, 'webtoolsmanagement' )
		) {
			throw new \ErrorPageError(
				'special-webToolsManager-title',
				'webtoolsmanager-error-nopermission'
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		global $wgSitename;
		$conf = ConfigService::getValues();
		$mwConfig = \MediaWiki\MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'webtoolsmanager' );

		$analyticsFields = [];
		$fields = [
			// Open graph
			'opengraph-activate' => [
				'type' => 'toggle',
				'label-message' => 'webtoolsmanager-form-opengraph-activate',
				'default' => $conf[ 'opengraph-activate' ],
				'section' => 'opengraph'
			],
			'opengraph-fallbackOnLogo' => [
				'type' => 'toggle',
				'label-message' => 'webtoolsmanager-form-opengraph-fallbackOnLogo',
				'default' => $conf[ 'opengraph-fallbackOnLogo' ],
				'help-message' => 'webtoolsmanager-form-opengraph-fallbackOnLogo-help',
				'cssclass' => 'opengraph-dependent-input',
				'disabled' => !$conf[ 'opengraph-activate' ],
				'section' => 'opengraph'
			],
			'opengraph-description' => [
				'type' => 'textarea',
				'label-message' => 'webtoolsmanager-form-opengraph-description',
				'rows' => '3',
				'default' => $conf[ 'opengraph-description' ],
				'help-message' => 'webtoolsmanager-form-opengraph-description-help',
				'cssclass' => 'opengraph-dependent-input',
				'disabled' => !$conf[ 'opengraph-activate' ],
				'section' => 'opengraph'
			],
			'opengraph-facebook-appid' => [
				'type' => 'text',
				'size' => '10',
				'label-message' => 'webtoolsmanager-form-opengraph-facebook-appid',
				'help-message' => 'webtoolsmanager-form-opengraph-facebook-appid-help',
				'default' => $conf[ 'opengraph-facebook-appid' ],
				'cssclass' => 'opengraph-dependent-input',
				'disabled' => !$conf[ 'opengraph-activate' ],
				'section' => 'opengraph'
			],
			'opengraph-twitter-site' => [
				'type' => 'text',
				'size' => '10',
				'label-message' => 'webtoolsmanager-form-opengraph-twitter-site',
				'placeholder' => wfMessage( 'webtoolsmanager-form-help-example' )
					->params( '@' . $wgSitename )->plain(),
				'help-message' => 'webtoolsmanager-form-opengraph-twitter-site-help',
				'default' => $conf[ 'opengraph-twitter-site' ],
				'cssclass' => 'opengraph-dependent-input',
				'disabled' => !$conf[ 'opengraph-activate' ],
				'section' => 'opengraph'
			],
			'opengraph-twitter-creator' => [
				'type' => 'text',
				'size' => '10',
				'label-message' => 'webtoolsmanager-form-opengraph-twitter-creator',
				'placeholder' => wfMessage( 'webtoolsmanager-form-help-example' )
					->params( '@username' )->plain(),
				'help-message' => 'webtoolsmanager-form-opengraph-twitter-creator-help',
				'default' => $conf[ 'opengraph-twitter-creator' ],
				'cssclass' => 'opengraph-dependent-input',
				'disabled' => !$conf[ 'opengraph-activate' ],
				'section' => 'opengraph'
			],
		];

		if ( $mwConfig->get( 'WebToolsManagerAllowGoogleAnalytics' ) ) {
			$analyticsFields = [
				// Analytics
				'analytics-google-id' => [
					'type' => 'text',
					'size' => '10',
					'label-message' => 'webtoolsmanager-form-analytics-google-id',
					'default' => $conf[ 'analytics-google-id' ],
					'validation-callback' =>
						'MediaWiki\\Extension\\WebToolsManager\\ConfigService::validateGoogleId',
					'help-message' => 'webtoolsmanager-form-analytics-google-id-help',
					'section' => 'analytics'
				],
				'analytics-google-anonymizeip' => [
					'type' => 'toggle',
					'label-message' => 'webtoolsmanager-form-analytics-google-anonymizeip',
					'default' => $conf[ 'analytics-google-anonymizeip' ],
					'section' => 'analytics'
				],
				'analytics-exclude-titles' => [
					'type' => 'titlesmultiselect',
					'label-message' => 'webtoolsmanager-form-analytics-exclude-titles',
					'default' => $conf[ 'analytics-exclude-titles' ],
					'section' => 'analytics',
				]
			];
		}
		$fields = array_merge( $fields, $analyticsFields );

		return $fields;
	}

	/**
	 * @param array $data
	 * @param HTMLForm|null $form
	 * @return bool
	 */
	public function onSubmit( array $data, ?\HTMLForm $form = null ) {
		$validFields = ConfigService::getValidConfigKeys();
		$result = [];

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $validFields ) ) {
				if (
					$key === 'analytics-google-anonymizeip' ||
					$key === 'opengraph-activate' ||
					$key === 'opengraph-fallbackOnLogo'
				) {
					$value = (int)$value;
				}
				$result[ $key ] = $value;
			}
		}

		return ConfigService::update( $result );
	}

	/**
	 * Successful form submission
	 */
	public function onSuccess() {
		$this->getOutput()->redirect( $this->getPageTitle()->getFullURL() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return true;
	}
}
