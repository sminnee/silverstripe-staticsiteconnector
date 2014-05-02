<?php
/**
 * 
 * @author Sam Minnee <sam@silverstripe.com>
 * @package staticsiteconnector
 */
class StaticSiteCrawlURLsTask extends BuildTask {

	/**
	 * 
	 * @param SS_HTTPRequest $request
	 * @return null
	 */
	public function run($request) {
		$id = $request->getVar('ID');
		if(!is_numeric($id) || !$id) {
			echo "<p>Specify ?ID=(number)</p>";
			return;
		}
		
		// Find all pages
		$contentSource = StaticSiteContentSource::get()->byID($id);
		$contentSource->urllist()->crawl(false, true);
	}

}
