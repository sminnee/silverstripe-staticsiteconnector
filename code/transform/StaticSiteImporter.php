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
		
		$importID = $current->ID;
		$this->runRewriteLinksTask($importID);
	}	
	
	/**
	 * 
	 * @param number $importID
	 * @return void
	 * @todo How to interject with external-content's "Import Complete" message to only show when
	 * this method has completed?
	 * @todo Use the returned task output, and display on-screen deploynaut style
	 */
	protected function runRewriteLinksTask($importID) {
		$params = Controller::curr()->getRequest()->postVars();
		$sourceID = !empty($params['ID']) ? $params['ID'] : 0;
		$autoRun = !empty($params['AutoRunTask']) ? $params['AutoRunTask'] : null;
		
		if($sourceID && $autoRun) {
			$task = TaskRunner::create();
			$getVars = array(
				'SourceID' => $sourceID,
				'ImportID' => $importID
			);
			
			// Skip TaskRunner. Too few docs available on its use
			$request = new SS_HTTPRequest('GET', '/dev/tasks/StaticSiteRewriteLinksTask', $getVars);
			$inst = Injector::inst()->create('StaticSiteRewriteLinksTask');
			$inst->run($request);
		}		
	}
}
