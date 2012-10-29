<?php

class StaticSitePageTransformer implements ExternalContentTransformer {

	public function transform($item, $parentObject, $duplicateStrategy) {
		// Sleep for 100ms to reduce load on the remote server
		usleep(100*1000);

		// Extract content from the page

		// Get the import rules from the content source
		$importRules = $item->getSource()->getImportRules();

 		// Extract from the remote page based on those rules
		$contentExtractor = new StaticSiteContentExtractor($item->AbsoluteURL);
		$contentFields = $contentExtractor->extractMap($importRules);

		// Default value for Title
		if(empty($contentFields['Title'])) $contentFields['Title'] = $item->Name;

		// Default value for URL segment
		if(empty($contentFields['URLSegment'])) $contentFields['URLSegment'] = str_replace('/','', $item->Name);;
		
		// Create a page with the appropriate fields
		$page = new Page;
		$page->ParentID = $parentObject ? $parentObject->ID : 0;

		foreach($content as $k => $v) {
			$page->$k = $v;
		}

		$page->write();

		return new TransformResult($page, $item->stageChildren());
	}
}