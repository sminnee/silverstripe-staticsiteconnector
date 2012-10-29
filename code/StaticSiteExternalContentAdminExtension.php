<?php

class StaticSiteExternalContentAdminExtension extends Extension {
	static $allowed_actions = array(
		"crawlsite",
	);

	public function crawlsite($request) {
		$selected = isset($request['ID']) ? $request['ID'] : 0;
		if(!$selected){
			$messageType = 'bad';
			$message = _t('ExternalContent.NOITEMSELECTED', 'No item selected to crawl.');
		
		} else {
			$source = ExternalContent::getDataObjectFor($selected);
			if (!($source instanceof ExternalContentSource)) $source = $from->getSource();

			$messageType = 'good';
			$message = _t('ExternalContent.CONTENTMIGRATED', 'Crawling successful.');

			try {
				$source->crawl();
			} catch(Exception $e) {
				$messageType = 'bad';
				$message = "Error crawling: " . $e->getMessage();
			}

		}

		Session::set("FormInfo.Form_EditForm.formError.message", $message);
		Session::set("FormInfo.Form_EditForm.formError.type", $messageType);

		return $this->owner->getResponseNegotiator()->respond($this->owner->getRequest());	
	}
}