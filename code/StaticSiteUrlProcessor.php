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
	 * 
	 * This name will be shown in the CMS when users are configuring the content import.
	 * 
	 * @return string The name, in plaintext (no HTML)
	 */
	function getName();

	/**
	 * Return an explanation of what processing is done.
	 * 
	 * This explanation will be shown in the CMS when users are configuring the content import.
	 * 
	 * @return string The description, in plaintext (no HTML)
	 */
	function getDescription();


	/**
	 * Return a description for this processor, to be shown in the CMS.
	 * @param string $url The unprocessed URL
	 * @return string The name
	 */
	function processURL($url);
}

/**
 * Processor for MOSS URLs
 */
class StaticSiteURLProcessor_DropExtensions implements StaticSiteUrlProcessor {
	function getName() {
		return "Simple clean-up (recommended)";
	}

	function getDescription() {
		return "Drop file extensions and trailing slashes on URLs but otherwise leave them the same";
	}

	function processURL($url) {
		if(preg_match('/^([^?]*)\?(.*)$/', $url, $matches)) {
			$url = $matches[1];
			$qs = $matches[2];
			if($url != '/') $url = preg_replace('#/$#','',$url);
			$url = preg_replace('#\.[^.]*$#','',$url);
			return "$url?$qs";
		} else {
			if($url != '/') $url = preg_replace('#/$#','',$url);
			$url = preg_replace('#\.[^.]*$#','',$url);
			return $url;
		}
	}
}
/**
 * Processor for MOSS URLs
 */
class StaticSiteMOSSURLProcessor extends StaticSiteURLProcessor_DropExtensions implements StaticSiteUrlProcessor {
	function getName() {
		return "MOSS-style URLs";
	}

	function getDescription() {
		return "Remove '/Pages/' from the URL, and drop extensions";
	}

	function processURL($url) {
		$url = str_ireplace('/Pages/','/',$url);
		return parent::processURL($url);
	}
}
