<?php
/**
 * A CMS report for URLs that failed to be re-written.
 *
 * @author Russell Michell <russell@silverstripe.com>
 * @package staticsiteconnector
 * @see {@link BadImportLog}
 * @see {@link StaticSiteRewriteLinksTask}
 * @todo 
 *	- Ensure CSV export button works properly.
 *	- Render the summary using ViewableData::customise() so more detail can easily be added
 */
class FailedURLRewriteReport extends SS_Report {
	
	/**
	 *
	 * @var string
	 */
	protected $description = <<<'TXT'
This report shows a record for each page that contains one or more broken links, left over from the selected import.
<br/>You can manually delete a record as you go through and correct the links in each one.
TXT;

	/**
	 * 
	 * @return string
	 */
	public function title() {
		return "Imported links rewrite report";
	}

	/**
	 * 
	 * @return \ArrayList
	 */
	public function SourceRecords() {
		$getVars = Controller::curr()->request->getVars();
		$importID = !empty($getVars['filters']) ? $getVars['filters']['ImportID'] : 1;
		$list = $this->getBadImportData($importID);
		$_list = new ArrayList();
		$linkCount = array();
		foreach($list as $badLink) {		
			// Prevent same page showing in the report and "sum" the totals
			if(empty($linkCount[$badLink->ContainedInID])) {
				$linkCount[$badLink->ContainedInID] = 1;
			}
			else {
				$linkCount[$badLink->ContainedInID] += 1;
			}
		}
		
		foreach($list as $item) {
			// Only push new items if not already in the list
			if(!$_list->find('ContainedInID', $item->ContainedInID)) {
				$item->Total = $linkCount[$item->ContainedInID];
				$_list->push($item);
			}			
		}
		return $_list;
	}

	/**
	 * Get the columns to show with header titles
	 *
	 * @return array
	 */
	public function columns() {
		return array(
			'Title' => array(
				'title' => 'Imported page',
				'formatting' => function($value, $item) {
					return sprintf('<a href="/admin/pages/edit/show/%s">%s</a>',
						$item->ContainedInID,
						$item->Title()
					);
				}
			),
			'Total' => array(
				'title' => 'No. Bad Urls in page',
				'formatting' => '".$Total."'
			),
			'Created' => array(
				'title' => 'Task run date',
				'casting' => 'SS_Datetime->Nice24'
			),
			'Import.Created' => array(
				'title' => 'Import date',
				'casting' => 'SS_Datetime->Nice24'
			)					
		);
	}

	/**
	 * Get the raw data.
	 * 
	 * @param number $importID
	 * @return DataList
	 */
	protected function getBadImportData($importID) {
		$default = new ArrayList();		
		if($badLinks = DataObject::get('FailedURLRewriteObject')
				->filter('ImportID', $importID)
				->sort('Created')) {
			return $badLinks;
		}
		return $default;
	}
	
	/**
	 * Get link-rewrite summary for display at the top of the report. 
	 * The data itself comes from a DataList of FailedURLRewriteObject's.
	 * 
	 * @param number $importID
	 * @return null | string
	 */	
	protected function getSummary($importID) {
		if(!$text = DataObject::get_one('FailedURLRewriteSummary', "\"ImportID\" = '$importID'")) {
			return;
		}
		
		$lines = explode(PHP_EOL, $text->Text);		
		$summaryData = '';
		foreach($lines as $line) {
			$summaryData .= $line . '<br/>';
		}
		return $summaryData;
	}
	
	/**
	 * Show a basic form that allows users to filter link-rewrite data according to
	 * a specific import propogate via query-string.
	 * 
	 * @return FieldList
	 */
	public function parameterFields() {
		$fields = new FieldList();
		$getVars = Controller::curr()->request->getVars();
		$importID = !empty($getVars['filters']) ? $getVars['filters']['ImportID'] : 1;
		
		if($summary = $this->getSummary($importID)) {
			$fields->push(new HeaderField('SummaryHead', 'Summary', 4));
			$fields->push(new LiteralField('SummaryBody', $summary));			
		}
		
		$source = DataObject::get('StaticSiteImportDataObject');
		$_source = array();
		foreach($source as $import) {
			$date = DBField::create_field('SS_Datetime', $import->Created)->Nice24();
			$_source[$import->ID] = $date . ' (Import #' . $import->ID . ')';
		}		
		$importDropdown = new DropdownField('ImportID', 'Import selection', $_source);
		
		$fields->push($importDropdown);
		return $fields;
	}
	
	/**
	 * Overrides SS_Report::getReportField() with the addition of GridField Actions.
	 * 
	 * @return GridField
	 */
	public function getReportField() {
		$gridField = parent::getReportField();
		$config = $gridField->getConfig();		
		$config->addComponent(new GridFieldDeleteAction());
		return $gridField;
	}	
}
