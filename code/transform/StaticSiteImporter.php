<?php
/**
 * Physically brings content into SilverStripe as defined by URLs fetched 
 * at the crawl stage, and utilises StaticSitePageTransformer and StaticSiteFileTransformer.
 * 
 * @package staticsiteconnector
 * @author Sam Minee <sam@silverstripe.com>
 * @author Russell Michell <russell@silverstripe.com>
 * @see {@link ExternalContentImporter}
 * @see {@link StaticSiteImportDataObject}
 */
class StaticSiteImporter extends ExternalContentImporter {
	
	/**
	 * 
	 * @return void
	 */
	public function __construct() {
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
	 * Run prior to the entire import process starting.
	 * 
	 * Creates an import DataObject record for hooking-into later with the link-processing logic.
	 * 
	 * @return void
	 */
	public function runOnImportStart() {
		parent::runOnImportStart();
		StaticSiteImportDataObject::create()->start();
	}
	
	/**
	 * Run right when the import process ends.
	 * 
	 * @return void
	 * @todo auto-run the StaticSiteRewriteLinksTask on import completion
	 *	- How to get sourceID to know which StaticSiteContentSource to fetch for a  "auto-rewrite" CMS field-value under the "Import" tab
	 */
	public function runOnImportEnd() {
		parent::runOnImportEnd();
		$current = StaticSiteImportDataObject::current();
		$current->end();
		
		$params = Controller::curr()->getURLParams();
		$sourceID = $params['ID'];
		$importID = $current->ID;
		$source = DataObject::get('StaticSiteContentSource')->filter('ID', $sourceID);
		
		if($source && $source->autoRunRewriteTask) {
			// Create the task runner to invoke dev/tasks/StaticSiteRewriteLinksTask
			// How?? See controller where dev/tasks is invoked as it does when you call index.php for the first time
		}
	}	
}
