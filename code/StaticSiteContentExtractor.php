<?php

require_once(dirname(__FILE__) . "/../thirdparty/phpQuery/phpQuery/phpQuery.php");

/**
 * This tool uses a combination of cURL and phpQuery to extract content from a URL.
 *
 * The URL is first downloaded using cURL, and then passed into phpQuery for processing.
 * Given a set of fieldnames and CSS selectors corresponding to them, a map of content
 * fields will be returned.
 *
 * If the URL represents a file-based Mime-Type, a File object is created and the physical file it represents can then be post-processed
 * and saved to the SS DB and F/S.
 */
class StaticSiteContentExtractor extends Object {

	/**
	 *
	 * @var string
	 */
	protected $url = null;

	/**
	 *
	 * @var string
	 */
	protected $mime = null;

	/**
	 *
	 * @var string
	 */
	protected $content = null;

	/**
	 *
	 * @var phpQueryObject
	 */
	protected $phpQuery = null;

	/**
	 *
	 * @var string
	 */
	protected $tmpFileName = '';

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
	 * "Caches" the mime-processor for use throughout
	 *
	 * @var StaticSiteMimeProcessor
	 */
	protected $mimeProcessor;

	/*
	 * @var Object
	 *
	 * Holds the StaticSiteUtils object on construct
	 */
	protected $utils;

	/**
	 * Create a StaticSiteContentExtractor for a single URL/.
	 *
	 * @param string $url The absolute URL to extract content from
	 * @param string $mimr The Mime-Type
	 * @return void
	 * @throws \Exception
	 */
	public function __construct($url,$mime) {
		$this->url = $url;
		$this->mime = $mime;
		$this->mimeProcessor = singleton('StaticSiteMimeProcessor');
		$this->utils = singleton('StaticSiteUtils');

		if (!class_exists('phpQuery')) {
			throw new Exception("The third party library 'phpQuery' is required");
		}
	}

	/**
	 * Extract content for map of field => css-selector pairs
	 *
	 * @param  array $selectorMap A map of field name => css-selector
	 * @param  StaticSiteContentItem $item The item to extract
	 * @return array A map of field name => array('selector' => selector, 'content' => field content)
	 */
	public function extractMapAndSelectors($selectorMap, $item) {

		if(!$this->phpQuery) {
			$this->fetchContent();
		}

		$output = array();

		foreach($selectorMap as $fieldName => $extractionRules) {

			if(!is_array($extractionRules)) {
				$extractionRules = array($extractionRules);
			}

			foreach($extractionRules as $extractionRule) {
				$content = null;

				if(!is_array($extractionRule)) {
					$extractionRule = array('selector' => $extractionRule);
				}

				if($this->isMimeHTML()) {
					$content = $this->extractField($extractionRule['selector'], $extractionRule['attribute'], $extractionRule['outerhtml']);
				}
				else if($this->isMimeFileOrImage()) {
					$content = $item->externalId;
				}

				if(!$content) {
					continue;
				}

				if($this->isMimeHTML()) {
					$content = $this->excludeContent($extractionRule['excludeselectors'], $extractionRule['selector'], $content);
				}

				if(!$content) {
					continue;
				}

				if(!empty($extractionRule['plaintext'])) {
					$content = Convert::html2raw($content);
				}

				// We found a match, select that one and ignore any other selectors
				$output[$fieldName] = $extractionRule;
				$output[$fieldName]['content'] = $content;
				break;
			}
		}
		return $output;
	}

	/**
	 * Extract content for a single css selector
	 *
	 * @param  string $cssSelector The selector for which to extract content.
	 * @param  string $attribute If set, the value will be from this HTML attribute
	 * @param  bool $outherHTML should we return the full HTML of the whole field
	 * @return string The content for that selector
	 */
	public function extractField($cssSelector, $attribute = null, $outerHTML = false) {

		if(!$this->phpQuery) {
			$this->fetchContent();
		}

		$elements = $this->phpQuery[$cssSelector];

		// @todo temporary workaround for File objects
		if(!$elements) {
			return '';
		}

		// just return the inner HTML for this node
		if(!$outerHTML || !$attribute) {
			return trim($elements->html());
		}

		$result = '';
		foreach($elements as $element) {
			// Get the full html for this element
			if($outerHTML) {
				$result .= $this->getOuterHTML($element);
			// Get the value of a attribute
			} elseif($attribute && trim($element->getAttribute($attribute))) {
				$result .= ($element->getAttribute($attribute)).PHP_EOL;
			}
		}

		$result = trim($result);
		return $result;
	}

	/**
	 * Strip away content from $content that matches one or many css selectors.
	 *
	 * @param array $excludeSelectors
	 * @param string $parentSelector
	 * @param string $content
	 * @return string
	 */
	protected function excludeContent($excludeSelectors, $parentSelector, $content) {
		if(!$excludeSelectors) {
			return $content;
		}

		foreach($excludeSelectors as $excludeSelector) {
			if(!trim($excludeSelector)) {
				continue;
			}
			$element = $this->phpQuery[$parentSelector.' '.$excludeSelector];
			if($element) {
				$remove = $element->htmlOuter();
				$content = str_replace($remove, '', $content);
				$this->utils->log(' - Excluded content from "'.$parentSelector.' '.$excludeSelector.'"');
			}
		}
		return $content;
	}

	/**
	 * Get the full HTML of the element and its children
	 *
	 * @param DOMElement $element
	 * @return string
	 */
	protected function getOuterHTML(DOMElement $element) {
		$doc = new DOMDocument();
		$doc->formatOutput = false;
		$doc->preserveWhiteSpace = true;
		$doc->substituteEntities = false;
		$doc->appendChild($doc->importNode($element, true));
		return $doc->saveHTML();
	}

	/**
	 *
	 * @return string
	 */
	public function getContent() {
		return $this->content;
	}

	/**
	 * Fetch the content and initialise $this->content and $this->phpQuery (the latter only if an appropriate mime-type matches)
	 *
	 * @return void
	 * @todo deal-to defaults when $this->mime isn't matched..
	 */
	protected function fetchContent() {
		$this->utils->log("Fetching {$this->url} ({$this->mime})");

		// Set some proxy options for phpCrawler
		$curlOpts = singleton('StaticSiteUtils')->defineProxyOpts(!Director::isDev());
		$response = $this->curlRequest($this->url, "GET", null, null, $curlOpts);

		if($response == 'file') {
			// Just stop here for files & images
			return;
		}
		$this->content = $response->getBody();

		// Clean up the content so phpQuery doesn't bork
		$this->prepareContent();
		$this->phpQuery = phpQuery::newDocument($this->content);
	}

	/**
	 * Use cURL to request a URL, and return a SS_HTTPResponse object (`SiteTree`) or write curl output directly to a tmp file
	 * ready for uploading to SilverStripe via Upload#load() (`File` and `Image`)
	 *
	 * @param string $url
	 * @param string $method
	 * @param string $data
	 * @param string $headers
	 * @param array $curlOptions
	 * @return boolean | \SS_HTTPResponse
	 * @todo Add checks when fetching multi Mb images to ignore anything over 2Mb??
	 */
	protected function curlRequest($url, $method, $data = null, $headers = null, $curlOptions = array()) {

		$this->utils->log("CURL START: {$this->url} ({$this->mime})");

		$ch        = curl_init();
		$timeout   = 10;
		$ssInfo = new SapphireInfo;
		$useragent = 'SilverStripe/' . $ssInfo->version();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);

		if($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// Add fields to POST and PUT requests
		if($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		} 
		elseif($method == 'PUT') {
			$put = fopen("php://temp", 'r+');
			fwrite($put, $data);
			fseek($put, 0);

			curl_setopt($ch, CURLOPT_PUT, 1);
			curl_setopt($ch, CURLOPT_INFILE, $put);
			curl_setopt($ch, CURLOPT_INFILESIZE, strlen($data));
		}

		// Follow redirects
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

		// Set any custom options passed to the request() function
		curl_setopt_array($ch, $curlOptions);

		// Run request
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		// See: http://forums.devshed.com/php-development-5/curlopt-timeout-option-for-curl-calls-isn-t-being-obeyed-605642.html
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);		// The number of seconds to wait while trying to connect.

		// Deal to files, write to them directly then return
		if($this->mimeProcessor->isOfFileOrImage($this->mime)) {
			$tmp_name = tempnam(getTempFolder().'/'.rand(), 'tmp');
			$fp = fopen($tmp_name, 'w+');
			curl_setopt($ch, CURLOPT_HEADER, 0);	// We do not want _any_ header info, it corrupts the file data
			curl_setopt($ch, CURLOPT_FILE, $fp);	// write curl response directly to file, no messing about
			curl_exec($ch);
			curl_close($ch);
			fclose($fp);
			$this->setTmpFileName($tmp_name);		// Set a tmp filename ready for passing to `Upload`
			return 'file';
		}

		$fullResponseBody = curl_exec($ch);
		$curlError = curl_error($ch);

		list($responseHeaders, $responseBody) = explode("\n\n", str_replace("\r","",$fullResponseBody), 2);
		if(preg_match("#^HTTP/1.1 100#", $responseHeaders)) {
			list($responseHeaders, $responseBody) = explode("\n\n", str_replace("\r","",$responseBody), 2);
		}

		$responseHeaders = explode("\n", trim($responseHeaders));
		// Shift off the HTTP response code
		array_shift($responseHeaders);

		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if($curlError !== '' || $statusCode == 0) {
			$this->utils->log("CURL ERROR: Error: {$curlError} Status: {$statusCode}");
			$statusCode = 500;
		}

		$response = new SS_HTTPResponse($responseBody, $statusCode);
		foreach($responseHeaders as $headerLine) {
			if(strpos($headerLine, ":") !== false) {
				list($headerName, $headerVal) = explode(":", $headerLine, 2);
				$response->addHeader(trim($headerName), trim($headerVal));
			}
		}

		$this->utils->log("CURL END: {$this->url} ({$this->mime})");
		return $response;
	}

	/**
	 * 
	 * @param string $tmp
	 * @return void
	 */
	public function setTmpFileName($tmp) {
		$this->tmpFileName = $tmp;
	}

	/**
	 * 
	 * @return string
	 */	
	public function getTmpFileName() {
		return $this->tmpFileName;
	}

	/*
	 * SilverStripe -> Mime shortcut methods
	 */
	public function isMimeHTML() {
		return $this->mimeProcessor->isOfHTML($this->mime);
	}
	public function isMimeFile() {
		return $this->mimeProcessor->isOfFile($this->mime);
	}
	public function isMimeImage() {
		return $this->mimeProcessor->isOfImage($this->mime);
	}
	public function isMimeFileOrImage() {
		return $this->mimeProcessor->isOfFileOrImage($this->mime);
	}

	/**
	 * Pre-process the content so phpQuery can parse it without violently barfing
	 * @return void
	 */
	protected function prepareContent() {
		// Trim it
		$this->content = trim($this->content);

		// Ensure the content begins with the 'html' tag
		if (stripos($this->content, '<html') === false) {
			$this->content = '<html>' . $this->content;
			$this->utils->log('Warning: content was missing html open tag');
		}
	}
}
