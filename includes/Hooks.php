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
	 * A method to respond to hook BeforePageDisplay
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param $out The OutputPage object.
	 * @param $skin - Skin object that will be used to generate the page, added in 1.13.
	 */
	public static function onBeforePageDisplay( \OutputPage $out, \Skin $skin ) {
		global $wgSitename;

		$configService = new ConfigService();
		$config = $configService->getvalues();
		$titleObj = $out->getTitle();

		// Add modules for the special page
		if ( $titleObj->isSpecial( 'WebToolsManager' ) ) {
			$out->addModules( 'ext.webToolsManager.specialPage' );
		}

		// Analytics
		$googleId = $config['analytics-google-id'];
		$googleAnonymize = $config['analytics-google-anonymizeip'];
		$excludedTitleNames = explode( "\n", $config['analytics-exclude-titles'] );
		if (
			!empty( $googleId ) &&
			!in_array( $out->getPageTitle(), $excludedTitleNames )
		) {
			$script = implode(
				"\n",
				[
					'<script async src="https://www.googletagmanager.com/gtag/js?id=' . $googleId . '"></script>',
					'<script>',
					'window.dataLayer = window.dataLayer || [];',
					'function gtag(){dataLayer.push(arguments);}',
					'gtag(\'js\', new Date());',
					!!$googleAnonymize ?
						'gtag(\'config\', \'' . $googleId . '\', { \'anonymize_ip\': true } );' :
						'gtag(\'config\', \'' . $googleId . '\');',
					'</script>'
				]
			);
			$out->addScript( $script );
		}

		// Open graph
		if (
			(bool)$config['opengraph-activate'] &&
			$titleObj->hasContentModel( CONTENT_MODEL_WIKITEXT )
		) {
			$meta = [];
			$isMainPage = $titleObj->isMainPage();
			$siteDetails = new SiteDetails(
				[ 'fallbackOnLogo' => (bool)$config['opengraph-fallbackOnLogo'] ]
			);
			$details = $siteDetails->getDetails( $titleObj );

			// Image needs to be injected first, but let's verify we're
			// not overriding any other extension that produced this
			$existingMetaTagKeys = array_map(
				// We get array of arrays of key/val
				function ( $keyval ) {
					return $keyval[0]; // $key
				},
				$out->getMetaTags()
			);
			if ( !in_array( 'og:image', $existingMetaTagKeys ) ) {
				$out->addMeta( 'og:image', $details['image'] );
			}

			// The rest of the tags
			$meta = [
				'og:type' => 'website',
				'og:title' => $wgSitename,
				'og:url' => $titleObj->getFullURL(),
				'og:description' => $details['summary'],
			];

			// Fallback on description, if it was given
			if (
				empty( $details['summary'] ) &&
				!empty( $config['opengraph-description'] )
			) {
				$meta['og:description'] = $config['opengraph-description'];
			}

			if ( !$isMainPage ) {
				$meta = [
					'og:type' => 'article',
					'og:title' => $titleObj->getText(),
				];
			}

			// Create the rest of the meta tags
			foreach ( $meta as $key => $content ) {
				$out->addMeta( $key, $content );
			}

			// Facebook App ID
			if ( !empty( $config['opengraph-facebook-appid'] ) ) {
				// The 'addMeta' outputs the fb:app_id as "name=" rather
				// than "property=", so we need to output it manually
				$out->addHeadItem(
					'meta:facebook:appid',
					\Html::element( 'meta', [
						'property' => 'fb:app_id',
						'content' => $config['opengraph-facebook-appid']
					] )
				);
			}

			// Twitter, "helpfully", uses <meta name=""> rather
			// than <meta property=""> which means we also need
			// to output manually
			$twitter = [];
			// Twitter handles
			if ( !empty( $config['opengraph-twitter-site'] ) ) {
				$twitter['twitter:site'] = $config['opengraph-twitter-site'];
			}

			if ( !empty( $config['opengraph-twitter-creator'] ) ) {
				$twitter['twitter:creator'] = $config['opengraph-twitter-creator'];
			}

			if ( !empty( array_keys( $twitter ) ) ) {
				$twitter['twitter:card'] = 'summary';
			}

			foreach ( $twitter as $name => $content ) {
				$out->addHeadItem(
					'meta:' . $name,
					\Html::element( 'meta', [
						'property' => $name,
						'content' => $content
						] )
					);
			}
		}
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
