<?php
/**
 * Interface for building URL processing plug-ins for StaticSiteUrlList.
 *
 * The URL processing plugins are used to process the relative URL before it it used for two separate purposes:
 *
 *  - Generating default URL and Title in the external content browser.
 *  - Building content hierarchy.
 *
 * For example, MOSS has a habit of putting unnecessary "/Pages/" elements into the URLs, and adding
 * .aspx extensions.  We don't want to include these in the content heirarchy.
 *
 * More sophisticated processing might be done to facilitate importing of less
 */
interface StaticSiteUrlProcessor {

	/**
	 * Return a name for the style of URLs to be processed.
	 * This name will be shown in the CMS when users are configuring the content import.
	 *
	 * @return string The name, in plaintext (no HTML)
	 */
	public function getName();

	/**
	 * Return an explanation of what processing is done.
	 * This explanation will be shown in the CMS when users are configuring the content import.
	 *
	 * @return string The description, in plaintext (no HTML)
	 */
	public function getDescription();


	/**
	 * Return a description for this processor, to be shown in the CMS.
	 * 
	 * @param array $urlData The unprocessed URL and mime-type as returned from PHPCrawler
	 * @return array An array comprising a processed URL and its Mime-Type
	 */
	public function processURL($urlData);
}

/**
 * Processor for MOSS Standard-URLs while dropping file extensions
 */
class StaticSiteURLProcessor_DropExtensions implements StaticSiteUrlProcessor {
	
	public function getName() {
		return "Simple clean-up (recommended)";
	}

	public function getDescription() {
		return "Drop file extensions and trailing slashes on URLs but otherwise leave them the same";
	}

	/*
	 * @todo:
	 * - Find out the reason for the replacement of a trailing slash in URLs
	 * - These are needed if child-nodes are to be discovered and imported later
	 * 
	 * @param array $urlData
	 * @return array
	 */
	public function processURL($urlData) {
		$url = '';
		if(preg_match("#^([^?]*)\?(.*)$#", $urlData['url'], $matches)) {
			$url = $matches[1];
			$qs = $matches[2];
			$url = preg_replace("#\.[^.]*$#",'',$url);
			$url = $this->postProcessUrl($url);
			return array(
				'url'=>"$url?$qs",
				'mime'=>$urlData['mime']
			);
		} 
		else {
			$url = $urlData['url'];
			$url = preg_replace("#\.[^.]*$#",'',$url);
			return array(
				'url'=>$this->postProcessUrl($url),
				'mime'=>$urlData['mime']
			);
		}
	}
	
	/*
	 * Post-processes urls for common issues like encoded brackets and slashes that we wish to apply to all URL
	 * Processors.
	 * 
	 * @param string $url
	 * @return string
	 * @todo Instead of testing for arbitrary URL irregularities, 
	 * can we not just clean-out chars that not adhere to HTTP1.1 or the appropriate RFC?
	 */
	private function postProcessUrl($url) {
		// Replace all encoded slashes with non-encoded versions
		$noSlashes = str_ireplace('%2f', '/', $url);	
		// Replace all types of brackets
		$noBrackets = str_replace(array('%28','(',')'), '', $noSlashes);
		// Return, ensuring $url never has >1 consecutive '/'e.g. /blah//test
		return preg_replace("#/{2,}#", '/', $noBrackets);
	}
}
/**
 * Processor for MOSS URLs
 */
class StaticSiteMOSSURLProcessor extends StaticSiteURLProcessor_DropExtensions implements StaticSiteUrlProcessor {
	
	/**
	 * 
	 * @return string
	 */
	public function getName() {
		return "MOSS-style URLs";
	}

	/**
	 * 
	 * @return string
	 */
	public function getDescription() {
		return "Remove '/Pages/' from the URL, and drops extensions";
	}

	/**
	 * 
	 * @param array $urlData
	 * @return array
	 */
	public function processURL($urlData) {
		$url = str_ireplace('/Pages/','/',$urlData['url']);
		$urlData = array(
			'url'	=> $url,
			'mime'	=> $urlData['mime']
		);
		return parent::processURL($urlData);
	}
}
