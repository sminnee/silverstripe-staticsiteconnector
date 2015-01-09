<?php

/**
 * External content - run import as a build task, importing content into a new container.
 */
class ExternalContentImportContentTask extends BuildTask {

	function run($request) {
		$id = $request->getVar('ID');
		if((!is_numeric($id) && !preg_match('/^[0-9]+_[0-9]+$/', $id)) || !$id) {
			echo $this->usage();
			return;
		}

		if($param = $request->getVar('includeSelected') && !empty($param)) {
			$includeSelected = (bool)$param;
		} else {
			$includeSelected = false;	
		}

		if($param = $request->getVar('includeChildren') && !empty($param)) {
			$includeChildren = (bool)$param;
		} else {
			$includeChildren = true;	
		}

		if($param = $request->getVar('duplicates') && !empty($param)) {
			$duplicates = $param;
		} else {
			$duplicates = 'Duplicate';	
		}
		
		$selected = $id;

		if($param = $request->getVar('ParentID') && !empty($param)) {
			$target = Page::get()->byId($param);
		} else {
			$target = new Page;
			$target->Title = "Import on " . date('Y-m-d H:i:s');
			$target->write();
		}

		$from = ExternalContent::getDataObjectFor($selected);
		if ($from instanceof ExternalContentSource) {
			$selected = false;
		}

		$importer = null;
		$importer = $from->getContentImporter('SiteTree');

		if ($importer) {
			$importer->import($from, $target, $includeSelected, $includeChildren, $duplicates);
		}
	}

	protected function usage() {
		$usage = <<<TXT
Usage: sake dev/tasks/ExternalContentImportContentTask ID=<id>

Parameters:
- ID (required): Database identifier of the StaticSiteContentSource 
- includeSelected (optional): Include selected item in import (default: false)
- includeChildren (optional): Include child items in import (default: true)
- duplicates (optional): How duplicates should be handled: "Duplicate", "Overwrite" or "Skip" (default: "Duplicate")
- ParentID (optional): Database identifier of the page root node to import to. 
                       Defaults to creating a new page "Import on <date> <time>"

TXT;

		return $usage;
	}
}
