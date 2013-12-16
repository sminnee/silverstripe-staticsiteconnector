<?php
/**
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
	 * @return null | void
	 */
	public function log($message, $filename=null, $mime=null) {
		$logFile = Config::inst()->get('StaticSiteContentExtractor', 'log_file');
		if(!$logFile) {
			return;
		}

		if(is_writable($logFile) || !file_exists($logFile) && is_writable(dirname($logFile))) {
			$message = $message.($filename?' '.$filename:'').($mime?' ('.$mime.')':'');
			error_log($message. PHP_EOL, 3, $logFile);
		}
	}

	/*
	 * Becuase we can have several imported "sub-trees" in the CMS' SiteTree at once and if we run the 
	 * StaticSiteLinkRewrite task, it will clumsily look for _all_ content with a non-NULL StaticSiteURL.
	 *
	 * To prevent this, we can reset the StaticSIteURL of _all_ matches _before_ we ever run the link-rewrite task.
	 *
	 * Resets the value of `$SStype.StaticSiteURL` to NULL before import, to ensure it's unique to the current import.
	 * If this isn't done, it isn't clear to the RewriteLinks BuildTask, which tree of imported content to link-to, when multiple imports have been made.
	 *
	 * @param string $url
	 * @param number $sourceID
	 * @param string $SSType SiteTree, File, Image
	 * @return void
	 */
	public function resetStaticSiteURLs($url, $sourceID, $SSType) {
		$url = trim($url);
		$resetItems = $SSType::get()->filter(array(
			'StaticSiteURL'=>$url,
			'StaticSiteContentSourceID' => $sourceID
		));
		foreach($resetItems as $item) {
			$item->StaticSiteURL = NULL;
			$item->write();
		}
	}

	/*
	 * If operating in a specific environment, set some proxy options for it for passing to curl and to phpCrawler (if set in config)
	 *
	 * @param boolean $set e.g. !Director::isDev()
	 * @param StaticSiteCrawler $crawler (Warning: Pass by reference)
	 * @return array Returns an array of the config options in a format consumable by curl.
	 */
	public function defineProxyOpts($set, &$crawler = null) {
		if($set && is_bool($set) && $set !== false) {
			$proxyOpts = Config::inst()->get('StaticSiteContentExtractor', 'curl_opts_proxy');
			if(!$proxyOpts || !is_array($proxyOpts) || !sizeof($proxyOpts)>0) {
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
