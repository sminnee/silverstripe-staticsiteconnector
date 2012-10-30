<?php

class StaticSiteContentSource extends ExternalContentSource {

	public static $db = array(
		'BaseUrl' => 'Varchar(255)',
		'UrlProcessor' => 'Varchar(255)',
	);

	public static $has_many = array(
		"ImportRules" => "StaticSiteContentSource_ImportRule"
	);


	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$importRules = $fields->dataFieldByName('ImportRules');
		$importRules->getConfig()->removeComponentsByType('GridFieldAddExistingAutocompleter');
		$importRules->getConfig()->removeComponentsByType('GridFieldAddNewButton');
		$addNewButton = new GridFieldAddNewButton('after');
		$addNewButton->setButtonName("Add rule");
		$importRules->getConfig()->addComponent($addNewButton);

		$fields->removeFieldFromTab("Root", "ImportRules");
		$fields->addFieldToTab("Root.Main", new LiteralField("", "<p>Each import rule will import content for a field"
			. " by getting the results of a CSS selector.  If more than one rule exists for a field, then they will be"
			. " processed in the order they appear.  The first rule that returns content will be the one used.</p>"));
		$fields->addFieldToTab("Root.Main", $importRules);


		$processingOptions = array("" => "No pre-processing");
		foreach(ClassInfo::implementorsOf('StaticSiteUrlProcessor') as $processor) {
			$processorObj = new $processor;
			$processingOptions[$processor] = "<strong>" . Convert::raw2xml($processorObj->getName()) 
				. "</strong><br>" . Convert::raw2xml($processorObj->getDescription());
		}

		$fields->addFieldToTab("Root.Main", new OptionsetField("UrlProcessor", "URL processing", $processingOptions));


		switch($this->urlList()->getSpiderStatus()) {
		case "Not started":
			$crawlButtonText = _t('StaticSiteContentSource.CRAWL_SITE', 'Crawl site');
			break;

		case "Partial":
			$crawlButtonText = _t('StaticSiteContentSource.RESUME_CRAWLING', 'Resume crawling');
			break;

		case "Complete":
			$crawlButtonText = _t('StaticSiteContentSource.RECRAWL_SITE', 'Re-crawl site');
			break;

		default:
			throw new LogicException("Invalid getSpiderStatus() value '".$this->urlList()->getSpiderStatus().";");
		}
		

		$crawlButton = FormAction::create('crawlsite', $crawlButtonText)
			->setAttribute('data-icon', 'arrow-circle-double')
			->setUseButtonTag(true);
		$fields->addFieldsToTab('Root.Crawl', array(
			new ReadonlyField("CrawlStatus", "Crawling Status", $this->urlList()->getSpiderStatus()),
			new ReadonlyField("NumURLs", "Number of URLs", $this->urlList()->getNumURLs()),

			new LiteralField('CrawlActions', 
			"<p>Before importing this content, all URLs on the site must be crawled (like a search engine does). Click"
			. " the button below to do so:</p>"
			. "<div class='Actions'>{$crawlButton->forTemplate()}</div>")
		));

		if($this->urlList()->getSpiderStatus() == "Complete") {
			$urlsAsUL = "<ul><li>" . implode("</li><li>", $this->urlList()->getURLs()) . "</li></ul>";

			$fields->addFieldToTab('Root.Crawl', 
				new LiteralField('CrawlURLList', "<p>The following URLs have been identified:</p>" . $urlsAsUL)
			);

			
		}

		return $fields;
	}

	public function onAfterWrite() {
		parent::onAfterWrite();

		$urlList = $this->urlList();
		if($this->isChanged('UrlProcessor') && $urlList->hasCrawled()) {
			if($processorClass = $this->UrlProcessor) {
				$urlList->setUrlProcessor(new $processorClass);
			} else {
				$urlList->setUrlProcessor(null);
			}
			$urlList->reprocessUrls();
		}
	}


	public function urlList() {
		if(!$this->urlList) {
			$this->urlList = new StaticSiteUrlList($this->BaseUrl, "../assets/static-site-" . $this->ID);
			if($processorClass = $this->UrlProcessor) {
				$this->urlList->setUrlProcessor(new $processorClass);
			}
		}
		return $this->urlList;
	}

	/**
	 * Crawl the target site
	 * @return [type] [description]
	 */
	public function crawl() {
		if(!$this->BaseUrl) throw new LogicException("Can't crawl a site until Base URL is set.");
		return $this->urlList()->crawl();
	}

	/**
	 * Return the import rules in a format suitable for configuring StaticSiteContentExtractor.
	 * 
	 * @return array A map of field name => array(CSS selector, CSS selector, ...)
	 */
	public function getImportRules() {
		$output = array();

		foreach($this->ImportRules() as $rule) {
			if(!isset($output[$rule->FieldName])) $output[$rule->FieldName] = array();
			$output[$rule->FieldName][] = $rule->CSSSelector;
		}

		return $output;
	}

	/**
	 * Returns a StaticSiteContentItem for the given URL.
	 * Relative URLs are used as the unique identifiers by this importer
	 * 
	 * @param $id The URL, relative to BaseURL, starting with "/".
	 * @return DataObject
	 */
	public function getObject($id) {

		if($id[0] != "/") {
			$id = $this->decodeId($id);
			if($id[0] != "/") throw new InvalidArgumentException("\$id must start with /");
		}

		return new StaticSiteContentItem($this, $id);
	}

	public function getRoot() {
		return $this->getObject('/');
	}

	public function allowedImportTargets() {
		return array('sitetree' => true);
	}

	/**
	 * Return the root node
	 * @return ArrayList A list containing the root node
	 */
	public function stageChildren($showAll = false) {
		if(!$this->urlList()->hasCrawled()) return new ArrayList;

		return new ArrayList(array(
			$this->getObject("/")
		));

	}

	public function getContentImporter($target=null) {
		return new StaticSiteImporter();
	}
	/*
	public function encodeId($id) {
		return $id;
	}
	public function decodeId($id) {
		return $id;
	}
 	*/
	public function isValid() {
		return (boolean)$this->BaseUrl;
	}
	public function canImport($member = null) {
		return $this->isValid();
	}
	public function canCreate($member = null) {
		return true;
	}

}

class StaticSiteContentSource_ImportRule extends DataObject {
	public static $db = array(
		"FieldName" => "Varchar",
		"CSSSelector" => "Text",
	);
	public static $summary_fields = array(
		"FieldName",
		"CSSSelector",
	);
	public static $field_labels = array(
		"FieldName" => "Field Name",
		"CSSSelector" => "CSS Selector",
	);

	public static $has_one = array(
		"ContentSource" => "StaticSiteContentSource",
	);

	function getCMSFields() {
		$fields = parent::getCMSFields();

		$fieldList = singleton('Page')->inheritedDatabaseFields();
		$fieldList = array_combine(array_keys($fieldList),array_keys($fieldList));
		unset($fieldList->ParentID);
		unset($fieldList->WorkflowDefinitionID);
		unset($fieldList->Version);

		$fieldNameField = new DropdownField("FieldName", "Field Name", $fieldList);
		$fieldNameField->setEmptyString("(choose)");
		$fields->insertBefore($fieldNameField, "CSSSelector");

		return $fields;
	}
}