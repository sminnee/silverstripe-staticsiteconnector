<?php

/**
 * StaticSiteURLAlias
 *
 */
class StaticSiteURLAlias extends DataObject {

	public static $db = array(
		'URL' => 'Varchar(255)',
		'ObjectClass' => 'Varchar(255)',
		'ObjectID' => 'Int'
	);
	
	/**
	 * 
	 * @param DataObject $object
	 */
	public static function get_for_object(DataObject $object) {
		return StaticSiteURLAlias::get()->filter(array(
			'ObjectClass' => $object->class,
			'ObjectID' => $object->ID
		));
	}
	
	/**
	 * 
	 * @param DataObject $object
	 * @param type $aliases
	 */
	public static function set_object(DataObject $object, $aliases) {
		$existing = StaticSiteURLAlias::get()->filter(array(
			'ObjectClass' => $object->class,
			'ObjectID' => $object->ID
		))->map('URL', 'ID');
		
		$list = $existing->toArray();
		
		foreach($aliases as $alias) {
			if(empty($list[$alias])) {
				$aliasObject = new StaticSiteURLAlias();
				$aliasObject->URL = $alias;
				$aliasObject->ObjectClass = $object->class;
				$aliasObject->ObjectID = $object->ID;
				$aliasObject->write();
				var_dump($alias);
			}
		}
	}
}
