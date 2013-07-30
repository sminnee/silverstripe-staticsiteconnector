<?php
/**
 * Class to encapsulate the result of a transformation.
 * Subclasses TransformResult to allow dealing-to File objects also
 *
 * @see {@link TransformResult}
 * @author Russell Michell 2013 russell@silverstripe.com
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