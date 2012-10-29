<?php

class StaticSiteContentSource extends ExternalContentSource {

	public static $db = array(
		'BaseUrl' => 'Varchar(255)',
	);

	/*
	public function getCMSFields() {
	}
	*/

	public function urlList() {
		if(!$this->urlList) {
			$this->urlList = new StaticSiteUrlList($this->BaseUrl, "../assets/static-site");
		}
		return $this->urlList;
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