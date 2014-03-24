<?php
/**
 * Caches some metadata for each import, "shadowing" the import and "allowing" imports
 * to have DataObject-like functionality.
 * 
 * @author Russell Michell <russell@silverstripe.com>
 * @package staticsiteconnector
 * @see ExternalContentImporter
 */
class StaticSiteImportDataObject extends DataObject {
	
	/**
	 *
	 * @var array
	 */
	public static $db = array(
		'Ended' => 'SS_Datetime'
	);
	
	/**
	 *
	 * @var array
	 */
	public static $has_one = array(
		'User' => 'Member'
	);
	
	/**
	 * Called at the start of an import.
	 * 
	 * @return StaticSiteImportDataObject
	 */
	public function start() {
		$this->UserID = Member::currentUserID();
		$this->write();
		return $this;
	}
	
	/**
	 * Called at the end of an import.
	 * 
	 * @return StaticSiteImportDataObject
	 */	
	public function end() {
		$this->Ended = SS_Datetime::now()->getValue();
		$this->write();
		return $this;
	}	
	
	/**
	 * Get the most recently started/run import.
	 * 
	 * @param $member Member
	 * @return null | DataList
	 */
	public static function current($member = null) {
		if(!$member) {
			$member = Member::currentUser();
		}
		
		return StaticSiteImportDataObject::get()
				->filter('UserID', $member->ID)
				->sort('Created')
				->last();
	}
	
}
