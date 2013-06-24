<?php
/*
 * URL transformer specific to SilverStripe's `SiteTree` object for use within the import functionality.
 *
 * @see {@link StaticSiteFileTransformer}
 */
class StaticSitePageTransformer implements ExternalContentTransformer {

	public function transform($item, $parentObject, $duplicateStrategy) {
		// Workaround for external-content module:
		// - ExternalContentAdmin#migrate()  assumes we're _either_ dealing-to a SiteTree object _or_ a File object
		// - todo Bug report?
		if($item->getType() != 'sitetree') {
			return false;
		}

		// Sleep for 100ms to reduce load on the remote server
		usleep(100*1000);

		// Extract content from the page
		$contentFields = $this->getContentFieldsAndSelectors($item);

		// Default value for Title
		if(empty($contentFields['Title'])) {
			$contentFields['Title'] = array('content' => $item->Name);
		}

		// Default value for URL segment
		if(empty($contentFields['URLSegment'])) {
			$urlSegment = str_replace('/','', $item->Name);
			$urlSegment = preg_replace('/\.[^.]*$/','',$urlSegment);
			$urlSegment = str_replace('.','-', $item->Name);
			$contentFields['URLSegment'] = array('content' => $urlSegment);
		}

		$source = $item->getSource();
		$schema = $source->getSchemaForURL($item->AbsoluteURL,$item->ProcessedMIME);
		if(!$schema) {
			return false;
		}

		$pageType = $schema->DataType;

		if(!$pageType) {
			throw new Exception('Pagetype for migration schema is empty!');
		}

		// Create a page with the appropriate fields
		$page = new $pageType(array());
		$existingPage = SiteTree::get_by_link($item->getExternalId());

		if($existingPage && $duplicateStrategy === 'Overwrite') {
			if(get_class($existingPage) !== $pageType) {
				$existingPage->ClassName = $pageType;
				$existingPage->write();
			}
			if($existingPage) {
				$page = $existingPage;
			}
		}

		$page->StaticSiteContentSourceID = $source->ID;
		$page->StaticSiteURL = $item->AbsoluteURL;

		$page->ParentID = $parentObject ? $parentObject->ID : 0;

		foreach($contentFields as $k => $v) {
			$page->$k = $v['content'];
		}

		$page->write();

		return new StaticSiteTransformResult($page, $item->stageChildren());
	}

	/**
	 * Get content from the remote host
	 *
	 * @param  StaticSiteeContentItem $item The item to extract
	 * @return array A map of field name => array('selector' => selector, 'content' => field content)
	 */
	public function getContentFieldsAndSelectors($item) {
		// Get the import rules from the content source
		$importSchema = $item->getSource()->getSchemaForURL($item->AbsoluteURL,$item->ProcessedMIME);
		if(!$importSchema) {
			return null;
			throw new LogicException("Couldn't find an import schema for URL: {$item->AbsoluteURL} and Mime: {$item->ProcessedMIME}");
		}
		$importRules = $importSchema->getImportRules();

 		// Extract from the remote page based on those rules
		$contentExtractor = new StaticSiteContentExtractor($item->AbsoluteURL,$item->ProcessedMIME);

		return $contentExtractor->extractMapAndSelectors($importRules, $item);
	}
}