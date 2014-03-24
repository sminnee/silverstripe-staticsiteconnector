<?php
/**
 * Basic class for utility methods unsuited to any other class.
 * 
 * @package staticsiteconnector
 * @author Russell Michell <russell@silverstripe.com>
 * @todo Should these methods all be statics?
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
