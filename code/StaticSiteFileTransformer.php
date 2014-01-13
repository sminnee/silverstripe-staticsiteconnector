<?php
/**
 * URL transformer specific to SilverStripe's `File` object for use within the import functionality.
 *
 * This both creates SilverStripe's database representation of the fetched-file and also creates a copy of the file itself
 * on the local filesystem.
 *
 * @package staticsiteconnector
 * @see {@link StaticSitePageTransformer}
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSiteFileTransformer implements ExternalContentTransformer {

	/**
	 * @var \StaticSiteUtils
	 *
	 * Holds the StaticSiteUtils object on construct
	 */
	protected $utils;

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
	 * @var Object
	 *
	 * $mimeTypeProcessor
	 */
	public $mimeProcessor;

	/**
	 * @return void
	 */
	public function __construct() {
		$this->utils = singleton('StaticSiteUtils');
		$this->mimeProcessor = singleton('StaticSiteMimeProcessor');
	}

	/**
	 * Generic function called by \ExternalContentImporter
	 * 
	 * @param type $item
	 * @param type $parentObject
	 * @param type $duplicateStrategy
	 * @return boolean | \StaticSiteTransformResult
	 * @throws Exception
	 */
	public function transform($item, $parentObject, $duplicateStrategy) {

		$this->utils->log("START transform for: ",$item->AbsoluteURL, $item->ProcessedMIME);

		$item->runChecks('file');
		if($item->checkStatus['ok'] !== true) {
			$this->utils->log($item->checkStatus['msg']." for: ",$item->AbsoluteURL, $item->ProcessedMIME);
			return false;
		}

		$source = $item->getSource();

		// Cleanup StaticSiteURLs
		$cleanupStaticSiteUrls = false;
		if ($cleanupStaticSiteUrls) {
			$this->utils->resetStaticSiteURLs($item->AbsoluteURL, $source->ID, 'File');
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

		$schema = $source->getSchemaForURL($item->AbsoluteURL, $item->ProcessedMIME);
		if(!$schema) {
			$this->utils->log("Couldn't find an import schema for: ",$item->AbsoluteURL,$item->ProcessedMIME);
			return false;
		}

		// @todo need to create the filter on schema on a mime-by-mime basis
		$dataType = $schema->DataType;

		if(!$dataType) {
			$this->utils->log("DataType for migration schema is empty for: ",$item->AbsoluteURL,$item->ProcessedMIME);
			throw new Exception('DataType for migration schema is empty!');
		}

		// Check if the file is already imported and decide what to do depending on the CMS-selected strategy (overwrite/skip etc)
		$existingFile = File::get()->filter('StaticSiteURL', $item->AbsoluteURL)->first();
		
		/* 
		 * It's difficult to properly mock situations where there's a pre-existing file in tests becuase SapphireTest invokes
		 * tearDown() on a per method basis, so we fake it for now
		 */
		if(SapphireTest::is_running_test()) {
			$existingFile = new $dataType(array());
		}
		
		// Overwrite
		if($existingFile && $duplicateStrategy === ExternalContentTransformer::DS_OVERWRITE) {
			$file = $this->cloneFile($dataType, $existingFile, ExternalContentTransformer::DS_OVERWRITE);
		}
		// Duplicate (Copy)
		else if($existingFile && $duplicateStrategy === ExternalContentTransformer::DS_DUPLICATE) {
			$file = $this->cloneFile($dataType, $existingFile, ExternalContentTransformer::DS_DUPLICATE);
		}	
		// Skip
		else if($existingFile && $duplicateStrategy === ExternalContentTransformer::DS_SKIP) {
			return false;
		}		
		// New
		else {
			$file = new $dataType(array());
		}
		
		if(!$file = $this->buildFileProperties($file, $item->AbsoluteURL, $item->ProcessedMIME)) {
			return false;
		}

		$this->write($file, $item, $source, $contentFields['tmp_path']);
		$this->utils->log("END transform for: ",$item->AbsoluteURL, $item->ProcessedMIME);

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

		if(!$file->write()) {
			$uploadedFileMsg = "!! {$item->AbsoluteURL} not imported";
			$this->utils->log($uploadedFileMsg , $file->Filename, $item->ProcessedMIME);
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

	/*
	 * Build the properties required for a safely saved SS asset.
	 * - Attempts to detect and fixup bad file-extensions based on Mime-Type
	 *
	 * @param \File $file
	 * @param string $url
	 * @param string $mime This is used to fixup bad-file extensions or filenames with no extension but which _do_ have a Mime-Type
	 * @return mixed (boolean | \File)
	 */
	public function buildFileProperties($file, $url, $mime) {
		// Build the container directory to hold imported files
		$isImage = $this->mimeProcessor->IsOfImage($mime);
		$path = 'Import' . DIRECTORY_SEPARATOR . ($isImage?'Images':'Documents');
		$parentFolder = Folder::find_or_make($path);
		if(!file_exists(ASSETS_PATH . DIRECTORY_SEPARATOR . $path)) {
			$this->utils->log("WARNING: File-import directory wasn't created.", $url, $mime);
			return false;
		}

		// Run some checks on the original filename and name it as per a default if we can do nothing useful with it
		// '.zzz' not in framework/_config/mimetypes.yml and unlikely ever to be found in \File so will fail gracefully
		$dummy = 'unknown.zzz';
		$origFilename = pathinfo($url,PATHINFO_FILENAME);
		$origFilename = (mb_strlen($origFilename)>0 ? $origFilename : $dummy);

		/*
		 * Some assets come through with no file-extension, which confuses SS's File logic and throws errors causing the import to stop dead.
		 * Check for these and add (guess) an appropriate file-extension if possible
		 */
		$oldExt = pathinfo($url,PATHINFO_EXTENSION);
		$extIsValid = in_array($oldExt, $this->getSSExtensions());

		// Only attempt to define and append a new filename ($newExt) if the $oldExt is itself invalid
		$newExt = null;
		if(!$extIsValid && !$newExt = $this->mimeProcessor->ext_to_mime_compare($oldExt,$mime,true)) {
			$this->utils->log("WARNING: Bad file-extension: \"{$oldExt}\". Unable to assign new file-extension (#1) - DISCARDING.", $url, $mime);
			return false;
		}
		else if($newExt) {
			$useExtension = $newExt;
			$logMessagePt1 = "NOTICE: Bad file-extension: \"{$oldExt}\". Assigned new file-extension: \"{$newExt}\" based on MimeType.";
			$logMessagePt2 = PHP_EOL."\t - FROM: \"{$url}\"".PHP_EOL."\t - TO: \"{$origFilename}.{$newExt}\"";
			$this->utils->log($logMessagePt1.$logMessagePt2, '', $mime);
		}
		else {
			// If $newExt didn't work, we need to check again if $oldExt is invalid and just dispose of it.
			if(!$extIsValid) {
				$this->utils->log("WARNING: Bad file-extension: \"{$oldExt}\". Unable to assign new file-extension (#2) - DISCARDING.", $url, $mime);
				return false;
			}
			if($this->mimeProcessor->isBadMimeType($mime)) {
				$this->utils->log("WARNING: Bad mime-type: \"{$mime}\". Unable to assign new file-extension (#3) - DISCARDING.", $url, $mime);
				return false;
			}
			$useExtension = $oldExt;
		}

		$fileName = $path . DIRECTORY_SEPARATOR . $origFilename;
		// Some files fail to save becuase of multiple dots in the filename. \FileNameFilter only removes leading dots, so pre-convert these:
		// @todo add another filter expression as per \FileNameFilter to module _config instead of using str_replace() here.
		$definitiveName = str_replace(".","-",$origFilename).'.'.$useExtension;
		$definitiveFilename = str_replace(".","-",$fileName).'.'.$useExtension;

		// Complete construction of $file.
		$file->setName($definitiveName);
		$file->setFilename($definitiveFilename);
		$file->setParentID($parentFolder->ID);
		
		$this->utils->log("NOTICE: \"File-properties built successfully for: ", $url, $mime);
		
		return $file;
	}

	/*
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
	
	/*
	 * @param string $dataType
	 * @param \File $existingFile
	 * @param string $method
	 * @return \File
	 */
	protected function cloneFile($dataType, $existingFile, $method) {
		if(get_class($existingFile) !== $dataType) {
			$existingFile->ClassName = $dataType;
			$existingFile->write();
		}
		if($existingFile) {
			$file = $existingFile;
		}
		$copy = $file;
		if($method == 'Overwrite') {
			$file->deleteDatabaseOnly();
		}
		$copy->write();
		$file = $copy;
		return $file;
	}
}
