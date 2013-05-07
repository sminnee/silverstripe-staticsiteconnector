<?php

class StaticSiteContentItem extends ExternalContentItem {
	public function init() {
		$url = $this->externalId;

		$processedURL = $this->source->urlList()->processedURL($url); 
		$parentURL = $this->source->urlList()->parentProcessedURL($processedURL);

		$subURL = substr($processedURL, strlen($parentURL));
		if($subURL != "/") $subURL = preg_replace('#(^/)|(/$)#','',$subURL);
		
		$this->Name = $subURL;
		$this->Title = $this->Name;
		$this->AbsoluteURL = preg_replace('#/$#','', $this->source->BaseUrl) . $this->externalId;
		$this->ProcessedURL = $processedURL;
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

	public function getType() {
		return "sitetree";
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		// Add the preview fields here, including rules used
		$t = new StaticSitePageTransformer;

		$urlField = new ReadonlyField("PreviewSourceURL", "Imported from",
			"<a href=\"$this->AbsoluteURL\">" . Convert::raw2xml($this->AbsoluteURL) . "</a>");
		$urlField->dontEscape = true;

		$fields->addFieldToTab("Root.Preview", $urlField);

		$content = $t->getContentFieldsAndSelectors($this);
		foreach($content as $k => $v) {
			$fields->addFieldToTab("Root.Preview", 
				new ReadonlyField("Preview$k", "$k<br>\n<em>" . $v['selector'] . "</em>", $v['content']));
		}

		return $fields;
	}
}	