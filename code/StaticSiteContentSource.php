<?php

class StaticSiteContentSource extends ExternalContentSource {

	public static $db = array(
		'BaseUrl' => 'Varchar(255)',
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
		$fields->addFieldToTab("Root.Main", $importRules);

		return $fields;
	}


	public function urlList() {
		if(!$this->urlList) {
			$this->urlList = new StaticSiteUrlList($this->BaseUrl, "../assets/static-site");
		}
		return $this->urlList;
	}

	/**
	 * Return the import rules in a format suitable for configuring StaticSiteContentExtractor.
	 * 
	 * @return array A map of field name => CSS selector
	 */
	public function getImportRules() {
		return $this->ImportRules()->map("FieldName", "CSSSelector")->toArray();
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