<?php

require_once('../vendor/cuab/phpcrawl/libs/PHPCrawler.class.php');

/**
 * Represents a set of URLs parsed from a site.
 *
 * Makes use of PHPCrawl to prepare a list of URLs on the site
 */
class StaticSiteUrlList {

	/**
	 *
	 * @var string
	 */
	public static $undefined_mime_type = 'unknown';

	/**
	 *
	 * @var string
	 */
	protected $baseURL;

	/**
	 *
	 * @var string
	 */
	protected $cacheDir;

	/**
	 * Two element array: contains keys 'inferred' and 'regular':
	 *  - 'regular' is an array mapping raw URLs to processed URLs
	 *  - 'inferred' is an array of inferred URLs
	 */
	protected $urls = null;

	/**
	 *
	 * @var boolean
	 */
	protected $autoCrawl = false;

	/**
	 *
	 * @var StaticSiteUrlProcessor
	 */
	protected $urlProcessor = null;

	/**
	 *
	 * @var array
	 */
	protected $extraCrawlURLs = null;

	/**
	 * A list of regular expression patterns to exclude from scraping
	 *
	 * @var array
	 */
	protected $excludePatterns = array();

	/**
	 * Create a new URL List
	 * @param string $baseURL  The Base URL to find links on
	 * @param string $cacheDir The local path to cache data into
	 */
	public function __construct($baseURL, $cacheDir) {
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
	public function setUrlProcessor(StaticSiteUrlProcessor $urlProcessor) {
		$this->urlProcessor = $urlProcessor;
	}

	/**
	 * Define additional crawl URLs as an array
	 * Each of these URLs will be crawled in addition the base URL.
	 * This can be helpful if pages are getting missed by the crawl
	 */
	public function setExtraCrawlURls($extraCrawlURLs) {
		$this->extraCrawlURLs = $extraCrawlURLs;
	}

	/**
	 * Return the additional crawl URLs as an array
	 */
	public function getExtraCrawlURLs() {
		return $this->extraCrawlURLs;
	}

	/**
	 * Set an array of regular expression patterns that should be excluded from
	 * being added to the url list
	 *
	 * @param array $excludePatterns
	 */
	public function setExcludePatterns(array $excludePatterns) {
		$this->excludePatterns = $excludePatterns;
	}

	/**
	 * Get an array of regular expression patterns that should not be added to
	 * the url list
	 *
	 * @return array
	 */
	public function getExcludePatterns() {
		return $this->excludePatterns;
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

	/*
	 * Raw URL+Mime data "accessor" used internally by logic outside of the class.
	 *
	 * @return mixed string $urls or null if no cached URL/Mime data found
	 */
	public function getRawCacheData() {
		if($this->urls) {
			$urls = $this->urls;
		// Don't rely on loadUrls() as it chokes on partially completed imports
		} else if(file_exists($this->cacheDir . 'urls')) {
			$urls = unserialize(file_get_contents($this->cacheDir . 'urls'));
		} else {
			return null;
		}
		return $urls;
	}

	/**
	 * Return the number of URLs crawled so far
	 */
	public function getNumURLs() {
		if(!$urls = $this->getRawCacheData()) {
			return null;
		}

		if (!isset($urls['regular']) || !isset($urls['regular'])) {
			return null;
		}

		$_regular = array();
		$_inferred = array();
		foreach($urls['regular'] as $key => $urlData) {
			array_push($_regular,$urlData['url']);
		}
		foreach($urls['inferred'] as $key => $urlData) {
			array_push($_inferred,$urlData['url']);
		}
		return sizeof(array_unique($_regular)) + sizeof($_inferred);
	}

	/**
	 * Return the raw URLs as an array
	 * @return array
	 * @todo Unused
	 */
	public function getRawURLs() {
		if($urls = $this->getProcessedURLs()) {
			$_urls = array();
			foreach($urls as $url) {
				array_push($_urls,$url['url']);
			}
			return $_urls;
		}
	}

	/**
	 * Return a map of URLs crawled, with raw URLs as keys and processed URLs as values
	 * @return array
	 */
	public function getProcessedURLs() {
		if($this->hasCrawled() || $this->autoCrawl) {
			if($this->urls === null) $this->loadUrls();
			$_regular = array();
			$_inferred = null;
			foreach($this->urls['regular'] as $key => $urlData) {
				$_regular[$key] = $urlData['url'];
			}
			if($this->urls['inferred']) {
				$_inferred = array();
				foreach($this->urls['inferred'] as $key => $urlData) {
					$_inferred[$key] = $urlData['url'];
				}
			}
			return array_merge(
				$_regular,
				$_inferred ? array_combine($_inferred, $_inferred) : array()
			);
		}
	}

	/**
	 * There are URLs and we're not in the middle of a crawl
	 *
	 * @return boolean
	 */
	public function hasCrawled() {
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
			// This happens if you move a cache-file out of the way during debugging...
			throw new LogicException("Crawl hasn't been executed yet, and autoCrawl is set to false. Maybe a cache file has been moved?");
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
		foreach($this->urls['regular'] as $url => $urlData) {
			$processedURLData = $this->generateProcessedURL($urlData);
			$this->urls['regular'][$url] = $processedURLData;

			// Trigger parent URL back-filling on new processed URL
			//$this->parentProcessedURL($processedURL);
			$this->parentProcessedURL($processedURLData);
		}

		$this->saveURLs();
	}

	/**
	 *
	 * @param int $limit
	 * @param bool $verbose
	 * @return \StaticSiteCrawler
	 */
	public function crawl($limit=false, $verbose=false) {
		increase_time_limit_to(3600);

		if(!is_dir($this->cacheDir)) {
			if(!mkdir($this->cacheDir)) {
				user_error('Unable to create cache directory at: '.$this->cacheDir);
				exit;
			}
		}

		$crawler = new StaticSiteCrawler($this, $limit, $verbose);
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
		return $crawler;
	}

	/**
	 * Save the current list of URLs to disk
	 * @return [type] [description]
	 */
	public function saveURLs() {
		file_put_contents($this->cacheDir . 'urls', serialize($this->urls));
	}

	/**
	 * Add a URL to this list, given the absolute URL
	 * @param string $url The absolute URL
	 * @param string $content_type The Mime-Type found at this URL e.g text/html or image/png
	 */
	public function addAbsoluteURL($url,$content_type) {
		$simpifiedURL = $this->simplifyURL($url);
		$simpifiedBase = $this->simplifyURL($this->baseURL);

		if(substr($simpifiedURL,0,strlen($simpifiedBase)) == $simpifiedBase) {
			$relURL = substr($url, strlen($this->baseURL));
		} else {
			throw new InvalidArgumentException("URL $url is not from the site $this->baseURL");
		}

		return $this->addURL($relURL,$content_type);
	}

	/**
	 *
	 * @param string $url
	 * @param string $contentType
	 */
	public function addURL($url, $contentType) {
		if($this->urls === null) {
			$this->loadUrls();
		}

		// Generate and save the processed URLs
		$urlData = array(
			'url'	=> $url,
			'mime'	=> $contentType
		);
		
		$this->urls['regular'][$url] = $this->generateProcessedURL($urlData);

		// Trigger parent URL back-filling
		$this->parentProcessedURL($this->urls['regular'][$url]);
	}

	/**
	 * Add an inferred URL to the list.
	 *
	 * Since the unprocessed URL isn't available, we use the processed URL in its place.  This should be used with
	 * some caution.
	 *
	 * @param array $inferredURLData Contains the processed URL and Mime-Type to add.
	 */
	public function addInferredURL($inferredURLData) {
		if($this->urls === null) $this->loadUrls();

		// Generate and save the processed URLs
		$this->urls['inferred'][$inferredURLData['url']] = $inferredURLData;

		// Trigger parent URL back-filling
		$this->parentProcessedURL($inferredURLData);
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Return true if the given URL exists
	 * @param  string $url The URL, either absolute, or relative starting with "/"
	 * @return boolean     Does the URL exist
	 */
	public function hasURL($url) {
		if($this->urls === null) $this->loadUrls();

		// Try and relativise an absolute URL
		if($url[0] != '/') {
			$simpifiedURL = $this->simplifyURL($url);
			$simpifiedBase = $this->simplifyURL($this->baseURL);

			if(substr($simpifiedURL,0,strlen($simpifiedBase)) == $simpifiedBase) {
				$url = substr($simpifiedURL, strlen($simpifiedBase));
			} else {
				throw new InvalidArgumentException("URL $url is not from the site $this->baseURL");
			}
		}

		return isset($this->urls['regular'][$url]) || in_array($url, $this->urls['inferred']);
	}

	/**
	 * Simplify a URL.
	 * Ignores https/http differences and "www." / non differences.
	 *
	 * @param  string $url
	 * @return string
	 */
	protected function simplifyURL($url) {
		return preg_replace('#^https?://(www\.)?#i','http://www.', $url);
	}

	/**
	 * Returns true if the given URL is in the list of processed URls
	 *
	 * @param  string  $processedURL The processed URL
	 * @return boolean               True if it exists, false otherwise
	 */
	function hasProcessedURL($processedURL) {
		if($this->urls === null) $this->loadUrls();

		//return in_array($processedURL, $this->urls['regular']) || in_array($processedURL, $this->urls['inferred']);
		return in_array($processedURL, array_keys($this->urls['regular'])) || in_array($processedURL, array_keys($this->urls['inferred']));

	}

	/**
	 * Return the processed URL that is the parent of the given one.
	 *
	 * Both input and output are processed URLs
	 *
	 * @param  array $processedURLData URLData comprising a relative URL and Mime-Type
	 * @return array $processedURLData [description]
	 */
	function parentProcessedURL($processedURLData) {
		$mime = self::$undefined_mime_type;
		$processedURL = $processedURLData;
		if(is_array($processedURLData)) {
			$mime = $processedURLData['mime'];
			$processedURL = $processedURLData['url'];
		}

		$default = function($fragment) use($mime) {
			return array(
				'url' => $fragment,
				'mime' => $mime
			);
		};

		if($processedURL == "/") return $default('');

		// URL heirachy can be broken down by querystring or by URL
		$breakpoint = max(strrpos($processedURL, '?'), strrpos($processedURL,'/'));

		// Special case for children of the root
		if($breakpoint == 0) return $default('/');

		// Get parent URL
		$parentProcessedURL = substr($processedURL,0,$breakpoint);

		$processedURLData = array(
			'url'	=> $parentProcessedURL,
			'mime'	=> $mime
		);

		// If an intermediary URL doesn't exist, create it
		if(!$this->hasProcessedURL($parentProcessedURL)) $this->addInferredURL($processedURLData);

		//return $parentProcessedURL;
		return $processedURLData;
	}

	/**
	 * Return the regular URL, given the processed one.
	 *
	 * Note that the URL processing isn't reversible, so this function works looks by iterating through all URLs.
	 * If the URL doesn't exist in the list, this function returns null.
	 *
	 * @param  string $processedURL The URL after processing has been applied.
	 * @return string               The original URL.
	 * @todo Unused
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
	 * @param  mixed string|array $urlData [description]
	 * @return array $urlData [description]
	 */
	function processedURL($urlData) {
		$url = $urlData;
		$mime = self::$undefined_mime_type;
		if(is_array($urlData)) {
			$url = $urlData['url'];
			$mime = $urlData['mime'];
		}
		if($this->urls === null) $this->loadUrls();

		$urlData = array(
			'url'	=> $url,
			'mime'	=> $mime
		);
		
		if(isset($this->urls['regular'][$url])) {
			// Generate it if missing
			if($this->urls['regular'][$url] === true) {
				$this->urls['regular'][$url] = $this->generateProcessedURL($urlData);
			}
			return $this->urls['regular'][$url];

		} elseif(in_array($url, array_keys($this->urls['inferred']))) {
			return $this->urls['inferred'][$url];
		}
	}

	/**
	 * Execute custom logic for processing URLs prior to heirachy generation.
	 *
	 * This can be used to implement logic such as ignoring the "/Pages/" parts of MOSS URLs, or dropping extensions.
	 *
	 * @param  array $urlData The unprocessed URLData
	 * @return array $urlData The processed URLData
	 */
	function generateProcessedURL($urlData) {
		$urlIsEmpty = (!$urlData || !isset($urlData['url']));
		if($urlIsEmpty) throw new LogicException("Can't pass a blank URL to generateProcessedURL");
		if($this->urlProcessor) $urlData = $this->urlProcessor->processURL($urlData);
		if(!$urlData) throw new LogicException(get_class($this->urlProcessor) . " returned a blank URL.");
		return $urlData;
	}

	/**
	 * Return the URLs that are a child of the given URL
	 * @param  [type] $url [description]
	 * @return [type]      [description]
	 */
	function getChildren($url) {
		if($this->urls === null) $this->loadUrls();

		$processedURL = $this->processedURL($url);
		$processedURL = $processedURL['url'];

		// Subtly different regex if the URL ends in ? or /
		if(preg_match('#[/?]$#',$processedURL)) $regEx = '#^'.preg_quote($processedURL,'#') . '[^/?]+$#';
		else $regEx = '#^'.preg_quote($processedURL,'#') . '[/?][^/?]+$#';

		$children = array();
		foreach($this->urls['regular'] as $urlKey => $potentialProcessedChild) {
			$potentialProcessedChild = $urlKey;
			if(preg_match($regEx, $potentialProcessedChild)) {
				if(!isset($children[$potentialProcessedChild])) {
					$children[$potentialProcessedChild] = $potentialProcessedChild;
				}
			}
		}
		foreach($this->urls['inferred'] as $urlKey => $potentialProcessedChild) {
			$potentialProcessedChild = $urlKey;
			if(preg_match($regEx, $potentialProcessedChild)) {
				if(!isset($children[$potentialProcessedChild])) {
					$children[$potentialProcessedChild] = $potentialProcessedChild;
				}
			}
		}

		return array_values($children);
	}

}

class StaticSiteCrawler extends PHPCrawler {
	protected $urlList;

	/**
	 *
	 * @var bool
	 */
	protected $verbose = false;

	function __construct(StaticSiteUrlList $urlList, $limit=false, $verbose=false) {
		parent::__construct();
		$this->urlList = $urlList;
		$this->verbose = $verbose;
		if($limit) {
			$this->setPageLimit($limit);
		}
	}

	function handleHeaderInfo(PHPCrawlerResponseHeader $header) {
		// Don't parse 400/500 responses
		if($header->http_status_code > 399) {
			$message = $header->source_url . " - skipped as it's $header->http_status_code".PHP_EOL;
			error_log($message, 3, '/tmp/urls');
			if($this->verbose) {
				echo "[!] ".$message;
			}
			return -1;
		}
	}

	function handleDocumentInfo(PHPCrawlerDocumentInfo $info) {
		// Ignore errors and redirects
		if($info->http_status_code < 200) return;
		if($info->http_status_code > 299) return;

		// Ignore non HTML
		//if(!preg_match('#/x?html#', $info->content_type)) return;

		$this->urlList->addAbsoluteURL($info->url,$info->content_type);
		if($this->verbose) {
			echo "[+] ".$info->url.PHP_EOL;
		}
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

		// Prevent URLs that matches the exclude patterns to be fetched
		if($excludePatterns = $this->urlList->getExcludePatterns()) {
			foreach($excludePatterns as $pattern) {
				$validRegExp = $this->addURLFilterRule('|'.str_replace('|', '\|', $pattern).'|');

				if(!$validRegExp) {
					throw new InvalidArgumentException('Exclude url pattern "'.$pattern.'" is not a valid regular expression.');
				}
			}
		}
    }
}
