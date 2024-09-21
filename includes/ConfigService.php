<?php
namespace MediaWiki\Extension\WebToolsManager;

use MediaWiki\MediaWikiServices;

/**
 * Configuration service for WebToolsManager
 */
class ConfigService {
	/**
	 * Get field definition
	 *
	 * @return array
	 */
	public static function getDefinition() {
		return [
			'analytics-google-id' => [
				'default' => '',
			],
			'analytics-google-anonymizeip' => [
				'default' => '1',
			],
			'analytics-exclude-titles' => [
				'default' => null
			],
			'opengraph-activate' => [
				'default' => '0',
			],
			'opengraph-fallbackOnLogo' => [
				'default' => '1'
			],
			'opengraph-description' => [
				'default' => ''
			],
			'opengraph-facebook-appid' => [
				'default' => ''
			],
			'opengraph-twitter-site' => [
				'default' => ''
			],
			'opengraph-twitter-creator' => [
				'default' => ''
			],
		];
	}

	/**
	 * Get the valid keys for the form
	 *
	 * @return array Valid form keys
	 */
	public static function getValidConfigKeys() {
		return array_keys( self::getDefinition() );
	}

	/**
	 * Update the configuration values
	 *
	 * @param array $data Key/value pair for the configuration
	 */
	public static function update( $data = [] ) {
		$rows = [];
		foreach ( $data as $key => $val ) {
			$rows[] = [
				'wtc_key' => $key,
				'wtc_value' => $val,
			];
		}

		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$db->replace(
			'webtools_config',
			'wtc_key',
			$rows,
			__METHOD__
		);
	}

	/**
	 * Get the configuration values
	 *
	 * @return array Configuration values
	 */
	public static function getValues() {
		$values = [];
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $db->select(
			'webtools_config',
			[ 'wtc_key', 'wtc_value' ],
			'',
			__METHOD__
		);

		if ( $res ) {
			foreach ( $res as $row ) {
				$values[ $row->wtc_key ] = $row->wtc_value;
			}
		}

		$merged = array_merge(
			self::getDefaultValues(),
			$values
		);
		return $merged;
	}

	/**
	 * Get the default values for the fields
	 *
	 * @return array Default values
	 */
	public static function getDefaultValues() {
		$defaults = [];
		foreach ( self::getDefinition() as $key => $data ) {
			$defaults[ $key ] = $data['default'];
		}

		return $defaults;
	}

	/**
	 * Validate the google analytics ID field
	 *
	 * @param string $value Field value
	 * @param array $alldata All values
	 * @param HTMLForm $form HTMLForm
	 * @return bool|wfMessage Field is valid, or a message if it's invalid
	 */
	public static function validateGoogleId( $value, $alldata, $form ) {
		$valid = (
			empty( $value ) ||
			(bool)preg_match(
				// UA-#########-#
				"/^UA-[0-9]{9}-[0-9]/",
				$value
			)
		);

		if ( !$valid ) {
			return $form->msg( 'webtoolsmanager-form-analytics-google-id-badformat' );
		}

		return true;
	}
}
