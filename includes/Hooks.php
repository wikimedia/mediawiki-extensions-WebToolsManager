<?php
namespace MediaWiki\Extension\WebToolsManager;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

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
				'MediaWiki\\Extension\\WebToolsManager\\Hooks::onPageContentSaveComplete';
		} else {
			$wgHooks['PageContentSaveComplete'][] =
				'MediaWiki\\Extension\\WebToolsManager\\Hooks::onPageContentSaveComplete';
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
	 * Handler for SkinTemplateNavigation::Universal hook.
	 * Add a link to the Web tools manager special page, for authorized users
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public static function onSkinTemplateNavigation__Universal( $sktemplate, &$links ) {
		$user = $sktemplate->getUser();
		if (
			\MediaWiki\MediaWikiServices::getInstance()
			->getPermissionManager()
			->userHasRight( $user, 'webtoolsmanagement' )
		) {
			$title = $sktemplate->getTitle();
			$personal_urls = &$links['user-menu'];
			$url = \SpecialPage::getTitleFor( 'WebToolsManager' )->getLocalURL();
			$link = [
				'href' => $url,
				'text' => $sktemplate->msg( 'webtoolsmanager-specialpage-link' )->text(),
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
