<?php
/**
 * Rewrite all links in content imported via staticsiteimporter.
 * All rewrite failures are written to a logfile (@see $log_file)
 * This log is used as the data source for the CMS report \BadImportsReport. This is because it's only after attempting to rewrite links that we're
 * able to anaylse why some failed. Often we find the reason is that the URL being re-written hasn't actually made it through the import process.
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
	 * @var HTTPRequest $request The request parameter passed from the task initiator, browser or cli
	 */
	function run($request) {
		// Load the logging file name from configuration settings in mysite/_config/config.yml
		self::$log_file = Config::inst()->get('StaticSiteRewriteLinksTask', 'log_file');

		// Get the StaticSiteContentSource ID from the request parameters
		$this->contentSourceID = $request->getVar('ID');
		if (!$this->contentSourceID || !is_numeric($this->contentSourceID)) {
			$this->printMessage("Please choose a Content Source ID, e.g. ?ID=(number)",'WARNING');

			// List the content sources to prompt user for selection
			if ($contentSources = StaticSiteContentSource::get()) {
				foreach ($contentSources as $i => $contentSource) {
					$this->printMessage('dev/tasks/'.__CLASS__.' ID=' . $contentSource->ID, 'ID: '. $contentSource->ID . ' | ' . $contentSource->Name);
				}
			}
			return;
		}
		$this->process();
	}

	/**
	 * Performs the actions of the task
	 */
	public function process() {
		// Load the content source using the ID number
		if(!$this->contentSource = StaticSiteContentSource::get()->byID($this->contentSourceID)) {
			$this->printMessage("No StaticSiteContentSource found via ID: ".$this->contentSourceID,'WARNING');
			return;
		}

		// Load pages and files imported by the content source
		$pages = $this->contentSource->Pages();
		$files = $this->contentSource->Files();

		$this->printMessage("Looking through {$pages->count()} imported pages.",'NOTICE');
		$this->printMessage("Looking through {$files->count()} imported files.",'NOTICE');

		// Set up rewriter
		$pageLookup = $pages->map('StaticSiteURL', 'ID');
		$fileLookup = $files->map('StaticSiteURL', 'ID');

		$baseURL = $this->contentSource->BaseUrl;
		$task = $this;
		$proc = singleton($this->contentSource->UrlProcessor);

		$rewriter = new StaticSiteLinkRewriter(function($url) use($pageLookup, $fileLookup, $baseURL, $task, $proc) {

			$fragment = "";
			if(strpos($url,'#') !== false) {
				list($url,$fragment) = explode('#', $url, 2);
				$fragment = '#'.$fragment;
			}

			// Create a URI for partial/regex matching
			$url = array(
				'url' => $url,
				'mime'=> ''
			);
			$url = $proc->processURL($url);
			
			if(!$url = $task->postProcessUrl($url['url'])) {
				return;
			}

			/*
			 * Rewrite Asset links
			 * Replaces phpQuery processed Asset-URLs with the appropriate asset-filename
			 * @todo replace with $fileLookup->each(function() {}) ...faster??
			 * @todo put into own method
			 */
			foreach($fileLookup as $staticSiteUrl=>$ID) {
				if(isset($staticSiteUrl[$url])) {
					if($file = DataObject::get_by_id('File',$ID)) {
						$task->printMessage("File: {$url} found",'NOTICE',$url);
						return preg_replace("#^$baseURL(.+)$#","$1",$file->Filename) . $fragment;
					}
				}
			}

			/*
			 * Rewrite SiteTree links
			 * Replaces phpQuery processed Page-URLs with SiteTree shortcodes
			 * @todo replace with $pageLookup->each(function() {}) ...faster??
			 * @todo put into own method
			 */
			foreach($pageLookup as $staticSiteUrl=>$ID) {
				if(isset($staticSiteUrl[$url])) {
					$task->printMessage("SiteTree: {$url} found",'NOTICE',$url);
					return '[sitetree_link,id='.$ID.']' . $fragment;
				}
			}
		});

		// Perform rewriting
		$changedFields = 0;
		foreach($pages as $i => $page) {
			$url = $page->StaticSiteURL;
			$mimeType = 'text/html';
			$modified = false;

			// Get the schema that matches the page's url
			if ($schema = $this->contentSource->getSchemaForURL($url, $mimeType)) {

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

			foreach($fields as $field) {
				$newContent = $rewriter->rewriteInContent($page->$field);
				if($newContent != $page->$field) {
					$newContent = str_replace(array('%5B','%5D'),array('[',']'),$newContent);
					$changedFields++;

					$this->printMessage("Changed {$field} on \"{$page->Title}\" (#{$page->ID})",'NOTICE');
					$page->$field = $newContent;
					$modified = true;
				}
			}

			if ($modified) {
				$page->write();
			}
		}
		$this->printMessage("Amended {$changedFields} content fields.",'NOTICE');
		$this->writeFailedRewrites();
	}

	/*
	 * Prints notices and warnings and aggregates them into two lists for later analysis, depending on $level and whether you're using the CLI or a browser
	 *
	 * @param string $msg
	 * @param string $level
	 * @param string $baseURL
	 * @return void
	 *
	 * @todo find a more intelligent way of matching the $page->field (See WARNING below)
	 * @todo Extrapolate the field-matching into a separate method
	 */
	public function printMessage($msg,$level,$url=null) {
		if(Director::is_cli()) {
			echo "[{$level}] {$msg}".PHP_EOL;
		}
		else {
			echo "<p>[{$level}] {$msg}</p>".PHP_EOL;
		}
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

	/*
	 * Write failed rewrites to a logfile for later analysis
	 *
	 * @return void
	 */
	public function writeFailedRewrites() {
		$logFile = self::$log_file;
		if (!$logFile) {
			error_log(__CLASS__.' log_file filename is not defined'.PHP_EOL, 3, null);
			return;
		}
		if (!is_writable($logFile)) {
			error_log(__CLASS__.' log_file is not writable: '.$logFile.PHP_EOL, 3, $logFile);
			return;
		}

		$logFail = implode(PHP_EOL,$this->listFailedRewrites);
		$header = 'Failures: ('.date('d/m/Y H:i:s').')'.PHP_EOL.PHP_EOL;
		foreach($this->countFailureTypes() as $label => $count) {
			$header .= FormField::name_to_label($label).': '.$count.PHP_EOL;
		}
		$logData = $header.PHP_EOL.$logFail.PHP_EOL;
		file_put_contents($logFile, $logData);
	}

	/*
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
		foreach($rawData as $url) {
			$url = trim(str_replace("Couldn't rewrite: ",'',$url));
			if(stristr($url,'http')) {
				++$countNotBase;
			}
			else if(preg_match("#($nonHTTPSchemes):#",$url)) {
				++$countNotSchm;
			}
			else if(preg_match("#^/#",$url)) {
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

	/*
 	 * Normalise URLs so DB PartialMatches can be done
 	 * Note: $url originates from Imported content post-processed by phpQuery.
	 *
	 * $url example: /Style%20Library/MOT/Images/MoTivate_198-pixels.png
	 * File.StaticSiteURL example:
	 *
	 * Ignore any URLs that are not normalisable and flag them for reporting
	 *
	 * @param string $url A URL
	 * @return mixed (string | boolean) A URI
	 */
	public function postProcessUrl($url) {
		// Leave all empty, root, special and pre-converted URLs - alone
		$url = trim($url);
		$noLength = (!strlen($url)>1);
		$nonHTTPSchemes = implode('|',self::$non_http_uri_schemes);
		$nonHTTPSchemes = (preg_match("#($nonHTTPSchemes):#",$url));
		$alreadyRewritten = (preg_match("#(\[sitetree|assets)#",$url));
		if($noLength) {
			$this->printMessage("No URL Length: {$url}",'NOTICE');
			return false;
		}
		if($nonHTTPSchemes) {
			$this->printMessage("Bad URL Scheme: {$url}",'NOTICE');
			return false;
		}
		if($alreadyRewritten) {
			$this->printMessage("URL already re-written: {$url}",'NOTICE');
			return false;
		}
		// For the partial match
		return preg_replace("#^http(s)?://(www\.)?(.+)/?$#","$3",$url);
	}


	/**
	 * Setter method for $this->staticSiteContentSourceID
	 *
	 * @param int $id
	 * @return void
	 */
	public function setContentSourceID($id) {
		$this->contentSourceID = $id;
	}
}
