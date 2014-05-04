<?php
/**
 * URL transformer specific to SilverStripe's `File` class for use with the module's
 * import content feature. It will re-create all available data of the scraped file into SilverStripe's
 * database and re-create a copy of the file itself on the filesystem.
 * 
 * If enabled in the CMS UI, links to imported images and documents in imported page-content will be automatically
 * re-written.
 * 
 * @todo write unit-test for unwritable assets dir.
 *
 * @package staticsiteconnector
 * @author Sam Minee <sam@silverstripe.com>
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 * @see {@link StaticSiteDataTypeTransformer}
 */
class StaticSiteFileTransformer extends StaticSiteDataTypeTransformer {
	
	/**
	 * The name to use for the main folder under assets where files & images will be cached.
	 */
	public static $default_parent_dir = 'Import';
	
	/**
	 * The name to use for the folder beneath assets/Import to cache imported images.
	 * @var static
	 */
	public static $file_import_dir_image = 'Images';
	
	/**
	 * The name to use for the folder beneath assets/Import to cache imported documents.
	 * @var static
	 */
	public static $file_import_dir_file = 'Documents';	

	/**
	 * Generic function called by \ExternalContentImporter
	 * 
	 * @inheritdoc
	 */
	public function transform($item, $parentObject, $strategy) {

		$this->utils->log("START file-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);

		$item->runChecks('file');
		if($item->checkStatus['ok'] !== true) {
			$this->utils->log(' - ' . $item->checkStatus['msg']. " for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END file-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$source = $item->getSource();

		// Sleep for 10ms to reduce load on the remote server
		usleep(10*1000);

		// Extract remote location of File
		// Also sets $this->tmpName for use in this->writeToFs()
		$contentFields = $this->getContentFieldsAndSelectors($item, 'File');

		// Default value for Title
		if(empty($contentFields['Filename'])) {
			$contentFields['Filename'] = array('content' => $item->externalId);
		}

		$schema = $source->getSchemaForURL($item->AbsoluteURL, $item->ProcessedMIME);
		if(!$schema) {
			$this->utils->log(" - Couldn't find an import schema for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END file-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		// @todo need to create the filter on schema on a mime-by-mime basis
		$dataType = $schema->DataType;

		if(!$dataType) {
			$this->utils->log(" - DataType for migration schema is empty for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END file-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			throw new Exception('DataType for migration schema is empty!');
		}
		
		// Process incoming according to user-selected duplication strategy
		if(!$file = $this->duplicationStrategy($dataType, $strategy, $item, $source->BaseUrl, $parentObject)) {
			$this->utils->log("END file-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}
		
		if(!$file = $this->buildFileProperties($file, $item->AbsoluteURL, $item->ProcessedMIME)) {
			$this->utils->log("END file-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		/*
		 * write() calls File::onAfterWrite() which calls File::updateFileSystem() which throws
		 * an exception if the same image is attempted to be written.
		 */
		try {
			$this->write($file, $item, $source, $contentFields['tmp_path']);
		}
		catch(Exception $e) {
			$this->utils->log($e->getMessage(), $item->AbsoluteURL, $item->ProcessedMIME);
		}
		$this->utils->log("END file-transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);

		return new StaticSiteTransformResult($file, $item->stageChildren());
	}

	/**
	 * Write the file to the DB and Filesystem, skipping \Upload.
	 * Will fix any stale tmp images lying around.
	 * 
	 * @param File $file
	 * @param StaticSiteContentItem $item
	 * @param StaticSiteContentSource $source
	 * @param string $tmpPath
	 * @return boolean | void
	 */
	public function write(File $file, $item, $source, $tmpPath) {
		$file->StaticSiteContentSourceID = $source->ID;
		$file->StaticSiteURL = $item->AbsoluteURL;
		$file->StaticSiteImportID = $this->getCurrentImportID();
		
		$assetPath = ASSETS_PATH . '/' . $this->getParentDir() . '/' . $this->getLastDir($item->ProcessedMIME);
		if(!is_writable($assetPath)) {
			$this->utils->log(" - Assets path isn't writable by the webserver. Permission denied.", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		if(!$file->write()) {
			$this->utils->log(" - Not imported: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$filePath = BASE_PATH . DIRECTORY_SEPARATOR . $file->Filename;
		
		// Move the file to new location in assets
		rename($tmpPath, $filePath);

		// Remove garbage tmp files if/when left lying around
		if(file_exists($tmpPath)) {
			unlink($tmpPath);
		}		
	}

	/**
	 * Build the properties required for a safely saved SilverStripe asset.
	 * Attempts to detect and fix bad file-extensions based on the available Mime-Type.
	 *
	 * @param \File $file
	 * @param string $url
	 * @param string $mime	Used to fixup bad-file extensions or filenames with no 
	 *						extension but which _do_ have a Mime-Type.
	 * @return mixed (boolean | \File)
	 */
	public function buildFileProperties($file, $url, $mime) {
		// Build the container directory to hold imported files
		$postVars = Controller::curr()->request->postVars();
		$parentDir = $this->getParentDir();
		$isImage = $this->mimeProcessor->IsOfImage($mime);
		$path = $parentDir . DIRECTORY_SEPARATOR . ($isImage ? self::$file_import_dir_image : self::$file_import_dir_file);
		$parentFolder = Folder::find_or_make($path);
		if(!file_exists(ASSETS_PATH . DIRECTORY_SEPARATOR . $path)) {
			$this->utils->log(" - WARNING: File-import directory wasn't created.", $url, $mime);
			return false;
		}

		/*
		 * Run checks on original filename and name it as per default if nothing can be done with it.
		 * '.zzz' not in framework/_config/mimetypes.yml and unlikely ever to be found in File, so fails gracefully.
		 */
		$dummy = 'unknown.zzz';
		$origFilename = pathinfo($url, PATHINFO_FILENAME);
		$origFilename = (mb_strlen($origFilename)>0 ? $origFilename : $dummy);

		/*
		 * Some assets come through with no file-extension, which confuses SS's File logic
		 * and throws errors causing the import to stop dead.
		 * Check for this and guess an appropriate file-extension, if possible.
		 */
		$oldExt = pathinfo($url, PATHINFO_EXTENSION);
		$extIsValid = in_array($oldExt, $this->getSSExtensions());

		// Only attempt to define and append a new filename ($newExt) if $oldExt is invalid
		$newExt = null;
		if(!$extIsValid && !$newExt = $this->mimeProcessor->ext_to_mime_compare($oldExt, $mime, true)) {
			$this->utils->log(" - WARNING: Bad file-extension: \"$oldExt\". Unable to assign new file-extension (#1) - DISCARDING.", $url, $mime);
			return false;
		}
		else if($newExt) {
			$useExtension = $newExt;
			$logMessagePt1 = "NOTICE: Bad file-extension: \"$oldExt\". Assigned new file-extension: \"$newExt\" based on MimeType.";
			$logMessagePt2 = PHP_EOL."\t - FROM: \"$url\"".PHP_EOL."\t - TO: \"$origFilename.$newExt\"";
			$this->utils->log(' - ' . $logMessagePt1 . $logMessagePt2, '', $mime);
		}
		else {
			// If $newExt didn't work, check again if $oldExt is invalid and just lose it.
			if(!$extIsValid) {
				$this->utils->log(" - WARNING: Bad file-extension: \"$oldExt\". Unable to assign new file-extension (#2) - DISCARDING.", $url, $mime);
				return false;
			}
			if($this->mimeProcessor->isBadMimeType($mime)) {
				$this->utils->log(" - WARNING: Bad mime-type: \"$mime\". Unable to assign new file-extension (#3) - DISCARDING.", $url, $mime);
				return false;
			}
			$useExtension = $oldExt;
		}

		$fileName = $path . DIRECTORY_SEPARATOR . $origFilename;
		
		/*
		 * Some files fail to save becuase of multiple dots in the filename. 
		 * FileNameFilter only removes leading dots, so pre-convert these.
		 * @todo add another filter expression as per \FileNameFilter to module _config instead of using str_replace() here.
		 */
		$definitiveName = str_replace(".", "-", $origFilename) . '.' . $useExtension;
		$definitiveFilename = str_replace(".", "-", $fileName). '.' . $useExtension;

		// Complete construction of $file.
		$file->setName($definitiveName);
		$file->setFilename($definitiveFilename);
		$file->setParentID($parentFolder->ID);
		
		$this->utils->log(" - NOTICE: \"File-properties built successfully for: ", $url, $mime);
		
		return $file;
	}
	
	/**
	 * Determine the correct parent dir under assets, where images and documents should be cached.
	 * 
	 * @return string $parentDir
	 */
	public function getParentDir() {
		$parentDir = self::$default_parent_dir;
		if(!empty($postVars['FileMigrationTarget'])) {
			$parentDirData = DataObject::get_by_id('File', $postVars['FileMigrationTarget']);
			$parentDir = $parentDirData->Title;
		}
		return $parentDir;
	}
	
	/**
	 * Get the name of the subdir directly beneath the parent, where the current file will 
	 * be cached.
	 * 
	 * @param string $mime
	 * @return string
	 */
	public function getLastDir($mime) {
		if($this->mimeProcessor->IsOfImage($mime)) {
			return self::$file_import_dir_image;
		}
		return self::$file_import_dir_file;
	}
}
