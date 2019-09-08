<?php
namespace MediaWiki\Extensions\WebToolsManager;
/**
 * WebToolsManager extension hooks
 *
 * @file
 * @ingroup Extensions
 */
class Hooks {
	/**
	 * Conditionally register the unit testing module for the ext.webToolsManager module
	 * only if that module is loaded
	 *
	 * @param array $testModules The array of registered test modules
	 * @param ResourceLoader $resourceLoader The reference to the resource loader
	 * @return true
	 */
	public static function onResourceLoaderTestModules( array &$testModules, \ResourceLoader &$resourceLoader ) {
		$testModules['qunit']['ext.webToolsManager.tests'] = [
			'scripts' => [
				'tests/WebToolsManager.test.js'
			],
			'dependencies' => [
				'ext.webToolsManager'
			],
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'WebToolsManager',
		];
		return true;
	}

	/**
	 * A method to respond to the hook PageContentSaveComplete
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 *
	 * @param WikiPage $wikiPage
	 */
	public static function onPageContentSaveComplete( $wikiPage ) {
		// Refresh the cached keys for this title
		MetadataManager::regenerateCacheValues( $wikiPage->getTitle() );
	}

	/**
	 * A method to respond to hook BeforePageDisplay
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param $out The OutputPage object.
	 * @param $skin - Skin object that will be used to generate the page, added in 1.13.
	 */
	public static function onBeforePageDisplay( \OutputPage $out, \Skin $skin ) {
		// Add modules for the special page
		if ( $out->getTitle()->isSpecial( 'WebToolsManager' ) ) {
			$out->addModules( 'ext.webToolsManager.specialPage' );
		}

		// Output the head components
		$config = ConfigService::getValues();
		$outputManager = new OutputManager( $out );

		$outputManager->outputAnalytics();
		$outputManager->outputMetaTags();
	}

	/**
	 * Handler for PersonalUrls hook.
	 * Add a link to the Web tools manager special page, for authorized users
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PersonalUrls
	 *
	 * @param array &$personal_urls Array of URLs to append to.
	 * @param Title &$title Title of page being visited.
	 * @param SkinTemplate $sk
	 * @return bool true in all cases
	 */
	public static function onPersonalUrls( &$personal_urls, &$title, $sk ) {
		$user = $sk->getUser();
		if (
			\MediaWiki\MediaWikiServices::getInstance()
			->getPermissionManager()
			->userHasRight( $user, 'webtoolsmanagement' )
		) {
			$url = \SpecialPage::getTitleFor( 'WebToolsManager' )->getLocalURL();
			$link = [
				'href' => $url,
				'text' => wfMessage( 'ext-webToolsManager-link' ),
				'active' => ( $url == $title->getLocalURL() ),
				'class' => [ 'ext-webToolsManager-link' ]
			];
			$personal_urls = wfArrayInsertAfter( $personal_urls, [ 'webtoolsmanager' => $link ], 'preferences' );
		}
	}

	/**
	 * Respond to the LoadExtensionSchemaUpdates hook
	 * Add or update necessary tables
	 *
	 * @param  DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( \DatabaseUpdater $updater ) {
		$dir = dirname( __DIR__ );
		$updater->addExtensionTable(
			'webtools_config',
			"$dir/database/webtools_config.sql"
		);
	}

}
