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

	public $importDirName = '';
	public $tmpFileName = '';

	public function transform($item, $parentObject, $duplicateStrategy) {
		// Workaround for external-content module:
		// - ExternalContentAdmin#migrate()  assumes we're _either_ dealing-to a SiteTree object _or_ a File object
		// - todo Bug report?
		if($item->getType() != 'file') {
			$this->importLogger("item not of type 'file' for: ",$item->AbsoluteURL,$item->ProcessedMIME,'WARNING');
			return false;
		}

		// Sleep for 100ms to reduce load on the remote server
		usleep(100*1000);

		// Extract remote location of File
		// Also sets $this->tmpName for use in this->writeToFs()
		$contentFields = $this->getContentFieldsAndSelectors($item);

		// Default value for Title
		if(empty($contentFields['Filename'])) {
			$contentFields['Filename'] = array('content' => $item->externalId);
		}

		$source = $item->getSource();
		$schema = $source->getSchemaForURL($item->AbsoluteURL,$item->ProcessedMIME);
		if(!$schema) {
			$this->importLogger("Couldn't find an import schema for: ",$item->AbsoluteURL,$item->ProcessedMIME,'WARNING');
			return false;
		}

		// @todo need to create the filter on schema on a mime-by-mime basis
		$dataType = $schema->DataType;

		if(!$dataType) {
			$this->importLogger("DataType for migration schema is empty for: ",$item->AbsoluteURL,$item->ProcessedMIME,'WARNING');
			throw new Exception('DataType for migration schema is empty!');
		}

		// Create a File object with the appropriate fields
		// @todo
		// - Do we really want to rely on user-input to ascertain the correct container class?
		// - Should it be detected based on Mime-Type(s) first and if none found, _then_ default to user-input?
		$file = new $dataType(array());
		$existingFile = File::find($item->getExternalId);
		$existingFileWritten = false;

		if($existingFile && $duplicateStrategy === 'Overwrite') {
			if(get_class($existingFile) !== $dataType) {
				$existingFile->ClassName = $dataType;
				if($this->writeToFs($existingFile, $item->ProcessedMIME)) {
					$existingFileWritten = true;
				}
			}
			if($existingFile && $existingFileWritten) {
				$file = $existingFile;
			}
		}

		// @todo Redelegate this dir-creation task to StaticSiteUrlList where the parent cachedir is created??
		$importCacheDir = $source->staticSiteCacheDir.'/asset-import/';
		$isDocument = singleton('MimeTypeProcessor')->isOfFile($item->ProcessedMIME);
		$isImage = singleton('MimeTypeProcessor')->isOfImage($item->ProcessedMIME);
		if($isDocument) {
			$importCacheDir .= 'Documents/';
		}
		if($isImage) {
			$importCacheDir .= 'Images/';
		}
		if(!file_exists(ASSETS_PATH."/{$importCacheDir}")) {
			mkdir(ASSETS_PATH."/{$importCacheDir}");
		}
		$this->importDirName = $importCacheDir; // relative to assets/
		$file->StaticSiteContentSourceID = $source->ID;
		$file->StaticSiteURL = $item->AbsoluteURL;
		$file->ParentID = $parentObject ? $parentObject->ID : 0;

		foreach($contentFields as $k => $v) {
			$file->$k = $v['content'];
		}

		$this->writeToFs($file,$item->ProcessedMIME);

		return new StaticSiteTransformResult($file, $item->stageChildren());
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
			$this->importLogger("Couldn't find an import schema for:",$item->AbsoluteURL,$item->ProcessedMIME,'WARNING');
			return null;
			throw new LogicException("Couldn't find an import schema for URL: {$item->AbsoluteURL} and Mime: {$item->ProcessedMIME}");
		}
		$importRules = $importSchema->getImportRules();

 		// Extract from the remote file based on those rules
		$contentExtractor = new StaticSiteContentExtractor($item->AbsoluteURL,$item->ProcessedMIME);
		$extraction = $contentExtractor->extractMapAndSelectors($importRules,$item);
		$this->tmpFileName = $contentExtractor->getTmpFileName();
		return $extraction;
	}

	/*
	 * Writes a copy of the fetched file represented in SS by the $file param to the F/S and to the DB via the Upload class.
	 *
	 * Notes:
	 * - This is a rather naive implementation in that it just dumps all the files into the one folder.
	 * - Upload#load() invokes $file->write() so no need to manually initiate that anywhere
	 *
	 * @param File $file
	 * @return boolean
	 */
	protected function writeToFs(File $file, $mimeType) {
		$uploader = new FileLoader; // From external-content: overrides Upload#validate() to always return true
		// N.b we have also added File#validate() to return true in its DataExtension
		$fileName = str_replace('/','',$file->getFilename());
		$fldrPath = $this->importDirName;

		// Our data doesn't come from `Form`, so no data in $_FILES as Upload#load() expects so we fake it..
		$tmpFile = array(
			'size' => filesize($this->tmpFileName),
			'name' => $fileName,
			'tmp_name' => $this->tmpFileName
		);

		if($uploader->loadIntoFile($tmpFile, $file, $fldrPath)) {
			// Clean-up if Upload() hasn't already done so..
			$tmp_name = getTempFolder().'/'.$tmpFile['tmp_name'];
			if(file_exists($tmp_name)) {
				unlink($tmp_name);
			}
			$filesize = round((int)$tmpFile['size'] / 1024,2);
			$uploadedFileMsg = "{$tmpFile['tmp_name']} uploaded to SS and copied to F/S as {$tmpFile['name']} ({$filesize}kb)";
			$this->importLogger($uploadedFileMsg,$tmpFile['tmp_name'],$mimeType,'NOTICE',true);
			return true;
		}
		else {
			$uploadedFileMsg = "{$tmpFile['tmp_name']} NOT uploaded to SS. Original filename: {$tmpFile['name']}";
			$this->importLogger($uploadedFileMsg,$tmpFile['tmp_name'],$mimeType,'WARNING',true);
		}
		return false;
	}

	/*
	 * Basic import-specific logger for debugging
	 */
	protected function importLogger($message,$filename,$mime,$level,$extraLine = false) {
		if(Director::isDev()) {
			$level = strtoupper($level);
			$lineEnd = ($extraLine?PHP_EOL.PHP_EOL.'----------'.PHP_EOL.PHP_EOL:PHP_EOL);
			file_put_contents('/var/tmp/upload.log',date('d/m/Y H:i:s')." [{$level}] {$message} :: {$filename} ({$mime})".$lineEnd,FILE_APPEND);
		}
	}
}