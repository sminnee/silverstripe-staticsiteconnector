<?php

/**
 * Rewrite all links in content imported via staticsiteimporter
 *
 * @todo Add ORM StaticSiteURL field NULL update to import process
 */
class StaticSiteRewriteLinksTask extends BuildTask {

	/**
	 * Where the failure log is cached
	 *
	 * @var string
	 */
	public static $failure_log = 'failedRewrite.log';

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
			// $url === $baseURL, don't rewrite anything
			else {
				if(substr($url,0,strlen($baseURL)) == $baseURL) {
					$task->printMessage("{$url} couldn't be rewritten",'WARNING',$url);
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
			array_push($this->listFailedRewrites,"Couldn't rewrite: {$url}");
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
	 * Normalise URLs so DB matches betweenn `SiteTee.StaticSiteURL` and $url can be made.
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
		// Leave empty, root and pre-converted URLs all alone
		if(!strlen($url)>0 || $url == '/' || preg_match("#\[sitetree#",$url)) {
			return $url;
		}
		$processed = trim($url);
		if(!preg_match("#http://#",$processed)) {
			// Add a slash if there isn't one at the end of $baseURL or the beginning of $url
			$addSlash = !(preg_match("#/$#",$baseURL) || preg_match("#^/#",$processed));
			$processed = $baseURL.$processed;
			if($addSlash) {
				$processed = $baseURL.'/'.$url;
			}
		}
		return $processed;
	}
}