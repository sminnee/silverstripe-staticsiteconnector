<?php
/**
 * Rewrites content-links found in <img> element "src" HTML attributes and <a> element "href"
 * HTML attributes, originally imported via {@link StaticSiteImporter}.
 * 
 * All rewrite failures are written to a logfile (@see $log_file).
 * This log is used as the data source for the CMS report {@link BadImportsReport}.
 * This is because it's only after attempting to rewrite links that we're
 * able to analyse why some failed. Often we find the reason is that the URL being re-written 
 * hasn't actually made it 100% through the import process.
 * 
 * @author Sam Minnee <sam@silverstripe.com>
 * @author SilverStripe Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSiteRewriteLinksTask extends BuildTask {

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
	 * An inexhaustive list of non http(s) URI schemes which we don't want to try to normalise.
	 *
	 * @see http://en.wikipedia.org/wiki/URI_scheme
	 * @var array
	 */
	public static $non_http_uri_schemes = array(
		'mailto',
		'tel',
		'ftp',
		'res',
		'skype',
		'ssh'
	);

	/**
	 * Set to true to enable verbose output of the rewriting progress, false by default
	 * This var responds to the command line argument: VERBOSE=1
	 *
	 * @var bool $verbose
	 */
	public $verbose = false;
	
	/**
	 * @var string
	 */
	protected $description = 'Rewrites imported links into SilverStripe compatible format.';

	/**
	 *
	 * @var string
	 */
	public $curentPageTitle = null;
	
	/**
	 *
	 * @var number
	 */
	public $currentPageId = null;

	/**
	 * Stores the dodgy URLs for later analysis
	 *
	 * @var array
	 */
	public $listFailedRewrites = array();

	/**
	 * The ID number of the StaticSiteContentSource which has the links to be rewritten
	 *
	 * @var int
	 */
	protected $contentSourceID;
	
	/**
	 * The import identifier
	 *
	 * @var int
	 */
	protected $contentImportID;	

	/**
	 * The StaticSiteContentSource which has the links to be rewritten
	 *
	 * @var StaticSiteContentSource
	 */
	protected $contentSource = null;
	
	/**
	 * Holds the StaticSiteUtils object on construct
	 * 
	 * @var StaticSiteUtils
	 */
	protected $utils;
	
	/**
	 * 
	 * @var string
	 */
	protected $newLine = '';

	/**
	 * Starts the task
	 *
	 * @param SS_HTTPRequest $request The request parameter passed from the task initiator, browser or CLI
	 * @return null | void
	 */
	public function run($request) {
		
		$this->utils = singleton('StaticSiteUtils');
		$this->newLine = Director::is_cli() ? PHP_EOL : '<br/>';

		// Get the StaticSiteContentSource and Import ID from the request parameters
		$this->contentSourceID = trim($request->getVar('SourceID'));
		$this->contentImportID = trim($request->getVar('ImportID'));
		$hasSid = ($this->contentSourceID && is_numeric($this->contentSourceID));
		$hasIid = ($this->contentImportID && is_numeric($this->contentImportID));
		
		if(!$hasSid || !$hasIid) {
			$this->printTaskInfo();
			return;
		}

		// Load the content source using the passed content-source ID and Import ID
		$this->contentSource = (StaticSiteContentSource::get()->byID($this->contentSourceID));
		$contentImport = (StaticSiteImportDataObject::get()->byID($this->contentImportID));
		if(!$this->contentSource) {
			$this->printMessage("No content-source found via SourceID: ".$this->contentSourceID, 'WARNING');
			return;
		}
		if(!$contentImport) {
			$this->printMessage("No content-import found via ImportID: ".$this->contentImportID, 'WARNING');
			return;
		}	

		/*
		 * Load imported page + file objects and filter the results on the passed ImportID,
		 * so the task knows in which imported content links should be re-written.
		 */
		$pages = $this->contentSource->Pages()->filter('StaticSiteImportID', $this->contentImportID);
		$files = $this->contentSource->Files()->filter('StaticSiteImportID', $this->contentImportID);

		$this->printMessage("Processing Import: {$pages->count()} pages, {$files->count()} files",'NOTICE');

		// Set up rewriter
		$pageLookup = $pages->map('StaticSiteURL', 'ID');
		$fileLookup = $files->map('StaticSiteURL', 'ID');

		// Check for verbose argument
		$verbose = $request->getVar('VERBOSE');
		if($verbose && $verbose == 1) {
			$this->verbose = true;
		}
		
		if($show = $request->getVar('SHOW')) {
			if($show == 'pages') {
				$this->printMessage('Page Map');
				foreach($pageLookup as $url => $id) {
					$this->printMessage($id . ' => ' . $url);
				}
			}
			if($show == 'files') {
				$this->printMessage('File Map');
				foreach($fileLookup as $url => $id) {
					$this->printMessage($id . ' => ' . $url);
				}
			}
		}

		if($request->getVar('DIE')) {
			return;
		}

		$baseURL = $this->contentSource->BaseUrl;
		$task = $this;
		
		/*
		 * If no URL Processor is set in the external-content CMS UI, check for it
		 * or calls to singleton() will fail.
		 */
		$urlProcessor = null;
		if($this->contentSource->UrlProcessor) {
			$urlProcessor = singleton($this->contentSource->UrlProcessor);
		}

		/*
		 * Create a callback function for the url rewriter which is called from StaticSiteLinkRewriter, 
		 * passed through the variable: $callback($url)
		 */
		$rewriter = new StaticSiteLinkRewriter(function($url) use(
				$pageLookup, $fileLookup, $baseURL, $task, $urlProcessor) {

			$origUrl = $url;
			$anchor = '';
			if(strpos($url, '#') !== false) {
				list($url, $anchor) = explode('#', $url, 2);
			}

			/*
			 * Process $url just the same as we did for the value of SiteTree.StaticSiteURL during import.
			 * This ensures $url === SiteTree.StaticSiteURL so we can match very accurately on it.
			 * The "mime" key is an expected argument but it's not actually used within this task.
			 * It defaults to the page's mime type.
			 */
			if($urlProcessor) {
				$processedURL = $urlProcessor->processURL(array('url' => $url, 'mime'=> 'text/html'));
				// processURL returns an array. Get the url from it
				$url = $processedURL['url'];				
			}

			// Return now if the url is empty, or is not an http scheme or is already processed into a SS shortcode
			if($task->ignoreUrl($url)) {
				return;
			}

			// strip the trailing slash if any
			if(substr($url, -1) == '/')  {
				$url = rtrim($url, '/');
			}

			/*
			 * Strip the host and protocol from the url to ensure the url is relative before creating
			 * the pageMapKey as an absolute url, so it matches the keys in $pageLookup.
			 */
			$pageMapKey = Controller::join_links($baseURL, parse_url($url, PHP_URL_PATH));

			/*
			 * File urls dont need processing as they dont have 'Pages' or '.aspx' present
			 * so create the file map key by just making the raw input url absolute
			 */
			$fileMapKey = Controller::join_links($baseURL, $origUrl);

			// Log the progress
			if($task->verbose) {
				$task->printMessage("# rewriting: \"$origUrl\"");
				if($anchor != '') {
					$task->printMessage(" - anchor: \"$anchor\"");
				}
				$task->printMessage(" - page-key: \"$pageMapKey\"");
				$task->printMessage(" - file-key: \"$fileMapKey\"");
			}
			
			/*
			 * @todo process and rewrite anchors correctly:
			 * - If found on the same page, rewrite as #my-anchor
			 * - If found on another page, strip them. SS doesn't support cross page anchors.
			 */

			/*
			 * Rewrite SiteTree links by replacing the phpQuery processed Page-URL 
			 * with a SiteTree shortcode
			 */
			$pageLookup = $pageLookup->toArray();
			if(isset($pageLookup[$pageMapKey]) && $siteTreeID = $pageLookup[$pageMapKey]) {
				$output = '[sitetree_link,id=' . $siteTreeID . ']';
				if($task->verbose) {
					$task->printMessage("+ found: SiteTree ID#" . $siteTreeID, null, $output);
				}
				return $output;
			}

			/*
			 * Rewrite Asset links by replacing phpQuery processed asset-URLs with 
			 * the appropriate asset-filename.
			 */
			$fileLookup = $fileLookup->toArray();
			if(isset($fileLookup[$fileMapKey]) && $fileID = $fileLookup[$fileMapKey]) {
				if($file = DataObject::get_by_id('File', $fileID)) {
					$output = $file->RelativeLink();
					if($task->verbose) {
						$task->printMessage("+ found: File ID#" . $fileID, null, $output);
					}
					return $output;
				}
				else {
					$task->printMessage('File get_by_id failed with FileID: ' . $fileID . ', FileMapKey: ' . $fileMapKey, 'WARNING');
				}
			}

			// Got this far? Link-rewriting has failed.
			$task->printMessage('Rewriter failed ', 'WARNING', $origUrl);

			// log the failed rewrites
			$segment01 = "Couldn't rewrite: " . $origUrl;
			$segment02 = " Found in Page: " . $task->currentPageTitle;
			$segment03 = " (ID:" . $task->currentPageID . ")";
			array_push($task->listFailedRewrites, $segment01 . $segment02 . $segment03);

			return $origUrl;
		});

		// Perform rewriting
		$changedFields = 0;
		foreach($pages as $i => $page) {
			/*
			 * Set these so the rewriter task can log some page context 
			 * for the urls that couldn't be re-writen.
			 */
			$this->currentPageTitle = $page->Title;
			$this->currentPageID = $page->ID;

			$url = $page->StaticSiteURL;
			if($this->verbose) {
				$this->printMessage('------------------------------------------------');
				$this->printMessage($page->URLSegment, $i);
			}
			
			// Get the schema that matches the page's url
			if($schema = $this->contentSource->getSchemaForURL($url, 'text/html')) {
				// Get fields to process
				$fields = array();
				foreach($schema->ImportRules() as $rule) {
					if(!$rule->PlainText) {
						$fields[] = $rule->FieldName;
					}
				}
				$fields = array_unique($fields);
			}
			else {
				$this->printMessage("No schema found for {$page->URLSegment}",'WARNING');
				continue;
			}
			
			$modified = false;
			foreach($fields as $field) {
				$newContent = $rewriter->rewriteInContent($page->$field);
				// square-brackets are converted somewhere upstream..
				$newContent = str_replace(array('%5B', '%5D'), array('[', ']'), $newContent);
				
				// if rewrite succeeded, then the content returned differs from the input
				if($newContent != $page->$field) {
					$changedFields++;
					$this->printMessage("Changed field: '$field' on page: \"{$page->Title}\" (ID: {$page->ID})", 'NOTICE');
					$page->$field = $newContent;
					$modified = true;
				}
			}
			
			/*
			 * Only save the page if modifications have occurred.
			 * Default is to just write the page with its changes, but not publish.
			 * If the 'PUBLISH' flag is passed, then publish it. (Beats a CMS batch update for 100s of pages)
			 */
			if($modified) {
				if($request->getVar('PUBLISH')) {
					$page->doPublish();
				}
				else {
					$page->write();
				}
			}
		}
		
		$newLine = $this->newLine;
		$this->printMessage("{$newLine}Complete.");
		$this->printMessage("Amended $changedFields content fields for {$pages->count()} pages and {$files->count()} files processed.");
		
		$msgNextSteps = " - Not all links will get fixed. It's recommended to also run a 3rd party link-checker over your imported content.";
		$msgSeeReport = " - Check the CMS \"".singleton('BadImportsReport')->title()."\" for a summary of failed link-rewrites.";
		$msgSeeLogged = " - Check ".Config::inst()->get('StaticSiteRewriteLinksTask', 'log_file')." for more detail on failed link-rewrites.";
		
		$this->printMessage("Tips:");
		$this->printMessage("{$newLine}$msgNextSteps");
		$this->printMessage($msgSeeReport);
		$this->printMessage($msgSeeLogged);
		
		$this->writeFailedRewrites();
	}

	/**
	 * Prints notices and warnings and aggregates them into two lists for later analysis, 
	 * depending on $level and whether you're using the CLI or a browser to run the task.
	 *
	 * @param string $message The message to log
	 * @param string $level The log level, e.g. NOTICE or WARNING
	 * @param string $url The url which was being re-written
	 * @return void
	 */
	public function printMessage($message, $level = null, $url = null) {
		$url = ($url ? '(' . $url . ') ' : '');
		$level = ($level ? '[' . $level .'] ' : '');
		if(Director::is_cli()) {
			echo "$level$message$url" . PHP_EOL;
		}
		else {
			echo "<p>$level$message$url</p>" . PHP_EOL;
		}
/*
 * Commented logic allowed comprehensive and detailed information to be logged for quality debugging.
 * It is commented for now, as it is way too slow for imports comprising 1000s of URLs
 */
		
// @todo find a more intelligent way of matching the $page->field (See WARNING below)
// @todo Extrapolate the field-matching into a separate method
//		if($url && $level == 'WARNING') {
//			// Attempt some context for the log, so we can tell what page the rewrite failed in
//			$normalized = $this->normaliseUrl($url, $this->contentSource->BaseUrl);
//			$url = preg_replace("#/$#",'',str_ireplace($this->contentSource->BaseUrl, '', $normalized['url']));
//			$pages = $this->contentSource->Pages();
//			$dbFieldsToMatchOn = array();
//			foreach($pages as $page) {
//				foreach($page->db() as $name=>$field) {
//					// Check that the $name is available on this particular Page subclass
//					// WARNING: We're hard-coding a connection between fields partially named as '.*Content.*' on the selected DataType!
//					if(strstr($name, 'Content') && in_array($name, $page->database_fields($page->ClassName))) {
//						$dbFieldsToMatchOn["{$name}:PartialMatch"] = $url;
//					}
//				}
//			}
//			// Query SiteTree for the page in which the link to be rewritten, was found
//			$failureContext = 'unknown';
//			if($page = SiteTree::get()->filter($dbFieldsToMatchOn)->First()) {
//				$failureContext = '"'.$page->Title.'" (#'.$page->ID.')';
//			}
//			array_push($this->listFailedRewrites, "Couldn't rewrite: {$url}. Found in: {$failureContext}");
//		}
	}

	/**
	 * Write failed rewrites to a logfile for later analysis.
	 * Note: There is a CMS report generated from this data.
	 *
	 * @see {@link BadImportsReport}
	 * @return void
	 */
	public function writeFailedRewrites() {
		$logFail = implode(PHP_EOL, $this->listFailedRewrites);
		$header = 'Imported link failure log: (' . date('d/m/Y H:i:s') . ')' . PHP_EOL . PHP_EOL;
		
		foreach($this->countFailureTypes() as $label => $payload) {
			$desc = $payload['desc'] ? " ({$payload['desc']})" : '';
			$header .= FormField::name_to_label($label) . ': '. $payload['count'] . $desc . PHP_EOL;
		}
		
		$logData = $header . PHP_EOL . $logFail . PHP_EOL;
		$this->utils->log($logData, null, null, __CLASS__);
	}

	/**
	 * Returns an array of totals of all the failed URLs, in different categories according to:
	 * - No. Non $baseURL http(s) URLs
	 * - No. Non http(s) URI schemes (e.g. mailto, tel etc)
	 * - No. URLs not imported
	 * - No. Junk URLs (i.e. those not matching any of the above)
	 *
	 * @return array
	 */
	public function countFailureTypes() {
		$rawData = $this->listFailedRewrites;
		$nonHTTPSchemes = implode('|', self::$non_http_uri_schemes);
		$countNotBase = 0;
		$countNotSchm = 0;
		$countNoImprt = 0;
		$countJunkUrl = 0;
		foreach($rawData as $url) {
			$url = trim(str_replace("Couldn't rewrite: ", '', $url));
			if(stristr($url,'http')) {
				++$countNotBase;
			}
			else if(preg_match("#($nonHTTPSchemes):#", $url)) {
				++$countNotSchm;
			}
			else if(preg_match("#^/#", $url)) {
				++$countNoImprt;
			}
			else {
				++$countJunkUrl;
			}
		}
		return array(
			'Total failures'	=> array('count' => count($rawData), 'desc' => ''),
			'ThirdParty'		=> array('count' => $countNotBase, 'desc' => 'Links to external websites'),
			'BadScheme'			=> array('count' => $countNotSchm, 'desc' => 'Links with bad scheme'),
			'BadImport'			=> array('count' => $countNoImprt, 'desc' => 'Links to pages that were not imported'),
			'Junk'				=> array('count' => $countJunkUrl, 'desc' => 'Junk links')
		);
	}

	/**
	 * Whether or not to ingore a URL. Returns true if a URL is either:
	 * 
	 *	- An empty string
	 *	- A non-HTTP scheme like an email link see: $non_http_uri_schemes
	 *	- A CMS sitetree shortcode or file/image asset path, e.g. [sitetree, 123] or assets/Images/logo.gif
	 *	- An absolute url, i.e. anything that beings with 'http'
	 *
	 * @param string $url A URL
	 * @return boolean True is the url can be ignored
	 * @todo What if the remote site is a SilverStripe site? asset+sitetree URLs will be ignored!
	 */
	public function ignoreUrl($url) {
		$url = trim($url);		

		// Empty string
		if(!strlen($url) >0) {
			if($this->verbose) {
				$this->printMessage("+ ignoring: '' (Empty link)");
			}
			return true;
		}

		// Not an HTTP protocol
		$nonHTTPSchemes = implode('|',self::$non_http_uri_schemes);
		$nonHTTPSchemes = (preg_match("#($nonHTTPSchemes):#", $url));
		if($nonHTTPSchemes) {
			if($this->verbose) {
				$this->printMessage("+ ignoring: $url (Non-HTTP URL)");
			}
			return true;
		}		

		// Is external or an absolute url
		$externalUrl = (substr($url, 0, 4) == 'http');
		if($externalUrl) {
			if($this->verbose) {
				$this->printMessage("+ ignoring $url (3rd party URL)");
			}
			return true;
		}
		
		// Has already been processed
		$alreadyRewritten = (preg_match("#(\[sitetree|assets)#", $url));
		if($alreadyRewritten) {
			if($this->verbose) {
				$this->printMessage("+ ignoring: $url (CMS link, already converted)");
			}
			return true;
		}		
		return false;
	}

	/**
	 * Set the ID number of the StaticSiteContentSource
	 *
	 * @param int $contentSourceID
	 * @return void
	 */
	public function setContentSourceID($contentSourceID) {
		$this->contentSourceID = $contentSourceID;
	}

	/**
	 * Prints information on the options available for running the task like, command line arguments
	 * such as verbose mode, debugging and usage examples
	 *
	 * @return void
	 */
	public function printTaskInfo() {
		$msgFragment = (Director::is_cli() ? '' : '?').'SourceID=(number) ImportID=(number)';
		$this->printMessage("Choose a SourceID and an ImportID e.g. $msgFragment", 'WARNING');
		$newLine = $this->newLine;

		// List the content sources to prompt user for selection
		if($contentSources = StaticSiteContentSource::get()) {
			$this->printMessage($newLine.'Available content-sources:'.$newLine);
			foreach($contentSources as $i => $contentSource) {
				$this->printMessage("\tdev/tasks/".__CLASS__.' SourceID=' . $contentSource->ID.' ImportID=<number>');
			}
			echo $newLine;
			if(Director::is_cli()) {
				$this->printMessage('Available command line options: '.$newLine);
				$this->printMessage("\tSHOW=pages \tPrint the contents of the pages map.");
				$this->printMessage("\tSHOW=files \tPrint the contents of the files map.");
				$this->printMessage("\tDIE=1 \t\tStop processing after showing map contents.");
				$this->printMessage("\tVERBOSE=1 \tShow debugging information while processing.");
			}
			echo $newLine;
		}
	}
}
