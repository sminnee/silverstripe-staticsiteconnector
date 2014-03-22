<?php
/**
 * Caches some metadata for each import.
 * 
 * @package staticsiteconnector
 * @author Russell Michell <russell@silverstripe.com>
 */
class StaticSiteImporterMetaCache extends DataObject {
	
	/**
	 *
	 * @var array
	 */
	public static $db = array(
		'Ended' => 'Datetime'
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
	 * @return void
	 */
	public function start() {
		$this->User = Member::currentUser();
		$this->write();
	}
	
	/**
	 * Called at the end of an import.
	 * 
	 * @return void
	 */	
	public function end() {
		$this->Ended = DBField::create_field('Datetime', time());
		$this->write();
	}	
	
}
