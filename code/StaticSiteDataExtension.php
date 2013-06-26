<?php

class StaticSiteDataExtension extends DataExtension {
	static $has_one = array(
		"StaticSiteContentSource" => "StaticSiteContentSource",
	);
	static $db = array(
		"StaticSiteURL" => "Varchar(255)",
		"StaticSiteURLFaux" => "Varchar(255)",
	);

	function updateCMSFields(FieldList $fields) {
		if($this->owner->StaticSiteContentSourceID && $this->owner->StaticSiteURL) {
			$fields->addFieldToTab('Root.Main', new ReadonlyField('StaticSiteURL', 'Imported URL'), 'MenuTitle');
		}
	}
}