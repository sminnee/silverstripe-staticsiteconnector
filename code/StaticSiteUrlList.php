<?php

require_once(BASE_PATH."/staticsiteconnector/thirdparty/PHPCrawl/libs/PHPCrawler.class.php");

/**
 * Represents a set of URLs parsed from a site.
 *
 * Makes use of PHPCrawl to prepare a list of URLs on the site
 */
class StaticSiteUrlList {
	protected $baseURL, $cacheDir;

	protected $urls = array();

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
	 * Load the URLs, either by crawling, or by fetching from cache
	 * @return void
	 */
	function loadUrls() {
		if(file_exists($this->cacheDir . 'urls')) {
			$this->urls = unserialize(file_get_contents($this->cacheDir . 'urls'));
		} else {
			$this->crawl();
		}

		// Fill out missing parents
		foreach($this->urls as $url => $dummy) {
			$this->parentURL($url);
		}
	}

	function crawl() {
		increase_time_limit_to(3600);

		if(!is_dir($this->cacheDir)) mkdir($this->cacheDir);

		$crawler = new StaticSiteCrawler($this);
		$crawler->enableResumption();
		$crawler->setUrlCacheType(PHPCrawlerUrlCacheTypes::URLCACHE_SQLITE);
		$crawler->setWorkingDirectory($this->cacheDir);

		// Allow for resuming an incomplete crawl
		if(file_exists($this->cacheDir.'crawlerid')) {
			$crawlerID = file_get_contents($this->cacheDir.'crawlerid');
			$crawler->resume($crawlerID);
		} else {
			$crawlerID = $crawler->getCrawlerId();
			file_put_contents($this->cacheDir.'/crawlerid', $crawlerID);
		}

		$crawler->setURL($this->baseURL);
		$crawler->go();

		unlink($this->cacheDir.'crawlerid');

		ksort($this->urls);

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

		Debug::message($relURL);

		$this->urls[$relURL] = true;
	}

	function addURL($url) {
		if(!$this->urls) $this->loadUrls();

		$this->urls[$url] = true;
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Return true if the given URL exists
	 * @param  string $url The URL, either absolute, or relative starting with "/"
	 * @return boolean     Does the URL exist
	 */
	function hasURL($url) {
		if(!$this->urls) $this->loadUrls();

		// Try and relativise an absolute URL
		if($url[0] != '/') {
			if(substr($url,0,strlen($this->baseURL)) == $this->baseURL) {
				$url = substr($url, strlen($this->baseURL));
			} else {
				throw new InvalidArgumentException("URL $url is not from the site $this->baseURL");
			}
		}

		return isset($this->urls[$url]);
	}

	/**
	 * Return the URL that is the parent of the given one.
	 * @param  [type] $url A relative URL
	 * @return [type]      [description]
	 */
	function parentURL($url) {
		if(!$this->urls) $this->loadUrls();

		if($url == "/") return "";

		// URL heirachy can be broken down by querystring or by URL
		$breakpoint = max(strrpos($url, '?'), strrpos($url,'/'));

		// Special case for children of the root
		if($breakpoint == 0) return "/";

		// Get parent URL
		$parentURL = substr($url,0,$breakpoint);

		// If an intermediary URL doesn't exist, create it
		if(!$this->hasURL($parentURL)) {
			$this->addURL($parentURL);
			// Create recursively
			$this->parentURL($parentURL);
		}
		return $parentURL;
	}

	/**
	 * Return the URLs that are a child of the given URL
	 * @param  [type] $url [description]
	 * @return [type]      [description]
	 */
	function getChildren($url) {
		if(!$this->urls) $this->loadUrls();

		$children = array();
		foreach(array_keys($this->urls) as $potentialChild) {
			if($this->parentURL($potentialChild) == $url) {
				$children[] = $potentialChild;
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
		if($info->http_status_code >= 200 && $info->http_status_code < 300) {
			$this->urlList->addAbsoluteURL($info->url);
		}
	}
}