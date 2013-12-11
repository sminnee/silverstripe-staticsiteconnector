<?php
/**
 * Encapsulates the result of a transformation.
 * It subclasses TransformResult to allow dealing-to File objects also
 *
 * @package staticsiteconnector
 * @see {@link TransformResult}
 * @author Russell Michell <russell@silverstripe.com>
 */
class StaticSiteTransformResult extends TransformResult {
	
	/**
	 * @var File
	 */
	public $file;
	
	/**
	 * @var SiteTree
	 */
	public $page;
	
	/**
	 * @var array
	 */
	public $children;

	/**
	 * 
	 * @param SiteTree $object
	 * @param SS_List $children
	 * @return void
	 */
	public function __construct($object, $children) {
		parent::__construct($object, $children);
		
		$instanceOfSiteTree = ($object instanceof SiteTree);
		$instanceOfFile = ($object instanceof File);
		if(!$instanceOfSiteTree && !$instanceOfFile) {
			user_error("Incorrect type passed to class constructor.");
			exit;
		}

		if($instanceOfSiteTree) {
			$this->page = $object;
		}
		if($instanceOfFile) {
			$this->file = $object;
		}

		$this->children = $children;
	}
}
