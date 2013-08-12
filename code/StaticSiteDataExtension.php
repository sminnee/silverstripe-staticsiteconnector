<?php

class StaticSiteDataExtension extends DataExtension {
	static $has_one = array(
		"StaticSiteContentSource" => "StaticSiteContentSource",
	);
	static $db = array(
		"StaticSiteURL" => "Varchar(255)"
	);

	function updateCMSFields(FieldList $fields) {
		if($this->owner->StaticSiteContentSourceID && $this->owner->StaticSiteURL) {
			$fields->addFieldToTab('Root.Main', new ReadonlyField('StaticSiteURL', 'Imported URL'), 'MenuTitle');
		}
		$aliases = $this->StaticSiteURLAliases();
		if($aliases->count()) {
			$printabelAliases = implode(',', $aliases->column('URL'));
			$fields->addFieldToTab('Root.Main', new ReadonlyField('StaticSiteAliases', 'Imported URL Aliases', $printabelAliases), 'MenuTitle');
		}
	}
	
	/**
	 * 
	 * @return DataList
	 */
	public function StaticSiteURLAliases() {
		return StaticSiteURLAlias::get_for_object($this->getOwner());
		
	}
	
}