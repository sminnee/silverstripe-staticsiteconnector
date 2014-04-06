<?php
/**
 * @package staticsiteconnector
 * @author Sam Minee <sam@silverstripe.com>
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSiteDataExtension extends DataExtension {
	
	/**
	 * 
	 * @var array
	 */
	static $has_one = array(
		"StaticSiteContentSource" => "StaticSiteContentSource",
	);
	
	/**
	 * 
	 * @var array
	 */	
	static $db = array(
		"StaticSiteURL" => "Varchar(255)",
		"StaticSiteImportID" => "Int"
	);

	/**
	 * Show readonly fields of Import "Meta data"
	 * 
	 * @param FieldList $fields
	 * @return void
	 */
	public function updateCMSFields(FieldList $fields) {
		if($this->owner->StaticSiteContentSourceID && $this->owner->StaticSiteURL) {
			$fields->addFieldToTab('Root.Main', new ReadonlyField('StaticSiteURL', 'Imported URL'), 'MenuTitle');
			$fields->addFieldToTab('Root.Main', $importField = new ReadonlyField('StaticSiteImportID', 'Import ID'), 'MenuTitle');
			$importField->setDescription('Use this number to pass as the \'ImportID\' parameter to the StaticSiteRewriteLinksTask.');
		}		
	}
	
	/**
	 * Ensure related FailedURLRewriteObjects are also removed, when the related SiteTree
	 * object is deleted in the CMS.
	 * 
	 * @return void
	 */
	public function onAfterDelete() {
		parent::onAfterDelete();
		if($failedRewriteObjects = DataObject::get('FailedURLRewriteObject')->filter('ContainedInID', $this->owner->ID)) {
			$failedRewriteObjects->each(function($item) {
				$item->delete();
			});
		}
	}
}
