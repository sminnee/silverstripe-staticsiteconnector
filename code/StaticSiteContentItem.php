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

	public function init() {
		$url = $this->externalId;

		$processedURL = $this->source->urlList()->processedURL($url);
		$parentURL = $this->source->urlList()->parentProcessedURL($processedURL);
		$subURL = substr($processedURL['url'], strlen($parentURL['url']));
		if($subURL != "/") $subURL = preg_replace('#(^/)|(/$)#','',$subURL);

		$this->Name = $subURL;
		$this->Title = $this->Name;
		$this->AbsoluteURL = preg_replace('#/$#','', $this->source->BaseUrl) . $this->externalId;
		// "Faux" This is identical to AbsoluteURL except the value is normalised, used for filtering on to prevent duplicates. See $this#runChecks()
		$this->AbsoluteURLFaux = preg_replace('#/$#','', $this->source->BaseUrl) . $this->externalId;
		$this->ProcessedURL = $processedURL['url'];
		$this->ProcessedMIME = $processedURL['mime'];
	}

	public function stageChildren($showAll = false) {
		if(!$this->source->urlList()->hasCrawled()) return new ArrayList;

		$childrenURLs = $this->source->urlList()->getChildren($this->externalId);

		$children = new ArrayList;
		foreach($childrenURLs as $child) {
			$children->push($this->source->getObject($child));
		}

		return $children;
	}

	public function numChildren() {
		if(!$this->source->urlList()->hasCrawled()) return 0;

		return sizeof($this->source->urlList()->getChildren($this->externalId));
	}

	/*
	 * Returns the correct SS base-type based on the curent URLs Mime-Type and directs the module to use the correct transformation class
	 *
	 * @return string
	 * @todo Create a static array somewhere (_config??) comprising all legit mime-types, or fetch directly from IANA..
	 */
	public function getType() {
		if(singleton('StaticSiteMimeProcessor')->isOfFileOrImage($this->ProcessedMIME)) {
			return "file";
		}
		return 'sitetree';
	}

	/*
	 * Returns the correct content-object transformation class
	 *
	 * @return ExternalContentTransformer
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
	 * Performs some checks on $item. If it is the wrong type, or if it has already been imported by matching on StaticSiteURL, it returns false
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

		$SSClass = ($type == 'sitetree' ? 'SiteTree' : 'File');

		/*
		 * By default, the crawler crawls everything to allow users fine-grained control over what is imported using Schema and Schema rules.
		 * During some crawls however, multiple URLs can be found that appear to point to the same canonical location.
		 * This causes issues for StaticSiteRewriteLinksTask which sees them as totally different URLs and cannot rewrite them.
		 *
		 * e.g.
		 * - /foo/Bar Fu Ba/foo
		 * - /foo/Bar%20Fu%20Ba/foo
		 * - /foo/bar fu ba/foo
		 * - /foo/bar%20fu%20ba/foo
		 *
		 * This logic prevents the importation of duplicates
		 */
		// Normalise what's already in the DB with what's being imported
		$this->AbsoluteURLFaux = str_replace(array(' ','%20'),array('-','-'),strtolower(trim($this->AbsoluteURLFaux)));
		// Here's why we use $this->AbsoluteURLFaux: So $this->AbsoluteURL can still be used to show "imported URL" in the imported content,
		// but we also get to filter out duplicated content
		$found = ($SSClass::get()->filter(array('StaticSiteURLFaux'=>$this->AbsoluteURLFaux))->count() >0);

		if($found) {
			$this->checkStatus = array(
				'ok'	=> false,
				'msg'	=> 'Item already imported!'
			);
		}
	}
}
