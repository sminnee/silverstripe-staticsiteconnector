<?php

require_once('../vendor/cuab/phpcrawl/libs/PHPCrawler.class.php');

/**
 * Represents a set of URLs parsed from a site.
 *
 * Makes use of PHPCrawl to prepare a list of URLs on the site
 */
class StaticSiteUrlList {
	protected $baseURL, $cacheDir;

	/**
	 * Two element array: contains keys 'inferred' and 'regular':
	 *  - 'regular' is an array mapping raw URLs to processed URLs
	 *  - 'inferred' is an array of inferred URLs
	 */
	protected $urls = null;

	protected $autoCrawl = false;

	protected $urlProcessor = null;

	protected $extraCrawlURLs = null;

	/**
	 * Create a new URL List
	 * @param string $baseURL  The Base URL to find links on
	 * @param string $cacheDir The local path to cache data into
	 */
	function __construct($baseURL, $cacheDir) {
		// baseURL mus not have a trailing slash
		if(substr($baseURL,-1) == "/") $baseURL = substr($baseURL,0,-1);
		// cacheDir must have a trailing slash
		if(substr($cacheDir,-1) != "/") $cacheDir .= "/";

		$this->baseURL = $baseURL;
		$this->cacheDir = $cacheDir;
	}

	/**
	 * Set a URL processor for this URL List.
	 *
	 * URL processors process the URLs before the site heirarchy and inferred meta-data are generated.
	 * These can be used to tranform URLs from CMSes that don't provide a natural heirarchy into something
	 * more useful.
	 *
	 * See {@link StaticSiteMOSSURLProcessor} for an example.
	 * 
	 * @param StaticSiteUrlProcessor $urlProcessor [description]
	 */
	function setUrlProcessor(StaticSiteUrlProcessor $urlProcessor) {
		$this->urlProcessor = $urlProcessor;
	}

	/**
	 * Define additional crawl URLs as an array
	 * Each of these URLs will be crawled in addition the base URL.
	 * This can be helpful if pages are getting missed by the crawl
	 */
	function setExtraCrawlURls($extraCrawlURLs) {
		$this->extraCrawlURLs = $extraCrawlURLs;
	}

	/**
	 * Return the additional crawl URLs as an array
	 */
	function getExtraCrawlURLs() {
		return $this->extraCrawlURLs;
	}

	/**
	 * 
	 * Set whether the crawl should be triggered on demand.
	 * @param [type] $autoCrawl [description]
	 */
	public function setAutoCrawl($autoCrawl) {
		$this->autoCrawl = $autoCrawl;
	}

	/**
	 * Returns the status of the spidering: "Complete", "Partial", or "Not started"
	 * @return [type] [description]
	 */
	public function getSpiderStatus() {
		if(file_exists($this->cacheDir . 'urls')) {
			if(file_exists($this->cacheDir . 'crawlerid')) return "Partial";
			else return "Complete";

		} else {
			return "Not started";
		}
	}

	/**
	 * Return the number of URLs crawled so far
	 */
	public function getNumURLs() {
		if($this->urls) {
			$urls = $this->urls;
		// Don't rely on loadUrls() as it chokes on partially completed imports
		} else if(file_exists($this->cacheDir . 'urls')) {
			$urls = unserialize(file_get_contents($this->cacheDir . 'urls'));
		} else {
			return null;
		}

		return sizeof($urls['regular']) + sizeof($urls['inferred']);
	}

	/**
	 * Return the raw URLs as an array
	 * @return array
	 */
	public function getRawURLs() {
		if($urls = $this->getProcessedURLs()) {
			return array_keys($urls);
		}
	}

	/**
	 * Return a map of URLs crawled, with raw URLs as keys and processed URLs as values
	 * @return array
	 */
	public function getProcessedURLs() {
		if($this->hasCrawled() || $this->autoCrawl) {
			if($this->urls === null) $this->loadUrls();
			return array_merge(
				$this->urls['regular'],
				$this->urls['inferred'] ? array_combine($this->urls['inferred'], $this->urls['inferred']) : array()
			);
		}
	}

	public function hasCrawled() {
		// There are URLs and we're not in the middle of a crawl
		return file_exists($this->cacheDir . 'urls') && !file_exists($this->cacheDir . 'crawlerid');
	}

	/**
	 * Load the URLs, either by crawling, or by fetching from cache
	 * @return void
	 */
	public function loadUrls() {
		if($this->hasCrawled()) {
			$this->urls = unserialize(file_get_contents($this->cacheDir . 'urls'));
			// Clear out obsolete format
			if(!isset($this->urls['regular']) || !isset($this->urls['inferred'])) {
				$this->urls = array('regular' => array(), 'inferred' => array());
			}

		} else if($this->autoCrawl) {
			$this->crawl();

		} else {
			throw new LogicException("Crawl hasn't been executed yet, and autoCrawl is set to false");
		}
	}

	/**
	 * Re-execute the URL processor on all the fetched URLs
	 * @return void
	 */
	public function reprocessUrls() {
		if($this->urls === null) $this->loadUrls();

		// Clear out all inferred URLs; these will be added
		$this->urls['inferred'] = array();

		// Reprocess URLs, in case the processing has changed since the last crawl
		foreach($this->urls['regular'] as $url => $oldProcessed) {
			$processedURL = $this->generateProcessedURL($url);
			$this->urls['regular'][$url] = $processedURL;

			// Trigger parent URL back-filling on new processed URL
			$this->parentProcessedURL($processedURL);
		}

		$this->saveURLs();
	}

	public function crawl() {
		increase_time_limit_to(3600);

		if(!is_dir($this->cacheDir)) mkdir($this->cacheDir);

		$crawler = new StaticSiteCrawler($this);
		$crawler->enableResumption();
		$crawler->setUrlCacheType(PHPCrawlerUrlCacheTypes::URLCACHE_SQLITE);
		$crawler->setWorkingDirectory($this->cacheDir);

		// Allow for resuming an incomplete crawl
		if(file_exists($this->cacheDir.'crawlerid')) {
			// We should re-load the partial list of URLs, if relevant
			// This should only happen when we are resuming a partial crawl
			if(file_exists($this->cacheDir . 'urls')) {
				$this->urls = unserialize(file_get_contents($this->cacheDir . 'urls'));
			} else {
				$this->urls = array('regular' => array(), 'inferred' => array());
			}
			
			$crawlerID = file_get_contents($this->cacheDir.'crawlerid');
			$crawler->resume($crawlerID);
		} else {
			$crawlerID = $crawler->getCrawlerId();
			file_put_contents($this->cacheDir.'/crawlerid', $crawlerID);
			$this->urls = array('regular' => array(), 'inferred' => array());
		}

		$crawler->setURL($this->baseURL);
		$crawler->go();

		unlink($this->cacheDir.'crawlerid');

		ksort($this->urls['regular']);
		ksort($this->urls['inferred']);
		$this->saveURLs();
	}

	/**
	 * Save the current list of URLs to disk
	 * @return [type] [description]
	 */
	function saveURLs() {
		file_put_contents($this->cacheDir . 'urls', serialize($this->urls));
	}

	/**
	 * Add a URL to this list, given the absolute URL
	 * @param string $url The absolute URL
	 */
	function addAbsoluteURL($url) {
		if(substr($url,0,strlen($this->baseURL)) == $this->baseURL) {
			$relURL = substr($url, strlen($this->baseURL));
		} else {
			throw new InvalidArgumentException("URL $url is not from the site $this->baseURL");
		}

		return $this->addURL($relURL);
	}

	function addURL($url) {
		if($this->urls === null) $this->loadUrls();

		// Generate and save the processed URLs
		$this->urls['regular'][$url] = $this->generateProcessedURL($url);

		// Trigger parent URL back-filling
		$this->parentProcessedURL($this->urls['regular'][$url]);
	}


	/**
	 * Add an inferred URL to the list.
	 * 
	 * Since the unprocessed URL isn't available, we use the processed URL in its place.  This should be used with
	 * some caution.
	 * 
	 * @param string $processedURL The processed URL to add.
	 */
	function addInferredURL($inferredURL) {
		if($this->urls === null) $this->loadUrls();

		// Generate and save the processed URLs
		$this->urls['inferred'][$inferredURL] = $inferredURL;

		// Trigger parent URL back-filling
		$this->parentProcessedURL($inferredURL);
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Return true if the given URL exists
	 * @param  string $url The URL, either absolute, or relative starting with "/"
	 * @return boolean     Does the URL exist
	 */
	function hasURL($url) {
		if($this->urls === null) $this->loadUrls();

		// Try and relativise an absolute URL
		if($url[0] != '/') {
			if(substr($url,0,strlen($this->baseURL)) == $this->baseURL) {
				$url = substr($url, strlen($this->baseURL));
			} else {
				throw new InvalidArgumentException("URL $url is not from the site $this->baseURL");
			}
		}

		return isset($this->urls['regular'][$url]) || in_array($url, $this->urls['inferred']);
	}

	/**
	 * Returns true if the given URL is in the list of processed URls
	 * 
	 * @param  string  $processedURL The processed URL
	 * @return boolean               True if it exists, false otherwise
	 */
	function hasProcessedURL($processedURL) {
		if($this->urls === null) $this->loadUrls();

		return in_array($processedURL, $this->urls['regular']) || in_array($processedURL, $this->urls['inferred']);

	}

	/**
	 * Return the processed URL that is the parent of the given one.
	 *
	 * Both input and output are processed URLs
	 * 
	 * @param  string $url A relative URL
	 * @return string      [description]
	 */
	function parentProcessedURL($processedURL) {
		if($processedURL == "/") return "";

		// URL heirachy can be broken down by querystring or by URL
		$breakpoint = max(strrpos($processedURL, '?'), strrpos($processedURL,'/'));

		// Special case for children of the root
		if($breakpoint == 0) return "/";

		// Get parent URL
		$parentProcessedURL = substr($processedURL,0,$breakpoint);

		// If an intermediary URL doesn't exist, create it
		if(!$this->hasProcessedURL($parentProcessedURL)) $this->addInferredURL($parentProcessedURL);

		return $parentProcessedURL;
	}

	/**
	 * Return the regular URL, given the processed one.
	 *
	 * Note that the URL processing isn't reversible, so this function works looks by iterating through all URLs.
	 * If the URL doesn't exist in the list, this function returns null.
	 * 
	 * @param  string $processedURL The URL after processing has been applied.
	 * @return string               The original URL.
	 */
	function unprocessedURL($processedURL) {
		if($url = array_search($processedURL, $this->urls['regular'])) {
			return $url;
		
		} else if(in_array($processedURL, $this->urls['inferred'])) {
			return $processedURL;
		} else {
			return null;
		}
	}

	/**
	 * Find the processed URL in the URL list
	 * @param  [type] $url [description]
	 * @return [type]      [description]
	 */
	function processedURL($url) {
		if($this->urls === null) $this->loadUrls();

		if(isset($this->urls['regular'][$url])) {
			// Generate it if missing
			if($this->urls['regular'][$url] === true) $this->urls['regular'][$url] = $this->generateProcessedURL($url);
			return $this->urls['regular'][$url];
		
		} elseif(in_array($url, $this->urls['inferred'])) {
			return $url;
		}
	}

	/**
	 * Execute custom logic for processing URLs prior to heirachy generation.
	 *
	 * This can be used to implement logic such as ignoring the "/Pages/" parts of MOSS URLs, or dropping extensions.
	 * 
	 * @param  string $url The unprocessed URL
	 * @return string      The processed URL
	 */
	function generateProcessedURL($url) {
		if(!$url) throw new LogicException("Can't pass a blank URL to generateProcessedURL");
		if($this->urlProcessor) $url = $this->urlProcessor->processURL($url);
		if(!$url) throw new LogicException(get_class($this->urlProcessor) . " returned a blank URL.");
		return $url;
	}

	/**
	 * Return the URLs that are a child of the given URL
	 * @param  [type] $url [description]
	 * @return [type]      [description]
	 */
	function getChildren($url) {
		if($this->urls === null) $this->loadUrls();

		$processedURL = $this->processedURL($url);

		// Subtly different regex if the URL ends in ? or /
		if(preg_match('#[/?]$#',$processedURL)) $regEx = '#^'.preg_quote($processedURL,'#') . '[^/?]+$#';
		else $regEx = '#^'.preg_quote($processedURL,'#') . '[/?][^/?]+$#';

		$children = array();
		foreach($this->urls['regular'] as $potentialChild => $potentialProcessedChild) {
			if(preg_match($regEx, $potentialProcessedChild)) {
				$children[] = $potentialChild;
			}
		}
		foreach($this->urls['inferred'] as $potentialProcessedChild) {
			if(preg_match($regEx, $potentialProcessedChild)) {
				$children[] = $potentialProcessedChild;
			}
		}

		return $children;
	}

}

class StaticSiteCrawler extends PHPCrawler {
	protected $urlList;

	function __construct(StaticSiteUrlList $urlList) {
		parent::__construct();
		$this->urlList = $urlList;
	}

	function handleDocumentInfo(PHPCrawlerDocumentInfo $info) {
		// Ignore errors and redirects
		if($info->http_status_code < 200) return;
		if($info->http_status_code > 299) return;

		// Ignore non HTML
		if(!preg_match('#/x?html#', $info->content_type)) return;

		$this->urlList->addAbsoluteURL($info->url);
		$this->urlList->saveURLs();
	}

	protected function initCrawlerProcess() {
		parent::initCrawlerProcess();

		// Add additional URLs to crawl to the crawler's LinkCache
		// NOTE: This is using an undocumented API
		if($extraURLs = $this->urlList->getExtraCrawlURLs()) {
			foreach($extraURLs as $extraURL) {
    			$this->LinkCache->addUrl(new PHPCrawlerURLDescriptor($extraURL));
    		}
    	}
    }
}