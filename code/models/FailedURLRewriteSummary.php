<?php
/**
 * A model object that represents a single failed link-rewrite summary. This data is displayed
 * at the top of the {@link FailedURLRewriteReport}.
 * 
 * @package staticsiteconnector
 * @author Russell Michell <russell@silverstripe.com>
 */
class FailedURLRewriteSummary extends DataObject {

	/**
	 *
	 * @var array
	 */
	public static $db = array(
		'Text' => 'Text',
		'ImportID' => 'Int'
	);

}
