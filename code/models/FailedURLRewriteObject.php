<?php
/**
 * A model object that represents a single failed link-rewrite during the 
 * running of the StaticSiteRewriteLinksTask. This data is then used to power the 
 * {@link FailedURLRewriteReport}.
 * 
 * @see {@link StaticSiteLinkRewriteTask}
 * @package staticsiteconnector
 * @author Russell Michell <russ@silverstripe.com>
 */
class FailedURLRewriteObject extends DataObject {

	/**
	 *
	 * @var array
	 */
	public static $db = array(
		"BadLinkType" => "Enum('ThirdParty, BadScheme, NotImported, Junk', 'Junk')"
	);
	
	/**
	 * 
	 * @var array
	 */
	public static $has_one = array(
		'Import' => 'StaticSiteImportDataObject',
		'ContainedIn' => 'SiteTree'
	);
	
	/**
	 * Fetch the related SiteTree object's Title property.
	 * 
	 * @return string
	 */
	public function Title() {
		return $this->ContainedIn()->Title;
	}

}
