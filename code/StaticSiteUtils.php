<?php
/**
 * 
 * Basic class for utility methods unsuited to any other class
 * 
 * @package staticsiteconnector
 * @author Russell Michell <russell@silverstripe.com>
 */
class StaticSiteUtils {

	/**
	 * Log a message if the logging has been setup according to docs
	 *
	 * @param string $message
	 * @param string $filename
	 * @param string $mime
	 * @param string $class Optional. The class passed to Config to find the value for $log_file.
	 * @return null | void
	 */
	public function log($message, $filename = null, $mime = null, $class = 'StaticSiteContentExtractor') {
		$logFile = Config::inst()->get($class, 'log_file');
		if(!$logFile) {
			return;
		}

		if(is_writable($logFile) || !file_exists($logFile) && is_writable(dirname($logFile))) {
			$message = $message . ($filename ? ' ' . $filename : '') . ($mime ? ' (' . $mime . ')' : '');
			error_log($message. PHP_EOL, 3, $logFile);
		}
	}

	/**
	 * Resets all items' StaticSiteURL fields to NULL, If there are multiple imported sub-trees, 
	 * this will clear the first-import's StaticSiteURL's, so the rewrite task will only attempt
	 * to rewrite links to content with a StaticSiteURL value of NOT NULL.
	 * 
	 * If this isn't done, it isn't clear to the RewriteLinks BuildTask, which tree of imported content 
	 * to look in for content to link-to, when multiple imports have been made. In this case it will clumsily 
	 * look for _all_ imported content with a non-NULL SiteTree.StaticSiteURL field value.
	 *
	 * @param string $url
	 * @param number $sourceID
	 * @param string $class SiteTree, File or Image
	 * @todo Figure out how to ensure these updates happen for the correct item, not _all_ items..
	 * @todo this is only useful when there are multiple subtrees. Detect if there are, and only run if there are >=2.
	 * @todo The match may be failing becuase StaticSiteURLs contain http(s)?://(www\.)?.
	 * @return void
	 */
	public function resetStaticSiteURLs($url, $sourceID, $class) {
		$url = trim($url);
		$resetItems = DataObject::get($class)->filter(array(
			'StaticSiteContentSourceID' => $sourceID,
			'StaticSiteURL' => $url
		));
		
		foreach($resetItems as $item) {
			$item->StaticSiteURL = NULL;
			$item->write();
		}
	}

	/**
	 * If operating in a specific environment, set some proxy options for it for passing to curl and 
	 * to phpCrawler (if set in config).
	 *
	 * @param boolean $set e.g. !Director::isDev()
	 * @param StaticSiteCrawler $crawler (Warning: Pass by reference)
	 * @return array Returns an array of the config options in a format consumable by curl.
	 */
	public function defineProxyOpts($set, &$crawler = null) {
		if($set && is_bool($set) && $set !== false) {
			$proxyOpts = Config::inst()->get('StaticSiteContentExtractor', 'curl_opts_proxy');
			if(!$proxyOpts || !is_array($proxyOpts) || !count($proxyOpts)>0) {
				return array();
			}
			if($crawler) {
				$crawler->setProxy($proxyOpts['hostname'], $proxyOpts['port']);
			}
			return array(
				CURLOPT_PROXY => $proxyOpts['hostname'],
				CURLOPT_PROXYPORT => $proxyOpts['port']
			);
		}
		return array();
	}
}
