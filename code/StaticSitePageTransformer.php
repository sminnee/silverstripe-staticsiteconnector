<?php
/**
 * URL transformer specific to SilverStripe's `SiteTree` class for use within the import functionality.
 *
 * @see {@link StaticSiteFileTransformer}
 * @package staticsiteconnector
 * @author Sam Minee <sam@silverstripe.com>
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSitePageTransformer implements ExternalContentTransformer {
	
	/**
	 *
	 * @var number
	 */
	protected static $parent_id = 1; // Default to home
	
	/**
	 * 
	 * @var string
	 */
	public static $import_root = 'import-home';

	/**
	 * Holds the StaticSiteUtils object on construct
	 * 
	 * @var StaticSiteUtils
	 */
	protected $utils;
	
	/**
	 * 
	 * @var number
	 */
	public $importID = 0;
	
	/**
	 * Set this by using the yml config system
	 *
	 * Example:
	 * <code>
	 * StaticSiteContentExtractor:
     *    log_file:  ../logs/import-log.txt
	 * </code>
	 *
	 * @var string
	 */
	private static $log_file = null;	

	/**
	 * 
	 * @return void
	 */
	public function __construct() {
		$this->utils = singleton('StaticSiteUtils');
		$this->importID = $this->getCurrentImportID();
	}

	/**
	 * Generic function called by \ExternalContentImporter
	 * 
	 * @param ExternalContentItem $item
	 * @param SiteTree $parentObject
	 * @param string $strategy
	 * @return boolean | StaticSiteTransformResult
	 * @throws Exception
	 */
	public function transform($item, $parentObject, $strategy) {

		$this->utils->log("START transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);

		$item->runChecks('sitetree');
		if($item->checkStatus['ok'] !== true) {
			$this->utils->log(' - '.$item->checkStatus['msg']." for: ",$item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$source = $item->getSource();

		// Sleep for 100ms to reduce load on the remote server
		usleep(100*1000);		

		// Extract content from the page
		$contentFields = $this->getContentFieldsAndSelectors($item);

		// Default value for Title
		if(empty($contentFields['Title'])) {
			$contentFields['Title'] = array('content' => $item->Name);
		}

		// Default value for URLSegment
		if(empty($contentFields['URLSegment'])) {
			// $item->Name comes from StaticSiteContentItem::init() and is a URL
			$name = ($item->Name == '/' ? self::$import_root : $item->Name);
			$urlSegment = preg_replace('#\.[^.]*$#', '', $name); // Lose file-extensions e.g .html
			$contentFields['URLSegment'] = array('content' => $urlSegment);	
		}
		
		// Default value for Content (Useful for during unit-testing)
		if(empty($contentFields['Content'])) {
			$contentFields['Content'] = array('content' => 'dummy');
		}

		// Get a user-defined schema suited to this URL and Mime
		$schema = $source->getSchemaForURL($item->AbsoluteURL, $item->ProcessedMIME);
		if(!$schema) {
			$this->utils->log(" - Couldn't find an import schema for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$pageType = $schema->DataType;

		if(!$pageType) {
			$this->utils->log(" - DataType for migration schema is empty for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			throw new Exception('DataType for migration schema is empty!');
		}
		
		// Process incoming according to user-selected duplication strategy
		if(!$page = $this->processStrategy($pageType, $strategy, $item, $source->BaseUrl, $parentObject)) {
			$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}
		
		$page->StaticSiteContentSourceID = $source->ID;
		$page->StaticSiteURL = $item->AbsoluteURL;
		$page->StaticSiteImportID = $this->$importID;
		$page->Status = 'Published';
		
		foreach($contentFields as $property => $map) {
			// Don't write anything new, if we have nothing new to write (useful during unit-testing)
			if(!empty($map['content'])) {
				$page->$property = $map['content'];
			}
		}
		
		Versioned::reading_stage('Stage');
		$page->write();
		$page->publish('Stage', 'Live');

		$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);

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
	
	/**
	 * Process incoming content according to CMS user-inputted duplication strategy.
	 * 
	 * @param string $pageType
	 * @param string $strategy
	 * @param StaticSiteContentItem $item
	 * @param string $baseUrl
	 * @param SiteTree $parentObject
	 * @return boolean | $page SiteTree
	 * @todo Add tests
	 */
	protected function processStrategy($pageType, $strategy, $item, $baseUrl, $parentObject) {
		// Is the page already imported?
		$baseUrl = rtrim($baseUrl, '/');
		$existing = $pageType::get()->filter('StaticSiteURL', $baseUrl.$item->getExternalId())->first();
		if($existing) {
			if($strategy === ExternalContentTransformer::DS_OVERWRITE) {
				// "Overwrite" == Update
				$page = $existing;
				$page->ParentID = $existing->ParentID;
			}
			else if($strategy === ExternalContentTransformer::DS_DUPLICATE) {
				$page = $existing->duplicate(false);
				$page->ParentID = ($parentObject ? $parentObject->ID : self::$parent_id);
			}
			else {
				// Deals-to "skip" and no selection
				return false;
			}
		}
		else {
			$page = new $pageType();
			$page->ParentID = ($parentObject ? $parentObject->ID : self::$parent_id);
		}
		return $page;
	}
	
	/**
	 * Get the ID of the current StaticSiteContentImporter which will start and write to 
	 * a StaticSiteImportData object on construct.
	 * 
	 * @return number
	 */
	protected function getCurrentImportID() {
		$importer = singleton('StaticSiteImporter');
		if($currentImport = $importer->getCurrent()) {
			return $currentImport->ID;
		}
		return 0;
	}
}
