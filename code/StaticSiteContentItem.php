<?php
/*
 * Deals-to transforming imported SiteTree and File objects
 */
class StaticSiteContentItem extends ExternalContentItem {
	public function init() {
		$url = $this->externalId;

		$processedURL = $this->source->urlList()->processedURL($url);
		$parentURL = $this->source->urlList()->parentProcessedURL($processedURL);
		$subURL = substr($processedURL['url'], strlen($parentURL['url']));
		if($subURL != "/") $subURL = preg_replace('#(^/)|(/$)#','',$subURL);

		$this->Name = $subURL;
		$this->Title = $this->Name;
		$this->AbsoluteURL = preg_replace('#/$#','', $this->source->BaseUrl) . $this->externalId;
		$this->ProcessedURL = $processedURL['url'];
		$this->ProcessedMIME = $processedURL['mime'];
		
		$mimeProcessor = singleton('MimeTypeProcessor');
		$isMimeOfFle = $mimeProcessor->isOfFile($this->ProcessedMIME);
		$isMimeOfImg = $mimeProcessor->isOfImage($this->ProcessedMIME);
		if($isMimeOfFle || $isMimeOfImg) {
			// Show CMS view of where $file->Filepath was saved to in assets...
		}
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
		if(singleton('MimeTypeProcessor')->isOfFileOrImage($this->ProcessedMIME)) {
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
}
