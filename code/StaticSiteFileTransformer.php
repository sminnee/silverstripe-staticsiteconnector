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

	/*
	 * @var Object
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

	public function __construct() {
		$this->utils = singleton('StaticSiteUtils');
		$this->mimeProcessor = singleton('StaticSiteMimeProcessor');
	}

	/**
	 *
	 * @param type $item
	 * @param type $parentObject
	 * @param type $duplicateStrategy
	 * @return boolean|\StaticSiteTransformResult
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
		//TODO: finalise the requirment for this activity by completing the Link Rewrite Task
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

		$source = $item->getSource();

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
			if(!$file = $this->buildFileProperties($file, $item->AbsoluteURL, $item->ProcessedMIME)) {
				return false;
			}
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
	 * Build the properties required for a safely saved SS asset
	 *
	 * @param $file
	 * @param string $mime
	 * @return mixed (boolean | \File)
	 * @todo Currently, if we can't "fix" borked file-extensions, they're discarded. Need to append our new file-extension ($newExt).
	 */
	public function buildFileProperties($file, $url, $mime) {
		// Build the container directory to hold imported files
		$isImage = $this->mimeProcessor->IsOfImage($mime);
		$path = 'Import' . DIRECTORY_SEPARATOR . ($isImage?'Images':'Documents');
		$parentFolder = Folder::find_or_make($path);

		// Run some checks on the original filename and name it as per a default if we can do nothing useful with it
		$origFilename = explode('/',$url);
		$origFilename = end($origFilename);
		$origFilenameIsOk = (!is_bool($origFilename) && mb_strlen($origFilename) >0);
		$origFilename = ($origFilenameIsOk ? $origFilename : 'unknown');

		// Some assets come through with no extension, which confuses SS's File logic and throws errors causing the import to stop.
		// Check for these and add an appropriate extension if appropriate
		$oldExt = File::get_file_extension($origFilename);
		$extIsValid = in_array($oldExt, $this->getSSExtensions());
		if(!$extIsValid && !$newExt = $this->mimeProcessor->ext_to_mime_compare($oldExt,$mime,true)) {
			$this->utils->log("WARNING: Unable to import file with bad file-extension: ", $url, $mime);
			return false;
		}

		// Complete construction of $file
		$file->setFilename($path . DIRECTORY_SEPARATOR . $origFilename);
		$file->setName($origFilename);
		$file->setParentID($parentFolder->ID);
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
}