<?php

class StaticSiteImporter extends ExternalContentImporter {
	public function __construct() {
		$this->contentTransforms['sitetree'] = new StaticSitePageTransformer();
		$this->contentTransforms['file'] = new StaticSiteFileTransformer();
	}

	public function getExternalType($item) {
		return $item->getType();
	}
}