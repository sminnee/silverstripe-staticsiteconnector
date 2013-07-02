<?php
/*
 * Basic class for utility methods unsuited to any other class
 */
class StaticSiteUtils {

	/**
	 * Log a message if the logging has been setup according to docs
	 *
	 * @param string $message
	 * @param string $filename
	 * @param string $mime
	 * @return void
	 */
	public function log($message, $filename=null, $mime=null) {
		$logFile = Config::inst()->get('StaticSiteContentExtractor', 'log_file');
		if(!$logFile) {
			return;
		}

		if(is_writable($logFile) || !file_exists($logFile) && is_writable(dirname($logFile))) {
			$message = $message.($filename?$filename:'').' ('.($mime?$mime:'').')';
			error_log($message. PHP_EOL, 3, $logFile);
		}
	}

	/*
	 * Becuase we can have several imported "sub-trees" in the CMS' SiteTree st once and if we run the StaticSiteLinkRewrite task, it will clumsily look for _all_ content
	 * with a non-NULL StaticSiteURL.
	 *
	 * To prevent this, reset the StaticSIteURL of _all_ matches _before_ we ever run the link-rewrite task.
	 *
	 * Resets the value of `$SStype.StaticSiteURL` to NULL before import. to ensure it's unique to the current import.
	 * If this isn't done, it isn't clear to the RewriteLinks BuildTask, which tree of imported content to link-to, when multiple imports have been made.
	 *
	 * @param string $url
	 * @param number $sourceID
	 * @param string $SSType SiteTree, File, Image
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

}
