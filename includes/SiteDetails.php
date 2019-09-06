<?php
namespace MediaWiki\Extensions\WebToolsManager;

/**
 * Site details class, fetches and builds details for the OpenGraph meta tags
 */
class SiteDetails {
	protected $fallbackOnLogo;

	public function __construct( $config = [] ) {
		$this->fallbackOnLogo = isset( $config['fallbackOnLogo'] ) ?
			(bool)$config['fallbackOnLogo'] : true;
	}

	/**
	 * Adjusted from TwitterCards
	 * @see https://github.com/wikimedia/mediawiki-extensions-TwitterCards/blob/master/TwitterCards.hooks.php
	 *
	 * @param  Title $title MWTitle
	 * @return String Article summary
	 */
	public function getDetails( $title = null, $config = [] ) {
		global $wgLogo;

		$result = [
			'summary' => '',
			'image' => '',
		];

		$props = [];
		if ( \ExtensionRegistry::getInstance()->isLoaded( 'TextExtracts' ) ) {
			$props[] = 'extracts';
		}
		if ( \ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
			$props[] = 'pageimages';
		}

		if ( count( $props ) > 0 ) {
			// Fake an API call
			$api = new \ApiMain(
				new \FauxRequest( [
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
				$contentKey = isset( $pageData['extract'][\ApiResult::META_CONTENT] )
					? $pageData['extract'][\ApiResult::META_CONTENT]
					: '*';
			} else {
				$pageData = $api->getResult()->getData()['query']['pages'][$title->getArticleID()];
				$contentKey = '*';
			}

			if ( isset( $pageData['extract'] ) && isset( $pageData['extract'][$contentKey] ) ) {
				$result['summary'] = $pageData['extract'][$contentKey];
			}

			if ( isset( $pageData['thumbnail'] ) && isset( $pageData['thumbnail']['source'] ) ) {
				$result['image'] = $pageData['thumbnail']['source'];
			}
		}

		if (
			$title->isMainPage() ||
			( empty( $result['image'] ) && $this->fallbackOnLogo )
		) {
			$result['image'] = wfExpandUrl( $wgLogo );
		}

		return $result;
	}
}
