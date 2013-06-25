<?php

/**
 * Rewrite all links in content imported via staticsiteimporter
 *
 * @todo Add ORM StaticSiteURL field NULL update to import process
 * @todo add a link in the CMS UI that users can select to run this task
 */
class StaticSiteRewriteLinksTask extends BuildTask {

	/**
	 * Where the failure log is cached
	 *
	 * @var string
	 */
	public static $failure_log = 'failedRewrite.log';

	/**
	 * An inexhaustive list of non http(s) URI schemes which we don't want to try and convert
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
	 *
	 * @var Object
	 */
	public $contentSource = null;
	
	function run($request) {
		$id = $request->getVar('ID');
		if(!is_numeric($id) || !$id) {
			$this->printMessage("Specify ?ID=(number)",'WARNING');
			return;
		}

		// Find all pages
		$this->contentSource = StaticSiteContentSource::get()->byID($id);
		$pages = $this->contentSource->Pages();
		$files = $this->contentSource->Files();

		$this->printMessage("Looking through {$pages->count()} pages.",'NOTICE');

		// Set up rewriter
		$pageLookup = $pages->map('StaticSiteURL', 'ID');
		$fileLookup = $files->map('StaticSiteURL', 'ID');

		$baseURL = $this->contentSource->BaseUrl;
		$task = $this;

		$rewriter = new StaticSiteLinkRewriter(function($url) use($pageLookup, $fileLookup, $baseURL, $task) {
			$fragment = "";
			if(strpos($url,'#') !== false) {
				list($url,$fragment) = explode('#', $url, 2);
				$fragment = '#'.$fragment;
			}

			$url = $task->normaliseUrl($url, $baseURL);
			// Replace phpQuery processed Page-URLs with SiteTree shortcode
			if($pageLookup[$url]) {
				return '[sitetree_link,id='.$pageLookup[$url] .']' . $fragment;
			}
			// Replace phpQuery processed Asset-URLs with the appropriate asset-filename
			else if($fileLookup[$url]) {
				if($file = DataObject::get_by_id('File', $fileLookup[$url])) {
					return str_replace($baseURL,'',$file->Filename) . $fragment;
				}
			}
			// Before writing any error, ensures that the base url of $url matches the root/base URL ($baseURL)
			else {
				if(substr($url,0,strlen($baseURL)) == $baseURL) {
					// This invocation writes a log-file so it contains all failed link-rewrites for analysis
					$task->printMessage("{$url} couldn't be rewritten (logged)",'WARNING',$url);
				}
				return $url . $fragment;
			}
		});

		// Perform rewriting
		$changedFields = 0;
		foreach($pages as $page) {

			$schema = $this->contentSource->getSchemaForURL($page->URLSegment);
			if(!$schema) {
				$this->printMessage("No schema found for {$page->URLSegment}",'WARNING');
				continue;
			}
			// Get fields to process
			$fields = array();
			foreach($schema->ImportRules() as $rule) {
				if(!$rule->PlainText) {
					$fields[] = $rule->FieldName;
				}
			}
			$fields = array_unique($fields);

			foreach($fields as $field) {
				$newContent = $rewriter->rewriteInContent($page->$field);
				if($newContent != $page->$field) {
					$newContent = str_replace(array('%5B','%5D'),array('[',']'),$newContent);
					$changedFields++;

					$this->printMessage("Changed {$field} on \"{$page->Title}\" (#{$page->ID})",'NOTICE');
					$page->$field = $newContent;
				}
			}

			$page->write();
		}
		$this->printMessage("Amended {$changedFields} content fields.",'SYSTEM');
		$this->writeFailedRewrites();
	}

	/*
	 * Prints notices and warnings and aggregates them into two lists for later analysis, depending on $level and whether you're using the CLI or a browser
	 * 
	 * @param string $msg
	 * @param string $level
	 * @param string $baseURL
	 * @return void
	 */
	public function printMessage($msg,$level,$url=null) {
		if(Director::is_cli()) {
			echo "[{$level}] {$msg}".PHP_EOL;
		}
		else {
			echo "<p>[{$level}] {$msg}</p>".PHP_EOL;
		}
		if($url && $level == 'WARNING') {
			// Attempt some context for the log, so we can tell what page the rewrite failed in
			$url = preg_replace("#/$#",'',str_ireplace($this->contentSource->BaseUrl, '', $this->normaliseUrl($url, $this->contentSource->BaseUrl)));
			$pages = $this->contentSource->Pages();
			$dbFieldsToMatchOn = array();
			foreach($pages as $page) {
				foreach($page->db() as $name=>$field) {
					// @TODO Note: We're hard-coding a connection between fields named 'Contentxxxx' on the selected DataType!
					if(stristr('Content', $name)) {
						$dbFieldsToMatchOn["{$name}:PartialMatch"] = $url;
					}
				}
			}
			$failureContext = 'unknown';
			if($page = SiteTree::get()->filter($dbFieldsToMatchOn)->First()) {
				$failureContext = '"'.$page->Title.'" (#'.$page->ID.')';
			}
			array_push($this->listFailedRewrites,"Couldn't rewrite: {$url}. Found in: {$failureContext}");
		}
	}

	/*
	 * Write failed rewrites to a logfile for later analysis
	 *
	 * @return void
	 */
	public function writeFailedRewrites() {
		$logFile = getTempFolder().'/'.self::$failure_log;
		$logFail = implode(PHP_EOL,$this->listFailedRewrites);
		$totalFailures = 'Total Failures: '.sizeof($this->listFailedRewrites).PHP_EOL;
		$logData = $totalFailures.PHP_EOL.$logFail.PHP_EOL.'----'.PHP_EOL;
		file_put_contents($logFile, $logData, FILE_APPEND);
	}

	/*
	 * Normalise URLs so DB matches between `SiteTee.StaticSiteURL` and $url can be made.
	 * $url originates from Imported content post-processed by phpQuery
	 *
	 * $url example: /Style%20Library/MOT/Images/MoTivate_198-pixels.png
	 * File.StaticSiteURL example: http://www.transport.govt.nz/Style%20Library/MOT/Images/MoTivate_198-pixels.png
	 *
	 * @param string $url
	 * @param string $baseURL
	 * @return string $processed
	 * @todo Is this logic better located in `SiteTree.StaticSiteURLList`?
	 */
	public function normaliseUrl($url, $baseURL) {
		// Leave empty, root, special and pre-converted URLs alone
		$noLength = (!strlen($url)>0);
		$isRoot = ($url == '/');
		$nonHTTPSchemes = implode('|',self::$non_http_uri_schemes);
		$nonHTTPSchemes = (preg_match("#($nonHTTPSchemes):#",$url));
		$alreadyProcessed = (preg_match("#\[sitetree#",$url));
		if($noLength || $isRoot || $nonHTTPSchemes || $alreadyProcessed) {
			return $url;
		}
		$processed = trim($url);
		if(!preg_match("#^http(s)?://#",$processed)) {
			// Add a slash if there isn't one at the end of $baseURL or at the beginning of $url
			$addSlash = !(preg_match("#/$#",$baseURL) || preg_match("#^/#",$processed));
			$processed = $baseURL.$processed;
			if($addSlash) {
				$processed = $baseURL.'/'.$url;
			}
		}
		return $processed;
	}
}