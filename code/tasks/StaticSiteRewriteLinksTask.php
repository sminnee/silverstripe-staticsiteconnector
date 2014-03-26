<?php
/**
 * Rewrites content-links found in <img> element "src" and <a> element "href"
 * HTML attributes, which were originally imported via {@link StaticSiteImporter}.
 * 
 * The task takes two arguments:
 * 
 * - An Import ID:
 * Allows the rewriter to know which content to rewrite, if duplicate imports exist.
 * 
 * - A Source ID
 * Allows the rewriter to fetch the correct content relative to the given source of scraped URLs.
 * 
 * All rewrite failures are written to a logfile (@see $log_file).
 * 
 * The log file is used as the data source for the CMS report {@link BadImportsReport}, this 
 * is because it's only after attempting to rewrite links that we can
 * analyse why some failed. Often we find the reason is that the URL being re-written 
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
	 * @todo just invert things so we test for [^http(s)?] ??
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
		$this->newLine = Director::is_cli() ? PHP_EOL : '<br/>';

		// Get the StaticSiteContentSource and Import ID from request parameters
		$this->contentSourceID = trim($request->getVar('SourceID'));
		$this->contentImportID = trim($request->getVar('ImportID'));

		if(!$this->checkInputs()) {
			return;
		}

		/*
		 * Load imported page + file objects and filter the results on the passed ImportID,
		 * so the task knows which imported content-links should be re-written.
		 */
		$pages = $this->contentSource->Pages()->filter('StaticSiteImportID', $this->contentImportID);
		$files = $this->contentSource->Files()->filter('StaticSiteImportID', $this->contentImportID);

		$this->printMessage("Processing Import: {$pages->count()} pages, {$files->count()} files",'NOTICE');

		$pageLookup = $pages;
		$fileLookup = $files->map('StaticSiteURL', 'ID');
		
		if($show = $request->getVar('SHOW')) {
			if($show == 'pages') {
				$this->printMessage('Page Map');
				foreach($pageLookup as $array) {
					$this->printMessage($array['ID'] . ' => ' . $array['StaticSiteURL']);
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

		$task = $this;
		// Callback for URL rewriter, called from StaticSiteLinkRewriter and passed through $callback($url)
		$rewriter = new StaticSiteLinkRewriter(function($url) use(
				$pageLookup, $fileLookup, $task) {

			$origUrl = $url;
			$anchor = '';
			if(strpos($url, '#') !== false) {
				list($url, $anchor) = explode('#', $url, 2);
			}

			/*
			 * Process $url using same process as for SiteTree.StaticSiteURL during import.
			 * This ensures $url == SiteTree.StaticSiteURL so we can match very accurately on it.
			 * The "mime" key is an expected argument but not actually used within this task.
			 * Note: Also checks if a URL Processor is set in the CMS UI.
			 */
			if($task->contentSource->UrlProcessor && $urlProcessor = singleton($task->contentSource->UrlProcessor)) {
				$processedURL = $urlProcessor->processURL(array('url' => $url, 'mime'=> 'text/html'));
				$url = $processedURL['url'];				
			}
			else {
				return;
			}

			// Just return now if the url is "faulty"
			if($task->ignoreUrl($url)) {
				return;
			}

			// Strip the trailing slash, if any
			$url = substr($url, -1) == '/' ? rtrim($url, '/') : $url;
			$baseURL = $task->contentSource->BaseUrl;

			// The keys to use to when URL-matching in $pageLookup and $fileLookup
			$pageMapKey = Controller::join_links($baseURL, parse_url($url, PHP_URL_PATH));
			$fileMapKey = Controller::join_links($baseURL, $origUrl);
			
			if(strlen($anchor)) {
				$task->printMessage("\tFound anchor: '#$anchor' (Removed for matching)");
			}
			
			$task->printMessage("\tPage-key: '$pageMapKey'");
			$task->printMessage("\tFile-key: '$fileMapKey'");
			
			$fileLookup = $fileLookup->toArray();

			/*
			 * Rewrite SiteTree links by replacing the phpQuery processed Page-URL 
			 * with a CMS shortcode or an anchor, if one is found in the Content field.
			 */
			if($siteTreeObject = $pageLookup->find('StaticSiteURL', $pageMapKey)) {
				$output = '[sitetree_link,id=' . $siteTreeObject->ID . ']';
				// If $anchor is found, use that.
				$anchorPattern = "<[\w]+\s+(name|id)=('|\")?". $anchor ."('|\")?";
				if(strlen($anchor) && preg_match("#$anchorPattern#mi", $siteTreeObject->Content)) {
					$output = "#$anchor";
				}
				$task->printMessage("\tFound: SiteTree ID#" . $siteTreeObject->ID, null, $output);
				return $output;
			}			
			/*
			 * Rewrite Asset links by replacing phpQuery processed asset-URLs with 
			 * the appropriate asset-filename.
			 */
			else if(isset($fileLookup[$fileMapKey]) && $fileID = $fileLookup[$fileMapKey]) {
				if($file = DataObject::get_by_id('File', $fileID)) {
					$task->printMessage("\tFound: File ID#" . $fileID, null, $file->RelativeLink());
					return $output;
				}
			}
			else {
				// Otherwise link-rewriting has failed.
				$task->printMessage("\tRewriter failed. See detail below:");
			}

			// Log failures
			$segment01 = "Couldn't rewrite: '$origUrl'";
			$segment02 = " Found in Page: '" . $task->currentPageTitle ."'";
			$segment03 = " ID: " . $task->currentPageID;
			array_push($task->listFailedRewrites, $segment01 . $segment02 . $segment03);

			return $origUrl;
		});

		// Perform rewriting
		$changedFields = 0;
		foreach($pages as $i => $page) {
			// For the rewriter, so it has some context for urls that couldn't be re-writen.
			$this->currentPageTitle = $page->Title;
			$this->currentPageID = $page->ID;
			$url = $page->StaticSiteURL;
			
			// Get the schema that matches the page's legacy url
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
				$this->printMessage("\tNo schema found for {$page->URLSegment}",'WARNING');
				continue;
			}
			
			$modified = false;
			foreach($fields as $field) {
				$task->printMessage("START Rewriter for links in: '$url'");
				$newContent = $rewriter->rewriteInContent($page->$field);
				// Square-brackets are converted upstream, so change them back.
				$fieldContent = str_replace(array('%5B', '%5D'), array('[', ']'), $newContent);
				
				// If rewrite succeeded, then the content returned should differ from the original
				if($fieldContent != $page->$field) {
					$changedFields++;
					$this->printMessage("\tChanged field: '$field' on page: \"{$page->Title}\" ID: {$page->ID}");
					$page->$field = $fieldContent;
					$modified = true;
				}
				else {
					$task->printMessage("\tNothing to rewrite");	
				}
				$task->printMessage("END Rewriter for links in: '$url'");
			}
			
			// If the 'PUBLISH' param is passed, then publish the object.
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
		StaticSiteUtils:create()->log($logData, null, null, __CLASS__);
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
			if(stristr($url, 'http')) {
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
	 *	- A non-HTTP scheme like an email link see: self::$non_http_uri_schemes
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
			$this->printMessage("\tIgnoring: '' (Empty link)");
			return true;
		}

		// Not an HTTP protocol
		$nonHTTPSchemes = implode('|', self::$non_http_uri_schemes);
		$nonHTTPSchemes = (preg_match("#($nonHTTPSchemes):#", $url));
		if($nonHTTPSchemes) {
			$this->printMessage("\tIgnoring: $url (Non-HTTP URL)");
			return true;
		}		

		// Is external or an absolute url
		$externalUrl = (substr($url, 0, 4) == 'http');
		if($externalUrl) {
			$this->printMessage("\tIgnoring: $url (3rd party URL)");
			return true;
		}
		
		// Has already been processed
		$alreadyRewritten = (preg_match("#(\[sitetree|assets)#", $url));
		if($alreadyRewritten) {
			$this->printMessage("\tIgnoring: $url (CMS link, already converted)");
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
	 * Checks the user-passed data is cotia.
	 * 
	 * @return boolean
	 */
	public function checkInputs() {	
		$hasSid = ($this->contentSourceID && is_numeric($this->contentSourceID));
		$hasIid = ($this->contentImportID && is_numeric($this->contentImportID));
		if(!$hasSid || !$hasIid) {
			$this->printTaskInfo();
			return false;
		}

		// Load the content source using the passed content-source ID and Import ID
		$this->contentSource = (StaticSiteContentSource::get()->byID($this->contentSourceID));
		$contentImport = (StaticSiteImportDataObject::get()->byID($this->contentImportID));
		if(!$this->contentSource) {
			$this->printMessage("No content-source found via SourceID: ".$this->contentSourceID, 'WARNING');
			return false;
		}
		if(!$contentImport) {
			$this->printMessage("No content-import found via ImportID: ".$this->contentImportID, 'WARNING');
			return false;
		}
		return true;
	}

	/**
	 * Prints information on the options available for running the task and
	 * debugging and usage examples.
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
				$this->printMessage("\tSourceID=<number> \t\tThe ID of the original crawl.");
				$this->printMessage("\tImportID=<number> \t\tThe ID of the import to use.");
				$this->printMessage("\tSHOW=pages \tPrint the contents of the pages map.");
				$this->printMessage("\tSHOW=files \tPrint the contents of the files map.");
				$this->printMessage("\tDIE=1 \t\tStop processing after showing map contents.");
			}
			echo $newLine;
		}
	}
}
