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
	 * Register hooks depending on version
	 */
	public static function registerExtension() {
		global $wgHooks;
		if ( class_exists( \MediaWiki\HookContainer\HookContainer::class ) ) {
			// MW 1.35+
			$wgHooks['PageSaveComplete'][] =
				'MediaWiki\\Extensions\\WebToolsManager\\Hooks::onPageContentSaveComplete';
		} else {
			$wgHooks['PageContentSaveComplete'][] =
				'MediaWiki\\Extensions\\WebToolsManager\\Hooks::onPageContentSaveComplete';
		}
	}

	/**
	 * A method to respond to the hook PageContentSaveComplete
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
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
	 * @param \OutputPage $out The OutputPage object.
	 * @param \Skin $skin - Skin object that will be used to generate the page, added in 1.13.
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
				'text' => wfMessage( 'webtoolsmanager-specialpage-link' ),
				'active' => ( $url == $title->getLocalURL() ),
				'class' => [ 'ext-webToolsManager-link' ]
			];
			$personal_urls = wfArrayInsertAfter(
				$personal_urls, [ 'webtoolsmanager' => $link ], 'preferences'
			);
		}
	}

	/**
	 * Respond to the LoadExtensionSchemaUpdates hook
	 * Add or update necessary tables
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( \DatabaseUpdater $updater ) {
		$dir = dirname( __DIR__ );
		$updater->addExtensionTable(
			'webtools_config',
			"$dir/database/webtools_config.sql"
		);
	}

}
