<?php
/**
 * Caches some metadata for each import. Allows imports to have some DataObject-like functionality.
 * 
 * @author Russell Michell <russ@silverstripe.com>
 * @package staticsiteconnector
 * @see {@link StaticSiteImporter}
 */
class StaticSiteImportDataObject extends DataObject {
	
	/**
	 *
	 * @var array
	 */
	private static $db = array(
		'Ended' => 'SS_Datetime'
	);
	
	/**
	 *
	 * @var array
	 */
	private static $has_one = array(
		'User' => 'Member'
	);
	
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
	
	/**
	 * To be called at the start of an import.
	 * 
	 * @return StaticSiteImportDataObject
	 */
	public function start() {
		$this->UserID = Member::currentUserID();
		$this->write();
		return $this;
	}
	
	/**
	 * To be called at the end of an import.
	 * 
	 * @return StaticSiteImportDataObject
	 */	
	public function end() {
		$this->Ended = SS_Datetime::now()->getValue();
		$this->write();
		return $this;
	}
	
	/**
	 * Make sure related FailedURLRewriteObject's are also removed
	 * 
	 * @todo Would belongs_to() do the job?
	 */
	public function onAfterDelete() {
		parent::onAfterDelete();
		$relatedFailedRewriteObjects = DataObject::get('FailedURLRewriteObject')->filter('ImportID', $this->ID);
		$relatedFailedRewriteSummaries = DataObject::get('FailedURLRewriteSummary')->filter('ImportID', $this->ID);
		$relatedFailedRewriteObjects->each(function($item) {
			$item->delete();
		});
		$relatedFailedRewriteSummaries->each(function($item) {
			$item->delete();
		});
	}
	
}
