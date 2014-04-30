<?php
/**
 * A model object that represents a single failed link-rewrite during the 
 * running of the StaticSiteRewriteLinksTask. This data is then used to power the 
 * {@link FailedURLRewriteReport}.
 * 
 * @author Russell Michell <russ@silverstripe.com>
 * @package staticsiteconnector
 * @see {@link StaticSiteLinkRewriteTask}
 */
class FailedURLRewriteObject extends DataObject {

	/**
	 *
	 * @var array
	 */
	private static $db = array(
		"BadLinkType" => "Enum('ThirdParty, BadScheme, NotImported, Junk, Unknown', 'Unknown')",
		"OrigUrl" => "Varchar(255)"
	);
	
	/**
	 * 
	 * @var array
	 */
	private static $has_one = array(
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
