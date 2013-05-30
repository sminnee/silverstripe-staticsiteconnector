<?php

require_once(dirname(__FILE__) . "/../thirdparty/phpQuery/phpQuery/phpQuery.php");

/**
 * This tool uses a combination of cURL and phpQuery to extract content from a URL.
 *
 * The URL is first downloaded using cURL, and then passed into phpQuery for processing.
 * Given a set of fieldnames and CSS selectors corresponding to them, a map of content
 * fields will be returned.
 */
class StaticSiteContentExtractor extends Object {

	protected $url;

	protected $content;

	protected $phpQuery;

	private static $log_file = null;

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

			foreach($cssSelectors as $extractionRule) {
				if(!is_array($extractionRule)) {
					$extractionRule = array('selector' => $extractionRule);
				}

				$content = trim($this->extractField($extractionRule['selector'], $extractionRule['attribute']));

				if($extractionRule['excludeselectors']) {
					foreach($extractionRule['excludeselectors'] as $excludeSelector) {
						$element = $this->phpQuery[$extractionRule['selector'].' '.$excludeSelector];
						if($element) {
							$remove = $element->htmlOuter();
							$content = str_replace($remove, '', $content);
						}
					}
				}

				if($content) {
					if(!empty($extractionRule['plaintext'])) {
						$content = Convert::html2raw($content);
					}

					$output[$field] = $extractionRule;
					$output[$field]['content'] = $content;
					$this->log("Found value for $field");
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
	public function extractField($cssSelector, $attribute = null) {
		if(!$this->phpQuery) $this->fetchContent();

		$element = $this->phpQuery[$cssSelector];
		if($attribute) return $element->attr($attribute);
		else return $element->html();
	}

	public function getContent() {
		return $this->content;
	} 

	/**
	 * Fetcht the content and initialise $this->content and $this->phpQuery
	 * @return void
	 */
	protected function fetchContent() {
		$this->log('Fetching ' . $this->url);

		$response = $this->curlRequest($this->url, "GET");	
		$this->content = $response->getBody();
		$this->phpQuery = phpQuery::newDocument($this->content);

		//// Make the URLs all absolute

		// Useful parts of the URL
		if(!preg_match('#^[a-z]+:#i', $this->url, $matches)) throw new Exception('Bad URL: ' . $this->url);
		$protocol = $matches[0];

		if(!preg_match('#^[a-z]+://[^/]+#i', $this->url, $matches)) throw new Exception('Bad URL: ' . $this->url);
		$server = $matches[0];

		$base = (substr($this->url,-1) == '/') ? $this->url : dirname($this->url) . '/';

		$this->log('Rewriting links in content');

		$rewriter = new StaticSiteLinkRewriter(function($url) use($protocol, $server, $base) {
			// Absolute
			if(preg_match('#^[a-z]+://[^/]+#i', $url) || substr($url,0,7) == 'mailto:') return $url;

			// Protocol relative
			if(preg_match('#^//[^/]#i', $url)) return $protocol . $url;

			// Server relative
			if($url[0] == "/") return $server . $url;

			// Relative
			$result = $base . $url;
			while(strpos($result, '/../') !== false) {
				$result = preg_replace('#[^/]+/+../+#i','/', $result);
			}
			while(strpos($result, '/./') !== false) {
				$result = str_replace('/./','/', $result);
			}
			return $result;

		});

		$rewriter->rewriteInPQ($this->phpQuery);
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

	protected function log($message) {
		if($logFile = Config::inst()->get('StaticSiteContentExtractor','log_file')) {
			if(is_writable($logFile) || !file_exists($logFile) && is_writable(dirname($logFile))) {
				error_log($message . "\n", 3, $logFile);
			}
		}
	}
}
