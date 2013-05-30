<?php

class StaticSiteContentSource extends ExternalContentSource {

	public static $db = array(
		'BaseUrl' => 'Varchar(255)',
		'UrlProcessor' => 'Varchar(255)',
		'ExtraCrawlUrls' => 'Text',
		'UrlExcludePatterns' => 'Text',
	);

	public static $has_many = array(
		"Schemas" => "StaticSiteContentSource_ImportSchema",
		"Pages" => "SiteTree",
	);


	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$importRules = $fields->dataFieldByName('Schemas');
		$importRules->getConfig()->removeComponentsByType('GridFieldAddExistingAutocompleter');
		$importRules->getConfig()->removeComponentsByType('GridFieldAddNewButton');
		$addNewButton = new GridFieldAddNewButton('after');
		$addNewButton->setButtonName("Add schema");
		$importRules->getConfig()->addComponent($addNewButton);

		$fields->removeFieldFromTab("Root", "Schemas");
		$fields->removeFieldFromTab("Root", "Pages");
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
			$urlsAsUL = "<ul>";
			foreach($this->urlList()->getProcessedURLs() as $raw => $processed) {
				if($raw == $processed) {
					$urlsAsUL .= "<li>$processed</li>";
				} else {
					$urlsAsUL .= "<li>$processed <em>(was: $raw)</em></li>";
				}
			}
			$urlsAsUL .= "</ul>";

			$fields->addFieldToTab('Root.Crawl', 
				new LiteralField('CrawlURLList', "<p>The following URLs have been identified:</p>" . $urlsAsUL)
			);

			
		}

		$fields->dataFieldByName("ExtraCrawlUrls")
			->setDescription("Add URLs that are not reachable through content scraping, eg: '/about/team'. One per line")
			->setTitle('Additional URLs');
		$fields->dataFieldByName("UrlExcludePatterns")
			->setDescription("URLs that should be excluded (support regular expression). eg: '/about/.*'. One per URL")
			->setTitle('Excluded URLs');

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
			if($this->ExtraCrawlUrls) {
				$extraCrawlUrls = preg_split('/\s+/', trim($this->ExtraCrawlUrls));
				$this->urlList->setExtraCrawlUrls($extraCrawlUrls);
			}
			if($this->UrlExcludePatterns) {
				$urlExcludePatterns = preg_split('/\s+/', trim($this->UrlExcludePatterns));
				$this->urlList->setExcludePatterns($urlExcludePatterns);
			}
 		}
		return $this->urlList;
	}

	/**
	 * Crawl the target site
	 * @return StaticSiteCrawler
	 */
	public function crawl($limit=false, $verbose=false) {
		if(!$this->BaseUrl) throw new LogicException("Can't crawl a site until Base URL is set.");
		return $this->urlList()->crawl($limit, $verbose);
	}

	public function getSchemaForURL($absoluteURL) {
		// TODO: Return the right schema
		return $this->Schemas()->First();
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

	public function isValid() {
		if(!(boolean)$this->BaseUrl) {
			return false;
		}
		return true;
	}
	public function canImport($member = null) {
		return $this->isValid();
	}
	public function canCreate($member = null) {
		return true;
	}

}

/**
 * A collection of ImportRules that apply to some or all of the pages being imported.
 */
class StaticSiteContentSource_ImportSchema extends DataObject {
	public static $db = array(
		"DataType" => "Varchar", // classname
		"Order" => "Int",
		"AppliesTo" => "Varchar(255)", // regex
	);
	public static $summary_fields = array(
		"AppliesTo",
		"DataType",
		"Order",
	);
	public static $field_labels = array(
		"AppliesTo" => "URLs applied to",
		"DataType" => "Data type",
		"Order" => "Priority",
	);

	public static $default_sort = "Order";

	public static $has_one = array(
		"ContentSource" => "StaticSiteContentSource",
	);

	public static $has_many = array(
		"ImportRules" => "StaticSiteContentSource_ImportRule",
	);

	public function getTitle() {
		return $this->DataType.' ('.$this->AppliesTo.')';
	}

	/**
	 * 
	 * @return FieldList
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeFieldFromTab('Root.Main', 'DataType');
		$fields->removeByName('ContentSourceID');
		$dataObjects = ClassInfo::subclassesFor('DataObject');
		array_shift($dataObjects);
		natcasesort($dataObjects);
		$fields->addFieldToTab('Root.Main', new DropdownField('DataType', 'DataType', $dataObjects));

		$importRules = $fields->dataFieldByName('ImportRules');
		$importRules->getConfig()->removeComponentsByType('GridFieldAddExistingAutocompleter');
		$importRules->getConfig()->removeComponentsByType('GridFieldAddNewButton');
		$addNewButton = new GridFieldAddNewButton('after');
		$addNewButton->setButtonName("Add Rule");
		$importRules->getConfig()->addComponent($addNewButton);

		$fields->removeFieldFromTab('Root', 'ImportRules');
		$fields->addFieldToTab('Root.Main', $importRules);

		return $fields;
	}

	public function requireDefaultRecords() {
		foreach(StaticSiteContentSource::get() as $source) {
			if(!$source->Schemas()->count()) {
				Debug::message("Making a schema for $source->ID");
				$defaultSchema = new StaticSiteContentSource_ImportSchema;
				$defaultSchema->Order = 1000000;
				$defaultSchema->AppliesTo = ".*";
				$defaultSchema->DataType = "Page";
				$defaultSchema->ContentSourceID = $source->ID;
				$defaultSchema->write();


				foreach(StaticSiteContentSource_ImportRule::get()->filter(array('SchemaID' => 0)) as $rule) {
					$rule->SchemaID = $defaultSchema->ID;
					$rule->write();
				}
			}
		}
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
			$ruleArray = array(
				'selector' => $rule->CSSSelector,
				'attribute' => $rule->Attribute,
				'plaintext' => $rule->PlainText,
			);
			$output[$rule->FieldName][] = $ruleArray;
		}

		return $output;
	}

}

/**
 * A single import rule that forms part of an ImportSchema
 */
class StaticSiteContentSource_ImportRule extends DataObject {
	public static $db = array(
		"FieldName" => "Varchar",
		"CSSSelector" => "Text",
		"Attribute" => "Varchar",
		"PlainText" => "Boolean",
	);
	public static $summary_fields = array(
		"FieldName",
		"CSSSelector",
		"Attribute",
		"PlainText",
	);
	public static $field_labels = array(
		"FieldName" => "Field Name",
		"CSSSelector" => "CSS Selector",
		"Attribute" => "Element attribute",
		"PlainText" => "Convert to plain text",
	);

	public static $has_one = array(
		"Schema" => "StaticSiteContentSource_ImportSchema",
	);

	public function getTitle() {
		return ($this->FieldName)?$this->FieldName:$this->ID;
	}

	/**
	 *
	 * @return FieldList
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$dataType = $this->Schema()->DataType;
		if($dataType) {
			$fieldList = singleton($dataType)->inheritedDatabaseFields();
			$fieldList = array_combine(array_keys($fieldList),array_keys($fieldList));
			unset($fieldList->ParentID);
			unset($fieldList->WorkflowDefinitionID);
			unset($fieldList->Version);

			$fieldNameField = new DropdownField("FieldName", "Field Name", $fieldList);
			$fieldNameField->setEmptyString("(choose)");
			$fields->insertBefore($fieldNameField, "CSSSelector");
		} else {
			$fields->replaceField('FieldName', $fieldName = new ReadonlyField("FieldName", "Field Name"));
			$fieldName->setDescription('Save this rule before being able to add a field name');
		}

		return $fields;
	}
}