<?php
/**
 * @package staticsiteconnector
 * @author Sam Minee <sam@silverstripe.com>
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSiteExternalContentAdminExtension extends Extension {
	
	/**
	 *
	 * @var array
	 */
	static $allowed_actions = array(
		"crawlsite",
		"deleteimports"
	);
	
	/**
	 * 
	 * @return void
	 */
	public function init() {
		Requirements::javascript('staticsiteconnector/js/StaticSiteConnectorAdmin.js');
	}

	/**
	 * 
	 * @param type $request
	 * @throws Exception
	 * @return SS_HTTPResponse
	 */
	public function crawlsite($request) {
		$selected = isset($request['ID']) ? $request['ID'] : 0;
		if(!$selected){
			$messageType = 'bad';
			$message = _t('ExternalContent.NOITEMSELECTED', 'No item selected to crawl.');
		} 
		else {
			$source = ExternalContent::getDataObjectFor($selected);
			if(!($source instanceof ExternalContentSource)) {
				throw new Exception('ExternalContent is not instance of ExternalContentSource.');
			}

			$messageType = 'good';
			$message = _t('ExternalContent.CONTENTMIGRATED', 'Crawling successful.');

			try {
				$source->crawl();
			} 
			catch(Exception $e) {
				$messageType = 'bad';
				$message = "Error crawling: " . $e->getMessage();
			}

		}

		Session::set("FormInfo.Form_EditForm.formError.message", $message);
		Session::set("FormInfo.Form_EditForm.formError.type", $messageType);

		return $this->owner->getResponseNegotiator()->respond($this->owner->getRequest());	
	}
	
	/**
	 * 
	 * Delete all StaticSiteImportDataObject's via AJAX.
	 * 
	 * @param SS_HTTPRequest $request
	 */
	public function deleteimports($request) {
		$imports = DataObject::get('StaticSiteImportDataObject');
		$imports->each(function($item) {
			$item->delete();
		});
		
		$messageType = 'good';
		$message = _t('StaticSiteConnector.ImportsDeleted', 'All import-data deleted successfully.');
		
		Session::set("FormInfo.Form_EditForm.formError.message", $message);
		Session::set("FormInfo.Form_EditForm.formError.type", $messageType);

		return $this->owner->getResponseNegotiator()->respond($this->owner->getRequest());	
	}
}
