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
	 * Creates an import shadow record for hooking into later with the link-processing logic.
	 */
	public function runOnImportStart() {
		parent::runOnImportStart();
		ImportShadow::create()->start();
	}
	
	/**
	 * Run right when the import process ends.
	 * 
	 */
	public function runOnImportEnd() {
		parent::runOnImportEnd();
		ImportShadow::current()->end();
	}	
}
