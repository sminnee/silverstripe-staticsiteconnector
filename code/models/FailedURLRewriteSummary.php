<?php
/**
 * A model object that represents a single failed link-rewrite summary. This data is displayed
 * at the top of the {@link FailedURLRewriteReport}.
 * 
 * @author Russell Michell <russ@silverstripe.com>
 * @package staticsiteconnector
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
