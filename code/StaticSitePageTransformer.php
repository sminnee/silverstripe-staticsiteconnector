<?php
/**
 * URL transformer specific to SilverStripe's `SiteTree` class for use within the import functionality.
 *
 * @package staticsiteconnector
 * @see {@link StaticSiteFileTransformer}
 * @author Sam Minee <sam@silverstripe.com>
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSitePageTransformer implements ExternalContentTransformer {

	/**
	 * Holds the StaticSiteUtils object on construct
	 * 
	 * @var StaticSiteUtils
	 */
	protected $utils;

	/**
	 * 
	 * @return void
	 */
	public function __construct() {
		$this->utils = singleton('StaticSiteUtils');
	}

	/**
	 * Generic function called by \ExternalContentImporter
	 * 
	 * @param type $item
	 * @param type $parentObject
	 * @param string $strategy
	 * @return boolean|\StaticSiteTransformResult
	 * @throws Exception
	 */
	public function transform($item, $parentObject, $strategy) {

		$this->utils->log("START transform for: ",$item->AbsoluteURL, $item->ProcessedMIME);

		$item->runChecks('sitetree');
		if($item->checkStatus['ok'] !== true) {
			$this->utils->log($item->checkStatus['msg']." for: ",$item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$source = $item->getSource();

		// Cleanup StaticSiteURLs
		$cleanupStaticSiteUrls = false;
		if ($cleanupStaticSiteUrls) {
			$this->utils->resetStaticSiteURLs($item->AbsoluteURL, $source->ID, 'SiteTree');
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
		
		// Default value for Content (Useful for during unit-testing)
		if(empty($contentFields['Content'])) {
			$contentFields['Content'] = array('content' => 'dummy');
		}

		$schema = $source->getSchemaForURL($item->AbsoluteURL, $item->ProcessedMIME);
		if(!$schema) {
			$this->utils->log("Couldn't find an import schema for: ", $item->AbsoluteURL,$item->ProcessedMIME);
			return false;
		}

		$pageType = $schema->DataType;

		if(!$pageType) {
			$this->utils->log("DataType for migration schema is empty for: ", $item->AbsoluteURL,$item->ProcessedMIME);
			throw new Exception('Pagetype for migration schema is empty!');
		}

		// Check if the page is already imported and decide what to do
		$existingPage = $pageType::get()->filter('StaticSiteURL', $item->getExternalId())->first();

		/*
		 * Conditions are:
		 *	1). existing AND overwrite
		 *	2). existing AND skip
		 *	3). existing AND duplicate
		 *	4). non-existent
		 */		
		if($existingPage) {
			if(get_class($existingPage) !== $pageType) {
				$existingPage->ClassName = $pageType;
				$existingPage->write();
			}
			if($existingPage && $existingPage->ID) {
				$page = $existingPage;
			}
		}
		else {
			$page = new $pageType(array());
		}
		
		if($strategy === ExternalContentTransformer::DS_OVERWRITE) {
			$copy = $page;
			$page->delete();
			$copy->write();
			$page = $copy;
		}
		if($strategy === ExternalContentTransformer::DS_DUPLICATE) {
			$page = $page->duplicate();
		}		
		if($strategy === ExternalContentTransformer::DS_SKIP) {
			return false;
		}

		$page->StaticSiteContentSourceID = $source->ID;
		$page->StaticSiteURL = $item->AbsoluteURL;
		$page->ParentID = $parentObject ? $parentObject->ID : 1; // Default to Home

		foreach($contentFields as $k => $v) {
			// Don't write anything new, if we have nothing new to write (useful during unit-testing)
			if($v['content']) {
				$page->$k = $v['content']; 
			}			
		}

		$page->write();

		$this->utils->log("END transform for: ",$item->AbsoluteURL, $item->ProcessedMIME);

		return new StaticSiteTransformResult($page, $item->stageChildren());
	}

	/**
	 * Get content from the remote host
	 *
	 * @param  StaticSiteeContentItem $item The item to extract
	 * @return null | array Map of field name=>array('selector' => selector, 'content' => field content)
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
