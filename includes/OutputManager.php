<?php
namespace MediaWiki\Extensions\WebToolsManager;

/**
 * Output manager, controls the output according to the database
 */
class OutputManager {
	protected $out;

	/**
	 * @inheritDoc
	 */
	public function __construct( \OutputPage $out ) {
		$this->out = $out;
	}

	/**
	 * Output meta tags and head tags based on given
	 * tag definition.
	 */
	public function outputMetaTags() {
		$config = ConfigService::getValues();
		if ( !$config['opengraph-activate'] ) {
			return;
		}

		$metaManager = new MetadataManager( $this->out );
		$tags = $metaManager->getMetadata();

		// Image needs to be injected first, but let's verify we're
		// not overriding any other extension that produced this
		$existingMetaTagKeys = array_map(
			// We get array of arrays of key/val
			static function ( $keyval ) {
				return $keyval[0];
			},
			$this->out->getMetaTags()
		);
		if ( !in_array( 'og:image', $existingMetaTagKeys ) ) {
			$this->out->addMeta( 'og:image', $tags['og:image'] );
			unset( $tags['og:image'] );
		}

		// Twitter tags are different output, let's do them first
		// Twitter, "helpfully", uses <meta name=""> rather
		// than <meta property=""> which means we also need
		// to output manually
		$twitterTags = [ 'twitter:card', 'twitter:site', 'twitter:creator' ];
		foreach ( $twitterTags as $name ) {
			if ( isset( $tags[$name] ) ) {
				$this->out->addHeadItem(
					'meta:' . $name,
					\Html::element( 'meta', [
						'property' => $name,
						'content' => $tags[$name]
						] )
					);
				unset( $tags[$name] );
			}
		}

		// Now go over the rest of the tags, and output them
		foreach ( $tags as $name => $content ) {
			$this->out->addMeta( $name, $content );
		}
	}

	/**
	 * Output the script required for analytics, if applicable
	 */
	public function outputAnalytics() {
		$mwConfig = \MediaWiki\MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'webtoolsmanager' );
		if ( !$mwConfig->get( 'WebToolsManagerAllowGoogleAnalytics' ) ) {
			return;
		}

		$config = ConfigService::getValues();

		$googleId = $config['analytics-google-id'];
		$googleAnonymize = $config['analytics-google-anonymizeip'];
		$excludedTitleNames = explode( "\n", $config['analytics-exclude-titles'] );
		if (
			!empty( $googleId ) &&
			!in_array( $this->out->getPageTitle(), $excludedTitleNames )
		) {
			$script = implode(
				"\n",
				[
					'<script async src="https://www.googletagmanager.com/gtag/js?id=' . $googleId . '"></script>',
					'<script>',
					'	window.dataLayer = window.dataLayer || [];',
					'	function gtag(){dataLayer.push(arguments);}',
					'	gtag(\'js\', new Date());',
					$googleAnonymize ?
						'	gtag(\'config\', \'' . $googleId . '\', { \'anonymize_ip\': true } );' :
						'	gtag(\'config\', \'' . $googleId . '\');',
					'</script>'
				]
			);
			$this->out->addScript( $script );
		}
	}
}
