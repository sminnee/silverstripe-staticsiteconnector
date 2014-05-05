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
	 * Customise the output of the FailedURLRewriteReport CSV export.
	 * 
	 * @return array
	 */
	public function summaryFields() {
		return array(
			'ContainedIn.Title' => 'Imported page',
			'Import.Created' => 'Import date',
			'ThirdPartyTotal' => 'No. 3rd Party Urls',
			'BadSchemeTotal' => 'No. Urls with bad-scheme',
			'NotImportedTotal' => 'No. Unimported Urls',
			'JunkTotal' => 'No. Junk Urls'
		);
	}
	
	/**
	 * Fetch the related SiteTree object's Title property.
	 * 
	 * @return string
	 */
	public function Title() {
		return $this->ContainedIn()->Title;
	}
	
	/**
	 * Get totals for each type of failed URL.
	 * 
	 * @param number $importID
	 * @param string $badLinkType e.g. 'NotImported'
	 * @return SS_List
	 */
	public function getBadImportData($importID, $badLinkType = null) {
		$default = new ArrayList();	
		
		$badLinks = DataObject::get(__CLASS__)
				->filter('ImportID', $importID)
				->sort('Created');
		
		if($badLinks) {
			if($badLinkType) {
				return $badLinks->filter('BadLinkType', $badLinkType);
			}
			return $badLinks;
		}
		
		return $default;
	}	

}
