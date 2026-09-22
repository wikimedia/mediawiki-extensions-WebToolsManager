<?php
namespace MediaWiki\Extension\WebToolsManager;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiResult;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Controls and manages the metadata needed for pages
 */
class MetadataManager {
	protected $out;

	/**
	 * @inheritDoc
	 */
	public function __construct( OutputPage $out ) {
		$this->out = $out;
	}

	/**
	 * Hold onto a cache for our operations. Static so it can reuse the same
	 * in-process cache in different instances.
	 *
	 * @return BagOStuff
	 */
	protected static function cache() {
		static $c = null;

		if ( $c === null ) {
			$c = MediaWikiServices::getInstance()->getMainObjectStash();
		}

		return $c;
	}

	/**
	 * Output all the metadata for this output
	 *
	 * @return array An array where the keys are metadata
	 *  property types, and the values are the values relevant
	 *  for this title
	 */
	public function getMetadata() {
		global $wgSitename, $wgLogo;
		$cache = self::cache();
		$config = ConfigService::getValues();
		$isMainPage = $this->out->getTitle()->isMainPage();
		$service = MediaWikiServices::getInstance();
		$data = [
			'og:type' => $isMainPage ? 'site' : 'article',
			'og:title' => $isMainPage ?
				$wgSitename : $this->out->getPageTitle(),
			'og:locale' => $service->getContentLanguage()->getHtmlCode(),
			'og:url' => $this->out->getTitle()->getFullURL(),
			// If dynamic, these may change
			'og:image' => $service->getUrlUtils()->expand( $wgLogo ),
			'og:description' => $config['opengraph-description'],
		];

		// Facebook
		if ( $config['opengraph-facebook-appid'] !== '' ) {
			$data['fb:app_id'] = $config['opengraph-facebook-appid'];
		}

		// Twitter
		$useTwitter = false;
		if ( $config['opengraph-twitter-site'] !== '' ) {
			$useTwitter = true;
			$data['twitter:site'] = $config['opengraph-twitter-site'];
		}

		if ( $config['opengraph-twitter-creator'] !== '' ) {
			$useTwitter = true;
			$data['twitter:creator'] = $config['opengraph-twitter-creator'];
		}

		if ( $useTwitter ) {
			$data['twitter:card'] = 'summary';
		}

		// Get the dynamic values
		if ( $this->shouldContainDynamicData() ) {
			$data['og:description'] = $cache->getWithSetCallback(
				self::getCacheKey(
					$this->out->getPageTitle(),
					'description'
				),
				$cache::TTL_WEEK,
				[ $this, 'generateDescriptionFromApi' ]
			);
			$data['og:image'] = $cache->getWithSetCallback(
				self::getCacheKey(
					$this->out->getPageTitle(),
					'image'
				),
				WANObjectCache::TTL_WEEK,
				[ $this, 'generateImageFromApi' ]
			);
		}

		if (
			$data['og:image'] === '' &&
			$config['opengraph-fallbackOnLogo']
		) {
			// Fall back on wiki logo
			$data['og:image'] = $service->getUrlUtils()->expand( $wgLogo );
		}

		if (
			$data['og:description'] === '' &&
			$config['opengraph-description']
		) {
			// Fall back on wiki logo
			$data['og:description'] = $config['opengraph-description'];
		}

		return $data;
	}

	/**
	 * Intentionally regenerate the cached values for
	 * the given title.
	 *
	 * @param Title $title
	 */
	public static function regenerateCacheValues( $title ) {
		$values = self::generateDynamicDataFromAPI( $title );

		self::cache()->set(
			self::getCacheKey( $title->getDBkey(), 'description' ),
			$values['description']
		);
		self::cache()->set(
			self::getCacheKey( $title->getDBkey(), 'image' ),
			$values['image']
		);
	}

	/**
	 * Build a cache key.
	 *
	 * @param string $title Page title
	 * @param string $field The meta field to store
	 * @return string Memcached key
	 */
	public static function getCacheKey( $title, $field ) {
		return self::cache()->makeKey(
			'webtoolsmanager',
			'meta',
			$title,
			$field
		);
	}

	/**
	 * Check whether the given page should contain custom generated
	 * data for its summary and image.
	 * Only articles that are not excluded should return true.
	 *
	 * @return bool
	 */
	public function shouldContainDynamicData() {
		return (
			!$this->out->getTitle()->isMainPage() &&
			!$this->out->getTitle()->isSpecialPage() &&
			!$this->out->getTitle()->isTalkPage()
			// TODO: Add an exclusion list in settings in case
			// admins want to set specific pages to also have
			// the static summary/image output instead of a
			// summary of the actual content
		);
	}

	/**
	 * Generate the dynamic data for the description
	 *
	 * @return string Page description
	 */
	public function generateDescriptionFromApi() {
		return self::generateDynamicDataFromAPI( $this->out->getTitle(), 'description' );
	}

	/**
	 * Generate the dynamic data for the image
	 *
	 * @return string Page image
	 */
	public function generateImageFromApi() {
		return self::generateDynamicDataFromAPI( $this->out->getTitle(), 'image' );
	}

	/**
	 * Generate the dynamic data that comes from the API
	 *
	 * @param Title $title Page title
	 * @param string|null $which A specific API module. Leave blank for all.
	 * @return string|array A string value if a module was given, or a keyed
	 *  array for module/value pairs if no module was specified.
	 */
	public static function generateDynamicDataFromAPI( Title $title, $which = null ) {
		if ( $which === null ) {
			$result = [
				'description' => '',
				'image' => '',
			];
		} else {
			$result = '';
		}

		$props = [];
		if (
			(
				$which === null ||
				$which === 'description'
			) &&
			ExtensionRegistry::getInstance()->isLoaded( 'TextExtracts' )
		) {
			$props[] = 'extracts';
		}

		if (
			(
				$which === null ||
				$which === 'image'
			) &&
			ExtensionRegistry::getInstance()->isLoaded( 'PageImages' )
		) {
			$props[] = 'pageimages';
		}

		if ( count( $props ) > 0 ) {
			// Fake an API call
			$api = new ApiMain(
				new FauxRequest( [
					'action' => 'query',
					'titles' => $title->getFullText(),
					'prop' => implode( '|', $props ),
					'exchars' => '200',
					'exsectionformat' => 'plain',
					'explaintext' => '1',
					'exintro' => '1',
					'piprop' => 'thumbnail',
					'pithumbsize' => 120 * 2,
				] )
			);
			$api->execute();

			if ( defined( 'ApiResult::META_CONTENT' ) ) {
				$pageData = $api->getResult()->getResultData(
					[ 'query', 'pages', $title->getArticleID() ]
				);
				$contentKey = $pageData['extract'][ApiResult::META_CONTENT] ?? '*';
			} else {
				$pageData = $api->getResult()->getResultData()['query']['pages'][$title->getArticleID()];
				$contentKey = '*';
			}

			if ( isset( $pageData['thumbnail'] ) ) {
				if ( $which === null ) {
					$result['image'] = $pageData['thumbnail'];
				} else {
					$result = $pageData['thumbnail'];
				}
			}

			if ( isset( $pageData['extract'] ) ) {
				if ( $which === null ) {
					$result['description'] = $pageData['extract'][$contentKey];
				} else {
					$result = $pageData['extract'][$contentKey];
				}
			}
		}

		return $result;
	}

}
