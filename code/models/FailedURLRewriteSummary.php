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
	private static $db = array(
		'Text' => 'Text',
		'ImportID' => 'Int'
	);
	
	/**
	 * 
	 * Format summary text so all totals are emboldened.
	 * 
	 * @return string
	 */
	public function getText() {
		return preg_replace("#([\d]*)#", "<strong>$1</strong>", $this->getField('Text'));
	}

}
