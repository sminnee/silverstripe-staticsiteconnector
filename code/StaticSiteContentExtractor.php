<?php

require(dirname(__FILE__) . "/../thirdparty/phpQuery/phpQuery/phpQuery.php");

/**
 * This tool uses a combination of cURL and phpQuery to extract content from a URL.
 *
 * The URL is first downloaded using cURL, and then passed into phpQuery for processing.
 * Given a set of fieldnames and CSS selectors corresponding to them, a map of content
 * fields will be returned.
 */
class StaticSiteContentExtractor {

	protected $url;

	protected $content;

	protected $phpQuery;

	/**
	 * Create a StaticSiteContentExtractor for a single URL/.
	 * @param string $url The absolute URL to extract content from
	 */
	public function __construct($url) {
		$this->url = $url;
	}

	/**
	 * Extract content for map of field => css-selector pairs
	 * @param  array $selectorMap A map of field name => css-selector
	 * @return array              A map of field name => array('selector' => selector, 'content' => field content)
	 */
	public function extractMapAndSelectors($selectorMap) {
		if(!$this->phpQuery) $this->fetchContent();

		$output = array();

		foreach($selectorMap as $field => $cssSelectors) {
			if(!is_array($cssSelectors)) $cssSelectors = array($cssSelectors);

			foreach($cssSelectors as $cssSelector) {
				$content = trim($this->extractField($cssSelector));
				if($content) {
					$output[$field] = array(
						'selector' => $cssSelector,
						'content' => $content,
					);
					break;
				}
			}
		}

		return $output;
	}

	/**
	 * Extract content for a single css selector
	 * @param  string $cssSelector The selector for which to extract content.
	 * @return string              The content for that selector
	 */
	public function extractField($cssSelector) {
		if(!$this->phpQuery) $this->fetchContent();

		return $this->phpQuery[$cssSelector]->html();
	}

	public function getContent() {
		return $this->content;
	} 

	/**
	 * Fetcht the content and initialise $this->content and $this->phpQuery
	 * @return void
	 */
	protected function fetchContent() {
		$response = $this->curlRequest($this->url, "GET");	
		$this->content = $response->getBody();
		$this->phpQuery = phpQuery::newDocument($this->content);
	}

	/**
	 * Use cURL to request a URL, and return a SS_HTTPResponse object.
	 */
	protected function curlRequest($url, $method, $data = null, $headers = null, $curlOptions = array()) {
		$ch        = curl_init();
		$timeout   = 5;
		$ssInfo = new SapphireInfo;
		$useragent = 'SilverStripe/' . $ssInfo->version();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		if (isset($_ENV['HTTP_PROXY'])) {
			curl_setopt($ch, $_ENV['HTTP_PROXY']);
		}

		/*
		// Cookie behaviour - is this necessary?
		$ckfile = "/tmp/cookie";
		curl_setopt ($ch, CURLOPT_COOKIEJAR, $ckfile); 
		if(file_exists($ckfile)) {
			curl_setopt ($ch, CURLOPT_COOKIEFILE, $ckfile); 
		}
		 */

		curl_setopt($ch, CURLOPT_HEADER, 1);

		if($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// Add fields to POST and PUT requests
		if($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		} elseif($method == 'PUT') {
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
		$fullResponseBody = curl_exec($ch);
		$curlError = curl_error($ch);

		list($responseHeaders, $responseBody) = explode("\n\n", str_replace("\r","",$fullResponseBody), 2);
		if(preg_match("#^HTTP/1.1 100#", $responseHeaders)) {
			list($responseHeaders, $responseBody) = explode("\n\n", str_replace("\r","",$responseBody), 2);
		}

		$responseHeaders = explode("\n", trim($responseHeaders));
		array_shift($responseHeaders);

		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 			
		if($curlError !== '' || $statusCode == 0) $statusCode = 500;

		$response = new SS_HTTPResponse($responseBody, $statusCode);		
		foreach($responseHeaders as $headerLine) {
			if(strpos($headerLine, ":") !== false) {
				list($headerName, $headerVal) = explode(":", $headerLine, 2);
				$response->addHeader(trim($headerName), trim($headerVal));
			}
		}

		curl_close($ch);

		return $response;
	}
}