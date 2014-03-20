<?php
/**
 * 
 * @package staticsiteconnector
 * @author Sam Minee <sam@silverstripe.com>
 * @author Russell Michell <russell@silverstripe.com>
 * @see \ExternalContentImporter
 * @see \StaticSiteImporterMetaCache
 */
class StaticSiteImporter extends ExternalContentImporter {
	
	/**
	 * 
	 * @return void
	 */
	public function __construct() {
		// Write some metadata about this import for later use
		$this->startImport();
		$this->contentTransforms['sitetree'] = new StaticSitePageTransformer();
		$this->contentTransforms['file'] = new StaticSiteFileTransformer();
	}

	/**
	 * 
	 * @param StaticSiteContentItem $item
	 * @return string
	 */
	public function getExternalType($item) {
		return $item->getType();
	}
	
	/**
	 * Runs the RewriteLinks BuildTask according to user selection and updates
	 * the import cache.
	 * 
	 * @return void
	 */
	public function onAfterImport() {
//		$taskRunner = TaskRunner::create();
//		$taskRequest = new SS_HTTPRequest;
//		$taskRunner->runTask($request);
		$this->endImport();
	}
	
	/**
	 * Writes a DataObject of this import. Its data is used by the StaticSIteRewriteLinksTask
	 * to allow it to identify which imported item(s) should have their links re-written.
	 * 
	 * @return void
	 */
	public function startImport() {
		$metaCache = StaticSiteImporterMetaCache::create();
		$metaCache->start();	
	}
	
	/*
	 * Writes a DataObject of this import. Its data is used by the StaticSIteRewriteLinksTask
	 * to allow it to identify which imported item(s) should have their links re-written.
	 * 
	 * @return void
	 */
	public function endImport() {
		// Get most recent import metadata record
		$metaCache = StaticSiteImporterMetaCache::get()->sort('ImportStartDate')->last();
		$metaCache->end();	
	}	
}
