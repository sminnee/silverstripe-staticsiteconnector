<?php
/**
 * URL transformer specific to SilverStripe's `SiteTree` object for use within the import functionality.
 *
 * @package staticsiteconnector
 * @see {@link StaticSiteFileTransformer}
 * @author Sam Minee <sam@silverstripe.com>
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSitePageTransformer implements ExternalContentTransformer {

	/*
	 * @var Object
	 *
	 * Holds the StaticSiteUtils object on construct
	 */
	protected $utils;

	/**
	 * @return void
	 */
	public function __construct() {
		$this->utils = singleton('StaticSiteUtils');
	}

	/**
	 *
	 * @param type $item
	 * @param type $parentObject
	 * @param type $duplicateStrategy
	 * @return boolean|\StaticSiteTransformResult
	 * @throws Exception
	 */
	public function transform($item, $parentObject, $duplicateStrategy) {

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

		$schema = $source->getSchemaForURL($item->AbsoluteURL,$item->ProcessedMIME);
		if(!$schema) {
			$this->utils->log("Couldn't find an import schema for: ",$item->AbsoluteURL,$item->ProcessedMIME);
			return false;
		}

		$pageType = $schema->DataType;

		if(!$pageType) {
			$this->utils->log("DataType for migration schema is empty for: ",$item->AbsoluteURL,$item->ProcessedMIME);
			throw new Exception('Pagetype for migration schema is empty!');
		}

		// Check if the page is already imported and decide what to do depending on the CMS-selected strategy (overwrite/skip etc)
		// Fake it when running tests
		if(SapphireTest::is_running_test()) {
			$existingPage = new $pageType(array());
		}	
		else {
			$existingPage = $pageType::get()->filter('StaticSiteURL',$item->getExternalId())->first();
		}

		/*
		 * @todo to "Overwrite" strategy isn't working. To "overwrite" something is to:
		 * - Delete it
		 * - Write a new one
		 */		
		if($existingPage && $duplicateStrategy === 'Overwrite') {
			if(get_class($existingPage) !== $pageType) {
				$existingPage->ClassName = $pageType;
				$existingPage->write();
			}
			if($existingPage) {
				$page = $existingPage;
			}
		}
		else if($existingPage && $duplicateStrategy === 'Skip') {
			return false;
		}
		else {
			// This deals to the "Duplicate" strategy, as well as creating new, non-existing objects
			$page = new $pageType(array());
			$page->Title = $contentFields['Title'];
			$page->MenuTitle = $contentFields['Title'];
			$page->URLSegment = $contentFields['URLSegment'];
			$page->Content = $contentFields['Content'];
		}

		$page->StaticSiteContentSourceID = $source->ID;
		$page->StaticSiteURL = $item->AbsoluteURL;
		$page->ParentID = $parentObject ? $parentObject->ID : 0;

		foreach($contentFields as $k => $v) {
			$page->$k = $v['content'];
		}

		$page->write();

		$this->utils->log("END transform for: ",$item->AbsoluteURL, $item->ProcessedMIME);

		return new StaticSiteTransformResult($page, $item->stageChildren());
	}

	/**
	 * Get content from the remote host
	 *
	 * @param  StaticSiteeContentItem $item The item to extract
	 * @return null | array A map of field name => array('selector' => selector, 'content' => field content)
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
