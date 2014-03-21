<?php
/**
 * URL transformer specific to SilverStripe's `File` object for use within import functionality.
 *
 * This creates SilverStripe's database representation of the fetched-file and a 
 * copy of the file itself on the local filesystem.
 *
 * @package staticsiteconnector
 * @see {@link StaticSitePageTransformer}
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSiteFileTransformer implements ExternalContentTransformer {

	/**
	 * Holds the StaticSiteUtils object on construct
	 * 
	 * @var \StaticSiteUtils
	 */
	protected $utils;
	
	/**
	 * 
	 * @var number
	 */
	public $importID = 0;	
	
	/**
	 *
	 * @var number
	 */
	protected static $parent_id = 0;

	/**
	 * Set this by using the yml config system
	 *
	 * Example:
	 * <code>
	 * StaticSiteContentExtractor:
     *    log_file:  ../logs/import-log.txt
	 * </code>
	 *
	 * @var string
	 */
	private static $log_file = null;
	
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
	 * @var StaticSiteMimeProcessor
	 *
	 * $mimeTypeProcessor
	 */
	public $mimeProcessor;

	/**
	 * 
	 * @return void
	 */
	public function __construct() {
		$this->utils = singleton('StaticSiteUtils');
		$this->mimeProcessor = singleton('StaticSiteMimeProcessor');
		$this->importID = $this->getCurrentImportID();
	}

	/**
	 * Generic function called by \ExternalContentImporter
	 * 
	 * @param type $item
	 * @param type $parentObject
	 * @param string $strategy
	 * @return boolean | \StaticSiteTransformResult
	 * @throws Exception
	 */
	public function transform($item, $parentObject, $strategy) {

		$this->utils->log("START transform for: ",$item->AbsoluteURL, $item->ProcessedMIME);

		$item->runChecks('file');
		if($item->checkStatus['ok'] !== true) {
			$this->utils->log(' - '.$item->checkStatus['msg']." for: ",$item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$source = $item->getSource();

		// Cleanup StaticSiteURLs to prevent StaticSiteRewriteLinksTask getting confused
		//$this->utils->resetStaticSiteURLs($item->AbsoluteURL, $source->ID, 'File');

		// Sleep for 10ms to reduce load on the remote server
		usleep(10*1000);

		// Extract remote location of File
		// Also sets $this->tmpName for use in this->writeToFs()
		$contentFields = $this->getContentFieldsAndSelectors($item);

		// Default value for Title
		if(empty($contentFields['Filename'])) {
			$contentFields['Filename'] = array('content' => $item->externalId);
		}

		$schema = $source->getSchemaForURL($item->AbsoluteURL, $item->ProcessedMIME);
		if(!$schema) {
			$this->utils->log(" - Couldn't find an import schema for: ",$item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		// @todo need to create the filter on schema on a mime-by-mime basis
		$dataType = $schema->DataType;

		if(!$dataType) {
			$this->utils->log(" - DataType for migration schema is empty for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			throw new Exception('DataType for migration schema is empty!');
		}
		
		// Process incoming according to user-selected duplication strategy
		if(!$file = $this->processStrategy($dataType, $strategy, $item, $source->BaseUrl, $parentObject)) {
			$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}
		
		if(!$file = $this->buildFileProperties($file, $item->AbsoluteURL, $item->ProcessedMIME)) {
			$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$this->write($file, $item, $source, $contentFields['tmp_path']);
		$this->utils->log("END transform for: ", $item->AbsoluteURL, $item->ProcessedMIME);

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
		$file->StaticSiteImportID = $this->$importID;

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
	 * Get content from remote file if $item->AbsoluteURL represents a File-ish object
	 *
	 * @param StaticSiteContentItem $item The item to extract
	 * @return array Map of field name=>array('selector' => selector, 'content' => field content)
	 */
	public function getContentFieldsAndSelectors($item) {
		// Get the import rules from the content source
		$importSchema = $item->getSource()->getSchemaForURL($item->AbsoluteURL,$item->ProcessedMIME);
		if(!$importSchema) {
			$this->utils->log("Couldn't find an import schema for ",$item->AbsoluteURL,$item->ProcessedMIME,'WARNING');
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
		$isImage = $this->mimeProcessor->IsOfImage($mime);
		$path = 'Import' . DIRECTORY_SEPARATOR . ($isImage ? self::$file_import_dir_image : self::$file_import_dir_file);
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
	 * Build an array of file extensions. Utilised in buildFileProperties() to check 
	 * incoming file-extensions are valid against those found on {@link File}.
	 * 
	 * @return array $exts
	 */
	protected function getSSExtensions() {
		$extensions = singleton('File')->config()->app_categories;
		$exts = array();
		foreach($extensions as $category => $extArray) {
			foreach($extArray as $ext) {
				$exts[] = $ext;
			}
		}
		return $exts;
	}
	
	/**
	 * Process incoming content according to CMS user-inputted duplication strategy.
	 * 
	 * @param string $pageType
	 * @param string $strategy
	 * @param StaticSiteContentItem $item
	 * @param string $baseUrl
	 * @param SiteTree $parentObject
	 * @return boolean | $page SiteTree
	 * @todo Add tests
	 */
	protected function processStrategy($dataType, $strategy, $item, $baseUrl, $parentObject) {
		// Is the file already imported?
		$baseUrl = rtrim($baseUrl, '/');
		$existing = $dataType::get()->filter('StaticSiteURL', $baseUrl.$item->getExternalId())->first();
		if($existing) {		
			if($strategy === ExternalContentTransformer::DS_OVERWRITE) {
				// "Overwrite" == Update
				$file = $existing;
				$file->ParentID = $existing->ParentID;
			}
			else if($strategy === ExternalContentTransformer::DS_DUPLICATE) {
				$file = $existing->duplicate(false);
				$file->ParentID = ($parentObject ? $parentObject->ID : self::$parent_id);
			}
			else {
				// Deals-to "skip" and no selection
				return false;
			}
		}
		else {
			$file = new $dataType(array());
			$file->ParentID = ($parentObject ? $parentObject->ID : self::$parent_id);
		}
		return $file;
	}
	
	/**
	 * Get the ID of the current StaticSiteContentImporter which will start and write to 
	 * a StaticSiteImportData object on construct.
	 * 
	 * @return number
	 */
	protected function getCurrentImportID() {
		$importer = singleton('StaticSiteImporter');
		if($currentImport = $importer->getCurrent()) {
			return $currentImport->ID;
		}
		return 0;
	}
}
