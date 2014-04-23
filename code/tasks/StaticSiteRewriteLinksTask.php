<?php
/**
 * Rewrites content-links found in <img> "src" and <a> "href"
 * HTML tag-attributes, which were originally imported via {@link StaticSiteImporter}.
 * 
 * The task takes two arguments:
 * 
 * - An Import ID:
 * Allows the rewriter to know which content to rewrite, when duplicate imports exist.
 * 
 * - A Source ID
 * Allows the rewriter to fetch the correct content relative to the given source of scraped URLs.
 * 
 * All rewrite failures are written the {@link FailedURLRewriteObject} DataObject to power the
 * CMS {@link FailedURLRewriteReport}.
 * 
 * @author Sam Minnee <sam@silverstripe.com>
 * @author Russell Michell <russ@silverstripe.com>
 * @author Michael Parkhill <mike@silverstripe.com>
 * @package staticsiteconnector
 */
class StaticSiteRewriteLinksTask extends BuildTask {

	/**
	 * An inexhaustive list of non http(s) URI schemes which we don't want to try to normalise.
	 *
	 * @see http://en.wikipedia.org/wiki/URI_scheme
	 * @var array
	 */
	public static $non_http_uri_schemes = array(
		'mailto',
		'tel',
		'htp',
		'ftp',
		'res',
		'skype',
		'ssh',
		'telnet',
		'gopher'
	);
	
	/**
	 * 
	 * @var string The prefix to use for the log-file summary.
	 */
	public static $summary_prefix = 'Import No.';
	
	/**
	 * @var string
	 */
	protected $description = 'Rewrites imported links into SilverStripe compatible format.';
	
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
		$this->SilentRun = trim($request->getVar('SilentRun'));

		if(!$this->checkInputs()) {
			return;
		}

		// Load imported objects and filter on ImportID, to know which links should be re-written.
		$pages = $this->contentSource->Pages()->filter('StaticSiteImportID', $this->contentImportID);
		$files = $this->contentSource->Files()->filter('StaticSiteImportID', $this->contentImportID);

		$this->printMessage("Processing Import: {$pages->count()} pages, {$files->count()} files", 'NOTICE'); 
		
		if($pages->count() == 0) {
			$this->printMessage("Nothing to rewrite! Did you forget to run an import?", 'NOTICE'); 
		}

		$pageLookup = $pages;
		$fileLookup = $files->map('StaticSiteURL', 'ID');
		
		if($show = $request->getVar('SHOW')) {
			if($show == 'pages') {
				$this->printMessage('Page Map');
				foreach($pageLookup as $page) {
					$this->printMessage($page->ID . ' => ' . $page->StaticSiteURL);
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
		$rewriter = new StaticSiteLinkRewriter(function($url) use($pageLookup, $fileLookup, $task) {

			$origUrl = $url;
			$anchor = '';
			if(strpos($url, '#') !== false) {
				list($url, $anchor) = explode('#', $url, 2);
			}

			// For accuracy, process $url using processURL(), the same as during crawl (if set)
			if($task->contentSource->UrlProcessor && $urlProcessor = singleton($task->contentSource->UrlProcessor)) {
				$processedURL = $urlProcessor->processURL(array('url' => $url, 'mime'=> 'text/html'));
				$url = $processedURL['url'];				
			}

			// Just return now if the url is un-rewritable
			if($task->ignoreUrl($url)) {
				// If it's being ignored, log it for a summary used in the CMS report.
				$task->pushFailedRewrite($task, $url);
				return;
			}

			// Strip the trailing slash, if any
			$url = substr($url, -1) == '/' ? rtrim($url, '/') : $url;

			// The keys to use to when URL-matching in $pageLookup and $fileLookup
			$pageMapKey = Controller::join_links($task->contentSource->BaseUrl, parse_url($url, PHP_URL_PATH));
			$fileMapKey = Controller::join_links($task->contentSource->BaseUrl, $origUrl);
			
			if(strlen($anchor)) {
				$task->printMessage("\tFound anchor: '#$anchor' (Removed for matching)");
			}
			
			$task->printMessage("\tPage-key: '$pageMapKey'");
			$task->printMessage("\tFile-key: '$fileMapKey'");
			
			$fileLookup = $fileLookup->toArray();

			/*
			 * Rewrite SiteTree links by replacing the phpQuery processed Page-URL 
			 * with a CMS shortcode or anchor if one is found in the 'Content' field.
			 */
			if($siteTreeObject = $pageLookup->find('StaticSiteURL', $pageMapKey)) {
				$output = '[sitetree_link,id=' . $siteTreeObject->ID . ']';
				$anchorPattern = "<[\w]+\s+(name|id)=('|\")?". $anchor ."('|\")?";
				if(strlen($anchor) && preg_match("#$anchorPattern#mi", $siteTreeObject->Content)) {
					$output = "#$anchor";
				}
				$task->printMessage("\tFound: SiteTree ID#" . $siteTreeObject->ID, null, $output);
				return $output;
			}			
			// Rewrite Asset links by replacing phpQuery processed URLs with appropriate filename.
			else if(isset($fileLookup[$fileMapKey]) && $fileID = $fileLookup[$fileMapKey]) {
				if($file = DataObject::get_by_id('File', $fileID)) {
					$output = $file->RelativeLink();
					$task->printMessage("\tFound: File ID#" . $fileID, null, $file->RelativeLink());
					return $output;
				}
			}
			else {
				// Otherwise link-rewriting has failed.
				$task->printMessage("\tRewriter failed. See detail below:");
			}
			
			// Log failures for later analysis
			$this->pushFailedRewrite($task, $url);			
			return $origUrl;
		});

		// Perform rewriting
		$changedFields = 0;
		foreach($pages as $i => $page) {
			// For the rewriter, so it has some context for urls that couldn't be re-writen.
			$this->currentPageID = $page->ID;
			$url = $page->StaticSiteURL;
			
			// Get the schema that matches the page's legacy url
			if($schema = $this->contentSource->getSchemaForURL($url, 'text/html')) {
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
			
			if($modified) {
				Versioned::reading_stage('Stage');
				$page->write();
				$page->publish('Stage', 'Live');
			}
		}
		
		$newLine = $this->newLine;
		$this->printMessage("{$newLine}Complete.");
		$this->printMessage("Amended $changedFields content fields for {$pages->count()} pages and {$files->count()} files processed.");
		
		$msgNextSteps = " - Not all links will get fixed. It's recommended to also run a 3rd party link-checker over your imported content.";
		$msgSeeReport = " - Check the CMS \"".singleton('FailedURLRewriteReport')->title()."\" report for a summary of failed link-rewrites.";
		
		$this->printMessage("Tips:");
		$this->printMessage("{$newLine}$msgNextSteps");
		$this->printMessage($msgSeeReport);
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
		if($this->SilentRun) {
			return;
		}
		$url = ($url ? '(' . $url . ') ' : '');
		$level = ($level ? '[' . $level .'] ' : '');
		if(Director::is_cli()) {
			echo "$level$message$url" . PHP_EOL;
		}
		else {
			echo "<p>$level$message$url</p>" . PHP_EOL;
		}
	}

	/**
	 * Write failed rewrites to the {@link BadImportLog} for later analysis by users
	 * via the CMS' Report admin.
	 *
	 * @return void
	 * @todo What to do with report summaries when a task for the same import is re-run?
	 */
	public function writeFailedRewrites() {
		$importID = 0;
		$postProcessed = array();
		$uniq = $this->uniq($this->listFailedRewrites);
		foreach($uniq as $failure) {
			$importID = $failure['ImportID']; // Will be the same value each time
			
			/*
			 * Prevent the same bad-link (for the same Import & container page)
			 * being written.
			 */
			$failureExists = DataObject::get('FailedURLRewriteObject')->filter(array(
				'ImportID' => $importID,
				'OrigUrl' => $failure['OrigUrl'],
				'ContainedInID' => $failure['ContainedInID']
			));
			
			if($failureExists->count() >0) {
				continue;
			}
			
			$failedURLObj = FailedURLRewriteObject::create();
			$failedURLObj->BadLinkType = $failure['BadLinkType'];
			$failedURLObj->ImportID = $failure['ImportID'];
			$failedURLObj->ContainedInID = $failure['ContainedInID'];
			$failedURLObj->OrigUrl = $failure['OrigUrl'];
			$failedURLObj->write();
		}
		
		$summaryText = self::$summary_prefix . $this->contentImportID;
		foreach($this->countFailureTypes() as $label => $payload) {
			$label = FormField::name_to_label($label) . ': ';
			$summaryText .= ' ' . $label . $payload['count'] . ' ' . $payload['desc'] . PHP_EOL;
		}
		
		// Write the summary, to be shown at the top of the report
		$summary = FailedURLRewriteSummary::create();
		$summary->Text = $summaryText;
		$summary->ImportID = $importID ? $importID : 0;
		$summary->write();
	}

	/**
	 * Returns an array of totals of all the failed URLs, in different categories according to:
	 * - No. Non $baseURL http(s) URLs
	 * - No. Non http(s) URI schemes (e.g. mailto, tel etc)
	 * - No. URLs not imported
	 * - No. Junk URLs (i.e. those not matching any of the above)
	 *
	 * @return array
	 * @todo too many URLs being collected in $this->listFailedRewrites
	 */
	public function countFailureTypes() {
		$rawData = $this->uniq($this->listFailedRewrites);
		$countThirdParty = $countBadScheme = $countNotImported = $countJunk = $countUnknown = 0;
		foreach($rawData as $data) {
			$url = $data['OrigUrl'];
			if($this->linkIsJunk($url)) {
				++$countJunk;
				continue;
			}		
			else if($this->linkIsThirdParty($url)) {
				++$countThirdParty;
				continue;
			}
			else if($this->linkIsBadScheme($url)) {
				++$countBadScheme;
				continue;
			}
			else if($this->linkIsNotImported($url)) {
				++$countNotImported;
				continue;
			}
			else {
				++$countUnknown;
			}
		}
		return array(
			'Total failed link rewrites'	=> array('count' => count($rawData), 'desc' => ''),
			'ThirdParty'		=> array('count' => $countThirdParty, 'desc' => '(Links to external websites)'),
			'BadScheme'			=> array('count' => $countBadScheme, 'desc' => '(Links with bad scheme)'),
			'NotImported'		=> array('count' => $countNotImported, 'desc' => '(Links to pages that were not imported)'),
			'Junk'				=> array('count' => $countJunk, 'desc' => '(Junk links)'),
			'Unknown'			=> array('count' => $countUnknown, 'desc' => '(Not categorisable)')
		);
	}
	
	/**
	 * Detects if a link is to a third-party website.
	 * 
	 * @param string $link
	 * @return boolean
	 */
	public function linkIsThirdParty($link) {
		$link = trim($link);
		return (bool)preg_match("#^http(s)?://#", $link);
	}
	
	/**
	 * Detects if a link uses an unsupported protocol (e.g. mailto, tel etc)
	 * 
	 * @param string $link
	 * @return boolean
	 */	
	public function linkIsBadScheme($link) {
		$nonHTTPSchemes = implode('|', self::$non_http_uri_schemes);
		$badScheme = preg_match("#^($nonHTTPSchemes):#", $link);
		$alreadyImported = $this->linkIsAlreadyRewritten($link);
		return (bool)($badScheme || $alreadyImported);
	}
	
	/**
	 * After rewrite task is run, link doesn't match a valid CMS link shortcode.
	 * 
	 * @param string $link
	 * @return boolean
	 */	
	public function linkIsNotImported($link) {
		return (bool)(stristr($link, 'sitetree') === false && stristr($link, 'assets') === false);
	}
	
	/**
	 * Detects if a link has already been re-written.
	 * 
	 * @param string $link
	 * @return boolean
	 */
	public function linkIsAlreadyRewritten($link) {
		return (bool)(stristr($link, 'sitetree') !== false || stristr($link, 'assets') !== false);
	}
	
	/**
	 * Link begins with non-legitimate character
	 * 
	 * @param string $link
	 * @return boolean
	 */
	public function linkIsJunk($link) {
		return (bool)preg_match("#^\.#", $link);
	}
	
	/**
	 * What kind of bad link is $link? The returned string should match the ENUM
	 * values on FailedURLRewriteObject
	 * 
	 * @param string $link
	 * @return string
	 * @todo can we add a check for links with anchors to other pages?
	 */
	public function badLinkType($link) {
		if($this->linkIsJunk($link)) {
			return 'Junk';
		}
		if($this->linkIsThirdParty($link)) {
			return 'ThirdParty';
		}
		if($this->linkIsBadScheme($link)) {
			return 'BadScheme';
		}
		if($this->linkIsNotImported($link)) {
			return 'NotImported';
		}
		return 'Unknown';
	}

	/**
	 * Whether or not to ingore a URL. Returns true if a URL is either:
	 * 
	 *	- An empty string
	 *	- A non-HTTP scheme like an email link see: self::$non_http_uri_schemes
	 *	- A CMS sitetree shortcode or file/image asset path, e.g. [sitetree_link,id=1234] or assets/Images/logo.gif
	 *	- An absolute url, i.e. anything that begins with 'http'
	 *
	 * @param string $url A URL
	 * @return boolean true if the url can be ignored
	 */
	public function ignoreUrl($url) {
		$url = trim($url);
		
		// Link is "Junk"
		if($this->linkIsJunk($url)) {
			$this->printMessage("\tIgnoring: $url (Junk URL)");
			return true;
		}		
		
		// Not an HTTP protocol
		if($this->linkIsBadScheme($url)) {
			$this->printMessage("\tIgnoring: $url (Non-HTTP URL)");
			return true;
		}		

		// Is external or an absolute url
		if($this->linkIsThirdParty($url)) {
			$this->printMessage("\tIgnoring: $url (3rd party URL)");
			return true;
		}
		
		// Has already been processed
		if($this->linkIsAlreadyRewritten($url)) {
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
			$this->printMessage("No content-source found via SourceID: " . $this->contentSourceID, 'WARNING');
			return false;
		}
		if(!$contentImport) {
			$this->printMessage("No content-import found via ImportID: " . $this->contentImportID, 'WARNING');
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
		$msgFragment = (Director::is_cli() ? '' : '?') . 'SourceID=(number) ImportID=(number)';
		$this->printMessage("Choose a SourceID and an ImportID e.g. $msgFragment", 'WARNING');
		$newLine = $this->newLine;

		// List the content sources to prompt user for selection
		if($contentSources = StaticSiteContentSource::get()) {
			$this->printMessage($newLine.'Available content-sources:'.$newLine);
			foreach($contentSources as $i => $contentSource) {
				$this->printMessage("\tdev/tasks/" . __CLASS__ . ' SourceID=' . $contentSource->ID . ' ImportID=<number>');
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
	
	/**
	 * Build an array of failed URL rewrites for later reporting.
	 * 
	 * @param StaticSiteRewriteLinksTask $obj
	 * @param string $link
	 * @return void
	 */
	protected function pushFailedRewrite($obj, $link) {		
		array_push($obj->listFailedRewrites, array(
			'OrigUrl' => $link,
			'ImportID' => $obj->contentImportID,
			'ContainedInID' => $obj->currentPageID,
			'BadLinkType' => $obj->badLinkType($link)
		));	
	}
	
	/**
	 * Quick way to ensure array values are unique in a multi-dimensional array.
	 * 
	 * @param array $array
	 * @return array
	 */
	protected function uniq($array) {
		$serialized = array();
		$unserialized = array();
		foreach($array as $item) {
			$serialized[] = serialize($item);
		}
		foreach(array_unique($serialized) as $item) {
			$unserialized[] = unserialize($item);
		}		
		return $unserialized;
	}
}
