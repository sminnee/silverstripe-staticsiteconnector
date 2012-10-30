<?php

class StaticSitePageTransformer implements ExternalContentTransformer {

	public function transform($item, $parentObject, $duplicateStrategy) {
		// Sleep for 100ms to reduce load on the remote server
		usleep(100*1000);

		// Extract content from the page
		$contentFields = getContentFieldsAndSelectors($item);

		// Default value for Title
		if(empty($contentFields['Title'])) {
			$contentFields['Title'] = array('content' => $item->Name);
		}

		// Default value for URL segment
		if(empty($contentFields['URLSegment'])) {
			$contentFields['URLSegment'] = array('content' => str_replace('/','', $item->Name));
		}
		
		// Create a page with the appropriate fields
		$page = new Page;
		$page->ParentID = $parentObject ? $parentObject->ID : 0;

		foreach($contentFields as $k => $v) {
			$page->$k = $v['content'];
		}

		$page->write();

		return new TransformResult($page, $item->stageChildren());
	}

	/**
	 * Get content from the remote host
	 * 
	 * @param  StaticSiteeContentItem $item The item to extract
	 * @return array A map of field name => array('selector' => selector, 'content' => field content)
	 */
	public function getContentFieldsAndSelectors($item) {
		// Get the import rules from the content source
		$importRules = $item->getSource()->getImportRules();

 		// Extract from the remote page based on those rules
		$contentExtractor = new StaticSiteContentExtractor($item->AbsoluteURL);

		return $contentExtractor->extractMapAndSelectors($importRules);
	}
}