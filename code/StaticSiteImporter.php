<?php
/**
 * @see \ExternalContentImporter
 */
class StaticSiteImporter extends ExternalContentImporter {
	
	/**
	 * @return false
	 */
	public function __construct() {
		$this->contentTransforms['sitetree'] = new StaticSitePageTransformer();
		$this->contentTransforms['file'] = new StaticSiteFileTransformer();
	}

	/**
	 * 
	 * @param type $item
	 * @return string
	 */
	public function getExternalType($item) {
		return $item->getType();
	}
}
