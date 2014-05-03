<?php
/**
 * Base content transformer. Comprises logic common to all types of legacy/scraped text
 * and binary content for import into native SilverStripe DataObjects.
 * 
 * Use this as your starting-point for creating custom content transformers for other data-types.
 * 
 * Hint: You'll need to use the returned object from getContentFieldsAndSelectors() if the dataType
 * you wish to work with is not 'File' or 'SiteTree'.
 * 
 * @todo
 *	- duplicationStrategy() make $parentObject optional as not all imports need have a parent
 *	- getContentFieldsAndSelectors() Add unit tests for when a non-SS DataType is passed as 2nd param
 * 
 * @package staticsiteconnector
 * @author Sam Minee <sam@silverstripe.com>
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 * @see {@link StaticSitePageTransformer}
 * @see {@link StaticSiteFileTransformer}
 */
abstract class StaticSiteDataTypeTransformer implements ExternalContentTransformer {

	/**
	 * Holds the StaticSiteUtils object on construct
	 * 
	 * @var StaticSiteUtils
	 */
	public $utils;	
	
	/**
	 * @var StaticSiteMimeProcessor
	 *
	 * $mimeTypeProcessor
	 */
	public $mimeProcessor;	

	/**
	 * 
	 * @return void
	 */
	public function __construct() {
		$this->utils = Injector::inst()->get('StaticSiteUtils', true);
		$this->mimeProcessor = Injector::inst()->get('StaticSiteMimeProcessor', true);
	}

	/**
	 * Generic function called by \ExternalContentImporter
	 * 
	 * @param ExternalContentItem $item
	 * @param DataObject $parentObject
	 * @param string $strategy
	 * @return boolean | StaticSiteTransformResult
	 * @throws Exception
	 */
	abstract public function transform($item, $parentObject, $strategy);

	/**
	 * Get content from remote datasource (e.g. a File, Image or page-text).
	 * If $dataType is anything but 'File' or 'SiteTree' a StaticSiteContentExtractor object
	 * is returned so sublclasses of StaticSiteDataTypeTransformer can implement custom logic
	 * based off it.
	 *
	 * @param StaticSiteContentItem $item The item to extract
	 * @param string $dataType e.g. 'File' or 'SiteTree'
	 * @return null | StaticSiteContentExtractor | array Map of SS field name=>array('selector' => selector, 'content' => field content)
	 */
	public function getContentFieldsAndSelectors($item, $dataType) {
		// Get the import rules from the content source
		$importSchema = $item->getSource()->getSchemaForURL($item->AbsoluteURL, $item->ProcessedMIME);
		if(!$importSchema) {
			$this->utils->log("Couldn't find an import schema for ", $item->AbsoluteURL, $item->ProcessedMIME, 'WARNING');
			return null;
		}
		$importRules = $importSchema->getImportRules();

 		// Extract from the remote content based on those rules
		$contentExtractor = new StaticSiteContentExtractor($item->AbsoluteURL, $item->ProcessedMIME);
		
		if($dataType == 'File') {
			$extraction = $contentExtractor->extractMapAndSelectors($importRules, $item);
			$extraction['tmp_path'] = $contentExtractor->getTmpFileName();
		}
		else if($dataType == 'SiteTree') {
			$extraction = $contentExtractor->extractMapAndSelectors($importRules, $item);			
		}
		else {
			// Allows for further data-types
			return $contentExtractor;
		}
		
		return $extraction;
	}
	
	/**
	 * Process incoming content according to CMS user-inputted duplication strategy.
	 * 
	 * @param string $dataType
	 * @param string $strategy
	 * @param StaticSiteContentItem $item
	 * @param string $baseUrl
	 * @param DataObject $parentObject
	 * @return boolean | $object
	 */
	protected function duplicationStrategy($dataType, $strategy, $item, $baseUrl, $parentObject) {
		// Has the object already been imported?
		$baseUrl = rtrim($baseUrl, '/');
		$existing = $dataType::get()->filter('StaticSiteURL', $baseUrl . $item->getExternalId())->first();
		if($existing) {		
			if($strategy === ExternalContentTransformer::DS_OVERWRITE) {
				// "Overwrite" == Update
				$object = $existing;
				$object->ParentID = $existing->ParentID;
			}
			else if($strategy === ExternalContentTransformer::DS_DUPLICATE) {
				$object = $existing->duplicate(false);
				$object->ParentID = ($parentObject ? $parentObject->ID : self::$parent_id);
			}
			else {
				// Deals-to "skip" and no selection
				return false;
			}
		}
		else {
			$object = new $dataType(array());
			$object->ParentID = ($parentObject ? $parentObject->ID : self::$parent_id);
		}
		return $object;
	}
	
	/**
	 * Get current import ID. If none can be found, start one and return that.
	 * 
	 * @return number
	 */
	public function getCurrentImportID() {
		if(!$import = StaticSiteImportDataObject::current()) {
			return 1;
		}
		return $import->ID;	
	}
	
	/**
	 * Build an array of file extensions. Utilised in buildFileProperties() to check 
	 * incoming file-extensions are valid against those found on {@link File}.
	 * 
	 * @return array $exts
	 */
	public function getSSExtensions() {
		$extensions = singleton('File')->config()->app_categories;
		$exts = array();
		foreach($extensions as $category => $extArray) {
			foreach($extArray as $ext) {
				$exts[] = $ext;
			}
		}
		return $exts;
	}	
}
