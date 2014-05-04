<?php
/**
 * A CMS report for URLs that failed to be re-written.
 *
 * @author Russell Michell <russ@silverstripe.com>
 * @package staticsiteconnector
 * @see {@link FailedURLRewriteObject}
 * @see {@link FailedURLRewriteSummary}
 * @see {@link StaticSiteRewriteLinksTask}
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
	 * @todo refactor this and use another, generic method to deal with repeated (similar) conditionals.
	 */
	public function SourceRecords() {
		$reqVars = Controller::curr()->request->requestVars();
		$importID = !empty($reqVars['filters']) ? $reqVars['filters']['ImportID'] : 1;
		$list = $this->getBadImportData($importID);
		$_list = new ArrayList();
		$countNotImported = $countJunk = $countThirdParty = $countBadScheme = array();
		foreach($list as $badLink) {
			if($badLink->BadLinkType == 'NotImported') {
				// Prevent same page showing in the report and "sum" the totals
				if(empty($countNotImported[$badLink->ContainedInID])) {
					$countNotImported[$badLink->ContainedInID] = 1;
				}
				else {
					$countNotImported[$badLink->ContainedInID] += 1;
				}
				continue;
			}
			if($badLink->BadLinkType == 'ThirdParty') {
				// Prevent same page showing in the report and "sum" the totals
				if(empty($countThirdParty[$badLink->ContainedInID])) {
					$countThirdParty[$badLink->ContainedInID] = 1;
				}
				else {
					$countThirdParty[$badLink->ContainedInID] += 1;
				}
			}
			if($badLink->BadLinkType == 'BadScheme') {
				// Prevent same page showing in the report and "sum" the totals
				if(empty($countBadScheme[$badLink->ContainedInID])) {
					$countBadScheme[$badLink->ContainedInID] = 1;
				}
				else {
					$countBadScheme[$badLink->ContainedInID] += 1;
				}
				continue;
			}
			if($badLink->BadLinkType == 'Junk') {
				// Prevent same page showing in the report and "sum" the totals
				if(empty($countJunk[$badLink->ContainedInID])) {
					$countJunk[$badLink->ContainedInID] = 1;
				}
				else {
					$countJunk[$badLink->ContainedInID] += 1;
				}
				continue;
			}			
		}
		
		foreach($list as $item) {
			// Only push new items if not already in the list
			if(!$_list->find('ContainedInID', $item->ContainedInID)) {
				$item->ThirdPartyTotal = isset($countThirdParty[$item->ContainedInID]) ? $countThirdParty[$item->ContainedInID] : 0;
				$item->BadSchemeTotal = isset($countBadScheme[$item->ContainedInID]) ? $countBadScheme[$item->ContainedInID] : 0;
				$item->NotImportedTotal = isset($countNotImported[$item->ContainedInID]) ? $countNotImported[$item->ContainedInID] : 0;
				$item->JunkTotal = isset($countJunk[$item->ContainedInID]) ? $countJunk[$item->ContainedInID] : 0;
				$_list->push($item);
			}			
		}
		return $_list;
	}

	/**
	 * Get the columns to show with header titles.
	 *
	 * @return array
	 */
	public function columns() {
		return array(
			'Title' => array(
				'title' => 'Imported page',
				'formatting' => function($value, $item) {
					return sprintf('<a href="admin/pages/edit/show/%s">%s</a>',
						$item->ContainedInID,
						$item->Title()
					);
				}
			),
			'ThirdPartyTotal' => array(
				'title' => '# 3rd Party Urls',
				'formatting' => '".$ThirdPartyTotal."'
			),	
			'BadSchemeTotal' => array(
				'title' => '# Urls w/bad-scheme',
				'formatting' => '".$BadSchemeTotal."'
			),					
			'NotImportedTotal' => array(
				'title' => '# Unimported Urls',
				'formatting' => '".$NotImportedTotal."'
			),	
			'JunkTotal' => array(
				'title' => '# Junk Urls',
				'formatting' => '".$JunkTotal."'
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
	 * @return SS_List
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
		$reqVars = Controller::curr()->request->requestVars();
		$importID = !empty($reqVars['filters']) ? $reqVars['filters']['ImportID'] : 1;
		
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
		
		$importDropdown = new LiteralField('ImportID', '<p>No imports found.</p>');
		if($_source) {
			$importDropdown = new DropdownField('ImportID', 'Import selection', $_source);
		}
		
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
		$gridField->setModelClass('FailedURLRewriteObject');
		$config = $gridField->getConfig();		
		$config->addComponent(new GridFieldDeleteAction());
		return $gridField;
	}	
}
