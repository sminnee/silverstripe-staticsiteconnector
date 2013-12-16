<?php
/*
 * Deals-to transforming imported SiteTree and File objects
 */
class StaticSiteContentItem extends ExternalContentItem {

	/**
	 * @var array
	 *
	 * Stores information about an item whenever it is invoked
	 */
	public $checkStatus = array(
		'ok'	=>	true,
		'msg'	=>	null
	);

	/**
 	 * Default Content type, either 'sitetree', 'file' or false to disable the default
 	 * @var mixed (string | boolean)
 	 */
	private $default_content_type = 'sitetree';

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

	/*
	 * @return void
	 */
	public function init() {
		$url = $this->externalId;

		$processedURL = $this->source->urlList()->processedURL($url);
		$parentURL = $this->source->urlList()->parentProcessedURL($processedURL);
		$subURL = substr($processedURL['url'], strlen($parentURL['url']));
		if($subURL != "/") {
			$subURL = trim($subURL, '/');
		}

		// Just default values
		$this->Name = $subURL;
		$this->Title = $this->Name;
		$this->AbsoluteURL = rtrim($this->source->BaseUrl, '/') . $this->externalId;
		$this->ProcessedURL = $processedURL['url'];
		$this->ProcessedMIME = $processedURL['mime'];
	}

	/**
	 * 
	 * @param boolean $showAll
	 * @return \ArrayList
	 */
	public function stageChildren($showAll = false) {
		if(!$this->source->urlList()->hasCrawled()) {
			return new ArrayList;
		}

		$childrenURLs = $this->source->urlList()->getChildren($this->externalId);

		$children = new ArrayList;
		foreach($childrenURLs as $child) {
			$children->push($this->source->getObject($child));
		}

		return $children;
	}

	/**
	 * 
	 * @return number
	 */
	public function numChildren() {
		if(!$this->source->urlList()->hasCrawled()) {
			return 0;
		}

		return sizeof($this->source->urlList()->getChildren($this->externalId));
	}

	/*
	 * Returns the correct SS base-type based on the curent URL's Mime-Type and directs the module to use the correct StaticSiteXXXTransformer class
	 *
	 * @return mixed string|boolean
	 * @todo Create a static array somewhere (_config??) comprising all legit mime-types, or fetch directly from IANA..
	 */
	public function getType() {
		$mimeTypeProcessor = singleton('StaticSiteMimeProcessor');
		if($mimeTypeProcessor->isOfFileOrImage($this->ProcessedMIME)) {
			return "file";
		}
		if($mimeTypeProcessor->isOfHtml($this->ProcessedMIME)) {
			return "sitetree";
		}
		// Log everything that doesn't fit:
		singleton('StaticSiteUtils')->log('Schema not configured for Mime & URL: '. $this->ProcessedMIME, $this->AbsoluteURL, $this->ProcessedMIME);
		return $this->default_content_type;
	}

	/*
	 * Returns the correct content-object transformation class
	 *
	 * @return \ExternalContentTransformer
	 */
	public function getTransformer() {
		$type = $this->getType();
		if($type == 'file') {
			return new StaticSiteFileTransformer;
		}
		if($type == 'sitetree') {
			return new StaticSitePageTransformer;
		}
	}

	/*
	 * @return \FieldList
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		// Add the preview fields here, including rules used
		$t = $this->getTransformer();

		$urlField = new ReadonlyField("PreviewSourceURL", "Imported from",
			"<a href=\"$this->AbsoluteURL\">" . Convert::raw2xml($this->AbsoluteURL) . "</a>");
		$urlField->dontEscape = true;

		$fields->addFieldToTab("Root.Preview", $urlField);

		$content = $t->getContentFieldsAndSelectors($this);
		if(count($content) === 0) {
			return $fields;
		}
		foreach($content as $k => $v) {
			$readonlyField = new ReadonlyField("Preview$k", "$k<br>\n<em>" . $v['selector'] . "</em>", $v['content']);
			$readonlyField->addExtraClass('readonly-click-toggle');
			$fields->addFieldToTab("Root.Preview", $readonlyField);
		}

		Requirements::javascript('staticsiteconnector/js/StaticSiteContentItem.js');
		Requirements::css('staticsiteconnector/css/StaticSiteContentItem.css');

		return $fields;
	}

	/*
	 * Performs some checks on $item. If it is of the wrong type, returns false
	 *
	 * @param string $type e.g. 'sitetree'
	 * @return void
	 */
	public function runChecks($type) {
		/*
		 * Workaround for external-content module:
		 * - ExternalContentAdmin#migrate()  assumes we're _either_ dealing-to a SiteTree object _or_ a File object
		 * - @todo Bug report?
		 */
		if(!$type || $this->getType() != strtolower($type)) {
			$this->checkStatus = array(
				'ok'	=> false,
				'msg'	=> 'Item not of type '.$type
			);
		}
	}
}
