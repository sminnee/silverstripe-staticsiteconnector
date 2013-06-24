<?php

/**
 * Rewrite all links in content imported via staticsiteimporter
 *
 * @todo deal-to Files and Images also
 */
class StaticSiteRewriteLinksTask extends BuildTask {

	/**
	 * Where the failure log is cached
	 */
	public static $failure_log = 'failedRewrite.log';

	/**
	 * Stores the dodgy URLs for later analysis
	 *
	 * @var type array
	 */
	public $listFailedRewrites = array();
	
	function run($request) {
		$id = $request->getVar('ID');
		if(!is_numeric($id) || !$id) {
			$this->printMessage("Specify ?ID=(number)",'WARNING');
			return;
		}

		// Find all pages
		$contentSource = StaticSiteContentSource::get()->byID($id);
		$pages = $contentSource->Pages();
		$files = $contentSource->Files();

		$this->printMessage("Looking through {$pages->count()} pages.",'NOTICE');

		// Set up rewriter
		$pageLookup = $pages->map('StaticSiteURL', 'ID');
		$fileLookup = $files->map('StaticSiteURL', 'ID');
		$baseURL = $contentSource->BaseUrl;
		$task = $this;

		$rewriter = new StaticSiteLinkRewriter(function($url) use($pageLookup, $fileLookup, $baseURL, $task) {
			$fragment = "";
			if(strpos($url,'#') !== false) {
				list($url,$fragment) = explode('#', $url, 2);
				$fragment = '#'.$fragment;
			}

			$url = $task->urlCleanup($url);
			if($pageLookup[$url]) {
				return '[sitetree_link,id='.$pageLookup[$url] .']' . $fragment;
			}
			else if($fileLookup[$url]) {
				return '[file_link,id='.$fileLookup[$url] .']';
			}
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

			$schema = $contentSource->getSchemaForURL($page->URLSegment);
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
	 */
	public function writeFailedRewrites() {
		$logFile = getTempFolder().'/'.self::$failure_log;
		$logFail = implode(PHP_EOL,$this->listFailedRewrites);
		$totalFailures = 'Total Failures: '.sizeof($this->listFailedRewrites).PHP_EOL;
		$logData = $totalFailures.PHP_EOL.$logFail.PHP_EOL.'----'.PHP_EOL;
		file_put_contents($logFile, $logData, FILE_APPEND);
	}

	/*
	 * post-process URLs so DB matches in `SiteTee.StaticSiteURL` are more easily found
	 *
	 * @param string $url
	 * @return string $url
	 * @todo Is this logic better located in `SiteTree.StaticSiteURLList`?
	 */
	public function urlCleanup($url) {
		// Clean-up urlencoded spaces, trailing slashes etc. This increaes our hit rate
		$url = preg_replace("#^(.+)/$#","$1",str_replace('%20',' ',$url));
		return $url;
	}
}