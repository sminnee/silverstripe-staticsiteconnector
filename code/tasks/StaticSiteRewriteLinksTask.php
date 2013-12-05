<?php
/**
 * Rewrite all links in content imported via staticsiteimporter.
 * All rewrite failures are written to a logfile (@see $log_file)
 * This log is used as the data source for the CMS report \BadImportsReport. This is because it's only after attempting to rewrite links that we're
 * able to anaylse why some failed. Often we find the reason is that the URL being re-written hasn't actually made it through the import process.
 *
 * @author SilverStripe Science Ninjas <scienceninjas@silverstripe.com>
 *
 * @todo Add ORM StaticSiteURL field NULL update to import process @see \StaticSiteUtils#resetStaticSiteURLs()
 * @todo add a link in the CMS UI that users can select to run this task @see https://github.com/phptek/silverstripe-staticsiteconnector/tree/feature/link-rewrite-ui
 */
class StaticSiteRewriteLinksTask extends BuildTask {

	/**
	 * Where the log file is cached
	 *
	 * The $log_file is loaded from config settings, see: mysite/_config/config.yml, e.g.
  	 *   StaticSiteRewriteLinksTask
  	 *    log_file: '/var/tmp/rewrite_links.log'
	 *
	 * Note: you need to manually create the log file and make sure the webservice can write to it, e.g. (via cli)
	 *   touch /var/tmp/rewrite_links.log && chmod 766 /var/tmp/rewrite_links.log
	 *
	 * @var string
	 */
	public static $log_file = null;

	/**
	 * An inexhaustive list of non http(s) URI schemes which we don't want to try and convert/normalise
	 *
	 * @see http://en.wikipedia.org/wiki/URI_scheme
	 * @var array
	 */
	public static $non_http_uri_schemes = array('mailto','tel','ftp','res','skype','ssh');

	/**
	 * Set to true to enable verbose output of the rewriting progress, false by default
	 * This var responds to the command line argument: VERBOSE=1
	 *
	 * @var bool $verbose
	 */
	public $verbose = false;

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
	 * The StaticSiteContentSource which has the links to be rewritten
	 *
	 * @var StaticSiteContentSource
	 */
	protected $contentSource = null;

	/**
	 * Starts the task
	 *
	 * @param SS_HTTPRequest $request The request parameter passed from the task initiator, browser or CLI
	 * @return null | void
	 */
	public function run($request) {
		// Load the logging file name from configuration settings in mysite/_config/config.yml
		self::$log_file = Config::inst()->get('StaticSiteRewriteLinksTask', 'log_file');

		// Get the StaticSiteContentSource ID from the request parameters
		$this->contentSourceID = $request->getVar('ID');
		if (!$this->contentSourceID || !is_numeric($this->contentSourceID)) {
			$this->printTaskInfo();
			return;
		}

		// Load the content source using the ID number
		if(!$this->contentSource = StaticSiteContentSource::get()->byID($this->contentSourceID)) {
			$this->printMessage("No StaticSiteContentSource found via ID: ".$this->contentSourceID,'WARNING');
			return;
		}

		// Load pages and files imported by the content source
		$pages = $this->contentSource->Pages();
		$files = $this->contentSource->Files();

		$this->printMessage("Processing Import: {$pages->count()} pages, {$files->count()} files",'NOTICE');

		// Set up rewriter
		$pageLookup = $pages->map('StaticSiteURL', 'ID');
		$fileLookup = $files->map('StaticSiteURL', 'ID');

		// Check for verbose argument
		$verbose = $request->getVar('VERBOSE');
		if ($verbose && $verbose == 1) {
			$this->verbose = true;
		}

		$show = $request->getVar('SHOW');
		if ($show) {
			if ($show == 'pages') {
				$this->printMessage('Page Map');
				foreach ($pageLookup as $url => $id) {
					$this->printMessage($id . ' => ' . $url);
				}
			}
			if ($show == 'files') {
				$this->printMessage('File Map');
				foreach ($fileLookup as $url => $id) {
					$this->printMessage($id . ' => ' . $url);
				}
			}
		}

		if ($request->getVar('DIE')) {
			return;
		}

		$baseURL = $this->contentSource->BaseUrl;
		$task = $this;
		
		// If no URL Processor is set in external-content CMS UI, check for it or calls to singleton() will fail
		$urlProcessor = null;
		if($this->contentSource->UrlProcessor) {
			$urlProcessor = singleton($this->contentSource->UrlProcessor);
		}

		// Create a callback function for the url rewriter which is called from StaticSiteLinkRewriter, passed through the variable: $callback($url)
		$rewriter = new StaticSiteLinkRewriter(function($url) use($pageLookup, $fileLookup, $baseURL, $task, $urlProcessor, $request) {

			$urlInput = $url;
			$fragment = "";
			if (strpos($url,'#') !== false) {
				list($url,$fragment) = explode('#', $url, 2);
				$fragment = '#'.$fragment;
			}

			/*
			 * Process $url just the same as we did for the value of SiteTree.StaticSiteURL during import
			 * This ensures $url === SiteTree.StaticSiteURL so we can match very accurately on it
			 * The "mime" key is an expected argument but it's not actually used within this task but defaults to the page mime type
			 */
			if($urlProcessor) {
				$processedURL = $urlProcessor->processURL(array('url' => $url, 'mime'=> 'text/html'));
				// processURL returns and array, get the url from it
				$url = $processedURL['url'];				
			}

			// Return now if the url is empty, not an http scheme or already processed into a SS shortcode
			if ($task->ignoreUrl($url)) {
				return;
			}

			// strip the trailing slash if any
			if (substr($url, -1) == '/')  {
				$url = rtrim($url, '/');
			}

			// Strip the host and protocol from the url to ensure the url is relative before creating
			// the pageMapKey as an absolute url, so it will match the keys in the page map
			$pageMapKey = Controller::join_links($baseURL, parse_url($url, PHP_URL_PATH));

			// File urls dont need processing as they dont have Pages or .aspx present
			// so create the file map key by just making the raw input url absolute
			$fileMapKey = Controller::join_links($baseURL, $urlInput);

			// Log the progress
			if ($task->verbose) {
				$task->printMessage("# rewriting: \"{$urlInput}\"");
				if ($fragment != '') $task->printMessage(" - fragment: \"{$fragment}\"");
				$task->printMessage(" - page-key: \"{$pageMapKey}\"");
				$task->printMessage(" - file-key: \"{$fileMapKey}\"");
			}

			// Rewrite SiteTree links by replacing the phpQuery processed Page-URL with a SiteTree shortcode
			// @todo replace with $pageLookup->each(function() {}) ...faster??
			// @todo put into own method
			$pageLookup = $pageLookup->toArray();
			if (isset($pageLookup[$pageMapKey]) && $siteTreeID = $pageLookup[$pageMapKey]) {
				$output = '[sitetree_link,id='.$siteTreeID.']' . $fragment;
				if ($task->verbose) {
					$task->printMessage("+ found: SiteTree ID#".$siteTreeID, null, $output);
				}
				return $output;
			}

			// Rewrite Asset links by replacing phpQuery processed Asset-URLs with the appropriate asset-filename
			//@todo replace with $fileLookup->each(function() {}) ...faster??
			//@todo put into own method
			$fileLookup = $fileLookup->toArray();
			if (isset($fileLookup[$fileMapKey]) && $fileID = $fileLookup[$fileMapKey]) {
				if ($file = DataObject::get_by_id('File', $fileID)) {
					$output = $file->RelativeLink();
					if ($task->verbose) {
						$task->printMessage("+ found: File ID#{$file->ID}", null, $output);
					}
					return $output;
				}
				else {
					$task->printMessage('File get_by_id failed with FileID: ' . $fileID . ', FileMapKey: ' . $fileMapKey, 'WARNING');
				}
			}

			$task->printMessage('Rewriter failed', 'WARNING', $urlInput);

			// log the failed rewrite
			array_push($task->listFailedRewrites, "Couldn't rewrite: " . $urlInput . " Found in Page: " . $task->currentPageTitle . " [ID#" . $task->currentPageID . "]");

			// return the url unchanged
			return $urlInput;
		});

		// Perform rewriting
		$changedFields = 0;
		foreach ($pages as $i => $page) {
			// Set these so the rewriter task can log some page context for the urls that could not be rewriten
			$this->currentPageTitle = $page->Title;
			$this->currentPageID = $page->ID;

			$url = $page->StaticSiteURL;
			$modified = false;
			if ($this->verbose) {
				$this->printMessage('------------------------------------------------');
				$this->printMessage($page->URLSegment, $i);
			}
			// Get the schema that matches the page's url
			if ($schema = $this->contentSource->getSchemaForURL($url, 'text/html')) {
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
			foreach ($fields as $field) {
				$newContent = $rewriter->rewriteInContent($page->$field);
				// if rewrite succeeded the content returned differs from the input
				if($newContent != $page->$field) {
					$newContent = str_replace(array('%5B','%5D'),array('[',']'),$newContent);
					$changedFields++;
					$this->printMessage("Changed {$field} on \"{$page->Title}\" (#{$page->ID})",'NOTICE');
					$page->$field = $newContent;
					$modified = true;
				}
			}
			
			/*
			 * Only save the page if modifications have occurred.
			 * Default is to just write the page with its changes, but not publish.
			 * If the 'PUBLISH' flag is passed, then publish it. (Beats a CMS batch update for 100s of pages)
			 */
			if ($modified) {
				if($request->getVar('PUBLISH')) {
					$page->doPublish();
				}
				else {
					$page->write();
				}
			}
		}
		$this->printMessage("Amended {$changedFields} content fields.",'NOTICE');
		$this->writeFailedRewrites();
	}

	/**
	 * Prints notices and warnings and aggregates them into two lists for later analysis, depending on $level and whether you're using the CLI or a browser
	 *
	 * @param string $message The message to log
	 * @param string $level The log level, e.g. NOTICE or WARNING
	 * @param string $url The url which was being re-written
	 * @return void
	 */
	public function printMessage($message, $level=null, $url=null) {
		if ($url) $url = '(' . $url . ')';
		if ($level) $level = '[' . $level .']';
		if (Director::is_cli()) {
			echo "{$level} {$message} {$url}" . PHP_EOL;
		}
		else {
			echo "<p>{$level} {$message} {$url}</p>" . PHP_EOL;
		}
/*
 * Commented logic allowed comprehensive and detailed information to be logged for quality debugging.
 * It is commented for now, as it is simple way too slow for imports comprising 1000s of URLs
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
	 * Write failed rewrites to a logfile for later analysis
	 *
	 * @return void
	 */
	public function writeFailedRewrites() {
		if ($this->isLoggingEnabled()) {
			$logFail = implode(PHP_EOL,$this->listFailedRewrites);
			$header = 'Failures: ('.date('d/m/Y H:i:s').')'.PHP_EOL.PHP_EOL;
			foreach ($this->countFailureTypes() as $label => $count) {
				$header .= FormField::name_to_label($label).': '.$count.PHP_EOL;
			}
			$logData = $header.PHP_EOL.$logFail.PHP_EOL;
			file_put_contents(self::$log_file, $logData);
		}
	}

	/**
	 * Check if the log filename is set and the file is writable by the webserver user
	 *
	 * @param boolean $showErrors Show an error message if the check fails, true by defualt
	 * @return boolean true The log filename is valid and writable
	 */
	public function isLoggingEnabled($showErrors = true) {
		if (!self::$log_file|| !is_writable(self::$log_file)) {
			if ($showErrors) {
				echo PHP_EOL;
				$this->printMessage(__CLASS__.' - $log_file is not defined or not writeable.');
			}
			return false;
		}	
		return true;
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
		$nonHTTPSchemes = implode('|',self::$non_http_uri_schemes);
		$countNotBase = 0;
		$countNotSchm = 0;
		$countNoImprt = 0;
		$countJunkUrl = 0;
		foreach ($rawData as $url) {
			$url = trim(str_replace("Couldn't rewrite: ",'',$url));
			if (stristr($url,'http')) {
				++$countNotBase;
			}
			else if (preg_match("#($nonHTTPSchemes):#",$url)) {
				++$countNotSchm;
			}
			else if (preg_match("#^/#",$url)) {
				++$countNoImprt;
			}
			else {
				++$countJunkUrl;
			}
		}
		return array(
			'Total'			=> sizeof($rawData),
			'ThirdParty'	=> $countNotBase,
			'BadScheme'		=> $countNotSchm,
			'BadImport'		=> $countNoImprt,
			'Junk'			=> $countJunkUrl
		);
	}

	/**
	 * Returns true if a URL is either:
	 * i. an empty string
	 * ii. a non-http scheme like an email link see: $non_http_uri_schemes
	 * iii. a cms sitetree shortcode or and file/image asset path, e.g. [sitetree, 123] or assets/Images/logo.gif
	 * iv. an absolute url, i.e. anything that beings with 'http'
 	 *
 	 * Note: $url originates from Imported content post-processed by phpQuery
	 *
	 * Example: $url = "/Style%20Library/MOT/Images/MoTivate_198-pixels.png"
	 *
	 * @param string $url A URL
	 * @return boolean True is the url can be ignored
	 */
	public function ignoreUrl($url) {
		$url = trim($url);

		// empty string
		if (!strlen($url) > 0) {
			if ($this->verbose) $this->printMessage("+ ignoring empty URL");
			return true;
		}

		// not a http protocol
		$nonHTTPSchemes = implode('|',self::$non_http_uri_schemes);
		$nonHTTPSchemes = (preg_match("#($nonHTTPSchemes):#",$url));
		if ($nonHTTPSchemes) {
			if ($this->verbose) $this->printMessage("+ ignoring Non-HTTP URL: {$url}");
			return true;
		}

		// has already been processed
		$alreadyRewritten = (preg_match("#(\[sitetree|assets)#",$url));
		if ($alreadyRewritten) {
			if ($this->verbose) $this->printMessage("+ ignoring CMS link: {$url}");
			return true;
		}

		// is external or absolute url
		$externalUrl = (substr($url,0,4) == 'http');
		if ($externalUrl) {
			if ($this->verbose) $this->printMessage("+ ignoring external url: {$url}");
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
		$this->printMessage("Please choose a Content Source ID, e.g. ?ID=(number)",'WARNING');

		// List the content sources to prompt user for selection
		if ($contentSources = StaticSiteContentSource::get()) {
			foreach ($contentSources as $i => $contentSource) {
				$this->printMessage('dev/tasks/'.__CLASS__.' ID=' . $contentSource->ID, 'ID: '. $contentSource->ID . ' | ' . $contentSource->Name);
			}
			print PHP_EOL;
			$this->printMessage('Command Line Options: ');
			$this->printMessage('SHOW=pages - show the contents of the pages map');
			$this->printMessage('SHOW=files - show the contents of the files map');
			$this->printMessage('DIE=1 - stop processing after showing map contents');
			$this->printMessage('VERBOSE=1 - show debug information while processing');
			print PHP_EOL;
		}
	}
}
