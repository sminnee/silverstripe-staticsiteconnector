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
		$this->startRecording();
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
		$this->endRecording();
	}
	
	/**
	 * Writes a DataObject of this import. Its data is used by the StaticSIteRewriteLinksTask
	 * to allow it to identify which imported item(s) should have their links re-written.
	 * 
	 * @return void
	 */
	public function startRecording() {
		StaticSiteImporterMetaCache::create()->start();
	}
	
	/*
	 * Writes a DataObject of this import. Its data is used by the StaticSIteRewriteLinksTask
	 * to allow it to identify which imported item(s) should have their links re-written.
	 * 
	 * @return void
	 */
	public function endRecording() {
		// Get most recent/up-to-date import metadata record
		if($metaCache = $this->getCurrent()) {
			$metaCache->end();		
		}
	}	
	
	/**
	 * Get the most recently started/run import.
	 * 
	 * @param $member Member
	 * @return null | DataList
	 */
	public function getCurrent($member = null) {
		if(!$member) {
			$member = Member::currentUser();
		}
		
		return StaticSiteImporterMetaCache::get()
				->filter('UserID', $member->ID)
				->sort('Created')
				->last();
	}
}
