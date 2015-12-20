<?php

class StaticSiteDataExtension extends DataExtension
{
    public static $has_one = array(
        "StaticSiteContentSource" => "StaticSiteContentSource",
    );
    public static $db = array(
        "StaticSiteURL" => "Varchar(255)",
    );

    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner->StaticSiteContentSourceID && $this->owner->StaticSiteURL) {
            $fields->addFieldToTab('Root.Main', new ReadonlyField('StaticSiteURL', 'Imported URL'), 'MenuTitle');
        }
    }
}
