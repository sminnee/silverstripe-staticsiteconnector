<?php
/*
 * URL transformer specific to SilverStripe's `File` object for use within the import functionality.
 *
 * This both creates SilverStripe's database representation of the fetched-file and also creates a copy of the file itself
 * on the local filesystem.
 *
 * @see {@link StaticSitePageTransformer}
 */
class StaticSiteFileTransformer implements ExternalContentTransformer {

	/**
	 *
	 * @param type $item
	 * @param type $parentObject
	 * @param type $duplicateStrategy
	 * @return boolean|\StaticSiteTransformResult
	 * @throws Exception
	 */
	public function transform($item, $parentObject, $duplicateStrategy) {

		$item->runChecks('file');
		if($item->checkStatus['ok'] !== true) {
			$this->log($item->checkStatus['msg']." for: ",$item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		// Sleep for 10ms to reduce load on the remote server
		usleep(10*1000);

		// Extract remote location of File
		// Also sets $this->tmpName for use in this->writeToFs()
		$contentFields = $this->getContentFieldsAndSelectors($item);

		// Default value for Title
		if(empty($contentFields['Filename'])) {
			$contentFields['Filename'] = array('content' => $item->externalId);
		}

		$source = $item->getSource();

		$schema = $source->getSchemaForURL($item->AbsoluteURL, $item->ProcessedMIME);
		if(!$schema) {
			$this->log("Couldn't find an import schema for: ",$item->AbsoluteURL,$item->ProcessedMIME);
			return false;
		}

		// @todo need to create the filter on schema on a mime-by-mime basis
		$dataType = $schema->DataType;

		if(!$dataType) {
			$this->log("DataType for migration schema is empty for: ",$item->AbsoluteURL,$item->ProcessedMIME);
			throw new Exception('DataType for migration schema is empty!');
		}

		$file = File::get()->filter('StaticSiteURL', $item->AbsoluteURL)->first();
		if($file && $duplicateStrategy === 'Overwrite') {
			if(get_class($file) !== $dataType) {
				$file->ClassName = $dataType;
			}
		} elseif($file && $duplicateStrategy === 'Skip') {
			return false;
		} else {
			// @todo
			// - Do we really want to rely on user-input to ascertain the correct container class?
			// - Should it be detected based on Mime-Type(s) first and if none found, _then_ default to user-input?
			$file = new $dataType(array());
			$path = parse_url($item->AbsoluteURL, PHP_URL_PATH);
			$file->Filename = dirname($path);
			$file->Name = basename($path);
			$parentFolder = Folder::find_or_make(dirname($path));
			$file->ParentID = $parentFolder->ID;
		}

		$this->write($file, $item, $source, $contentFields['tmp_path']);

		return new StaticSiteTransformResult($file, $item->stageChildren());
	}

	/**
	 *
	 * @param File $file
	 * @param StaticSiteContentItem $item
	 * @param StaticSiteContentSource $source
	 * @param string $tmpPath
	 * @return boolean
	 */
	public function write(File $file, $item, $source, $tmpPath) {
		$file->StaticSiteContentSourceID = $source->ID;
		$file->StaticSiteURL = $item->AbsoluteURL;
		// "Faux" This is identical to AbsoluteURL except the value is normalised, used for filtering on to prevent duplicates. See $this#runChecks()
		$file->StaticSiteURLFaux = $item->AbsoluteURLFaux;

		if(!$file->write()) {
			$uploadedFileMsg = "!! {$item->AbsoluteURL} not imported";
			$this->log($uploadedFileMsg , $file->Filename, $item->ProcessedMIME);
			return false;
		}

		rename($tmpPath, BASE_PATH . DIRECTORY_SEPARATOR . $file->Filename);
	}

	/**
	 * Get content from the remote file if $item->AbsoluteURL represents a File-ish object
	 *
	 * @param  StaticSiteContentItem $item The item to extract
	 * @return array A map of field name => array('selector' => selector, 'content' => field content) etc
	 */
	public function getContentFieldsAndSelectors($item) {
		// Get the import rules from the content source
		$importSchema = $item->getSource()->getSchemaForURL($item->AbsoluteURL,$item->ProcessedMIME);
		if(!$importSchema) {
			$this->log("Couldn't find an import schema for ",$item->AbsoluteURL,$item->ProcessedMIME,'WARNING');
			return null;
		}
		$importRules = $importSchema->getImportRules();

 		// Extract from the remote file based on those rules
		$contentExtractor = new StaticSiteContentExtractor($item->AbsoluteURL,$item->ProcessedMIME);
		$extraction = $contentExtractor->extractMapAndSelectors($importRules,$item);
		$extraction['tmp_path'] = $contentExtractor->getTmpFileName();
		return $extraction;
	}

	/**
	 * Log a message if the logging has been setup according to docs
	 *
	 * @param string $message
	 * @return void
	 */
	protected function log($message, $filename, $mime) {
		$logFile = Config::inst()->get('StaticSiteContentExtractor', 'log_file');
		if(!$logFile) {
			return;
		}

		if(is_writable($logFile) || !file_exists($logFile) && is_writable(dirname($logFile))) {
			error_log($message.' '.$filename.' '.'('.$mime.')'. PHP_EOL, 3, $logFile);
		}
	}

	/*
	 * Resets the value of `SiteTree.StaticSiteURL` to NULL before import, to ensure it's unique to the current import.
	 * If this isn't done, it isn't clear to the RewriteLinks BuildTask, which tree of imported content to link-to, when multiple imports have been made.
	 *
	 * @param string $url
	 * @param number $sourceID
	 */
	public function resetStaticSiteURL($url,$sourceID) {
		$url = trim($url);
		$resetPages = SiteTree::get()->filter(array(
			'StaticSiteURL'=>$url,
			'StaticSiteContentSourceID' => $sourceID
		));
		foreach($resetPages as $page) {
			$page->StaticSiteURL = NULL;
			$page->write();
		}
	}
}