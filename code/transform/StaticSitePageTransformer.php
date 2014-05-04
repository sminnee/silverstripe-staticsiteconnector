<?php
/**
 * URL transformer specific to SilverStripe's `SiteTree` class for use with the module's
 * import content feature.
 * 
 * If enabled in the CMS UI, links to imported pages will be automatically re-written.
 *
 * @package staticsiteconnector
 * @author Sam Minee <sam@silverstripe.com>
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 * @see {@link StaticSiteDataTypeTransformer}
 */
class StaticSitePageTransformer extends StaticSiteDataTypeTransformer {
	
	/**
	 * 
	 * @var string
	 */
	public static $import_root = 'import-home';
	
	/**
	 * 
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->setParentId(1);
	}

	/**
	 * Generic function called by \ExternalContentImporter
	 * 
	 * @inheritdoc
	 */
	public function transform($item, $parentObject, $strategy) {

		$this->utils->log("START page-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);

		$item->runChecks('sitetree');
		if($item->checkStatus['ok'] !== true) {
			$this->utils->log(' - '.$item->checkStatus['msg']." for: ",$item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END page-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$source = $item->getSource();

		// Sleep for 100ms to reduce load on the remote server
		usleep(100*1000);		

		// Extract content from the page
		$contentFields = $this->getContentFieldsAndSelectors($item, 'SiteTree');

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
			$contentFields['Content'] = array('content' => 'No content found');
			$this->utils->log(" - No content found for 'Content' field.", $item->AbsoluteURL, $item->ProcessedMIME);
		}

		// Get a user-defined schema suited to this URL and Mime
		$schema = $source->getSchemaForURL($item->AbsoluteURL, $item->ProcessedMIME);
		if(!$schema) {
			$this->utils->log(" - Couldn't find an import schema for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END page-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$dataType = $schema->DataType;

		if(!$dataType) {
			$this->utils->log(" - DataType for migration schema is empty for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END page-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			throw new Exception('DataType for migration schema is empty!');
		}
		
		// Process incoming according to user-selected duplication strategy
		if(!$page = $this->duplicationStrategy($dataType, $strategy, $item, $source->BaseUrl, $parentObject)) {
			$this->utils->log("END page-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}
		
		$page->StaticSiteContentSourceID = $source->ID;
		$page->StaticSiteURL = $item->AbsoluteURL;
		$page->StaticSiteImportID = $this->getCurrentImportID();
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

		$this->utils->log("END page-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);

		return new StaticSiteTransformResult($page, $item->stageChildren());
	}
}
