<?php
/**
 * @package staticsiteconnector
 */

// We need PHPCrawlerDocumentInfo
require_once(BASE_PATH.'/vendor/cuab/phpcrawl/libs/PHPCrawlerDocumentInfo.class.php');

class StaticSiteUrlListTest extends SapphireTest {
	
	/**
	 * @var string
	 */
	public static $fixture_file = 'StaticSiteContentSource.yml';
	
	/**
	 * @var array
	 * Array of URL tests designed for exercising the StaticSiteURLProcessor_DropExtensions URL Processor
	 * @todo put these in the fixture file
	 */
	public static $url_patterns_for_drop_extensions = array(
		'/test/contains-double-slash-normal-and-encoded/%2ftest' => '/test/contains-double-slash-normal-and-encoded/test',
		'/test/contains-double-slash-encoded-and-normal%2f/test' => '/test/contains-double-slash-encoded-and-normal/test',
		'/test/contains-double-slash-encoded%2f%2ftest' => '/test/contains-double-slash-encoded/test',
		'/test/contains-single-slash-normal/test' => '/test/contains-single-slash-normal/test',
		'/test/contains-single-slash-encoded%2ftest' => '/test/contains-single-slash-encoded/test',
		'/test/contains-slash-encoded-bracket/%28/test' => '/test/contains-slash-encoded-bracket/test',
		'/test/contains-slash-non-encoded-bracket/(/test' => '/test/contains-slash-non-encoded-bracket/test',
		'/test/contains-UPPER-AND-lowercase/test' => '/test/contains-UPPER-AND-lowercase/test',
		'/test/contains%20single%20encoded%20spaces/test' => '/test/contains%20single%20encoded%20spaces/test',
		'/test/contains%20%20doubleencoded%20%20spaces/test' => '/test/contains%20%20doubleencoded%20%20spaces/test',
		'/test/contains%20single%20encoded%20spaces and non encoded spaces/test' => '/test/contains%20single%20encoded%20spaces and non encoded spaces/test'
	);
	
	/**
	 * @var array
	 * Array of URL tests designed for exercising the StaticSiteMOSSURLProcessor URL Processor
	 * @todo put these in the fixture file
	 */
	public static $url_patterns_for_moss = array(
		'/test/Pages/contains-MOSS-style-structure/test' => '/test/contains-MOSS-style-structure/test'
	);	
	
	/**
	 * @var array
	 */
	public static $server_codes_bad = array(400,404,500,403,301,302);
	
	/**
	 * @var array
	 */
	public static $server_codes_good = array(200);	
	
	/**
	 * Tests various facets of our URL list cache
	 */
	public function testInstantiateStaticSiteUrlList() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$cacheDir = BASE_PATH . '/staticsiteconnector/tests/static-site-1/';
		$urlList = new StaticSiteUrlList($source, $cacheDir);
		
		$this->assertGreaterThan(1, strlen($urlList->getProperty('baseURL')));
		$this->assertGreaterThan(1, strlen($urlList->getProperty('cacheDir')));
		$this->isInstanceOf('StaticSiteContentSource', $urlList->getProperty('source'));
	}
	
	/**
	 * 
	 */
	public function testSimplifyUrl() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$cacheDir = BASE_PATH . '/staticsiteconnector/tests/static-site-1/';
		$urlList = new StaticSiteUrlList($source, $cacheDir);
		
		$this->assertEquals('http://www.stuff.co.nz', $urlList->simplifyUrl('http://stuff.co.nz'));
		$this->assertEquals('http://www.stuff.co.nz', $urlList->simplifyUrl('https://stuff.co.nz'));
		$this->assertEquals('http://www.stuff.co.nz', $urlList->simplifyUrl('http://www.stuff.co.nz'));
		$this->assertEquals('http://www.stuff.co.nz', $urlList->simplifyUrl('https://www.stuff.co.nz'));	
		$this->assertEquals('http://www.STUFF.co.nz', $urlList->simplifyUrl('http://STUFF.co.nz'));		
	}
	
	/*
	 * Perhaps the most key method in the whole class: handleDocumentInfo() extends the default functionality of
	 * PHPCrawler and decides what gets parsed and what doesn't, according to the file info returned by the host webserver.
	 * handleDocumentInfo then goes on to called StaticSiteUrlList#saveURLs(), addURL(), addAbsoluteURL() etc which all have
	 * a URL processing function. 	
	 */
	
	/**
	 * Tests dodgy URLs with "Bad" server code(s) using the StaticSiteURLProcessor_DropExtensions URL Processor
	 */
	public function testHandleDocumentInfoBadServerCode_DropExtensions() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$cacheDir = BASE_PATH . '/staticsiteconnector/tests/static-site-1/';
		$urlList = new StaticSiteUrlList($source, $cacheDir);
		$urlList->setUrlProcessor(new StaticSiteURLProcessor_DropExtensions());
		$crawler = new StaticSiteCrawler($urlList);
		
		foreach(self::$url_patterns_for_drop_extensions as $urlFromServer=>$expected) {
			$urlFromServer = 'http://localhost'.$urlFromServer;
			foreach(self::$server_codes_bad as $code) {
				// Fake a server response into a PHPCrawlerDocumentInfo object
				$crawlerInfo = new PHPCrawlerDocumentInfo(); 
				$crawlerInfo->url = $urlFromServer;
				$crawlerInfo->http_status_code = $code;
				// If we get a bad server error code, we return null regardless
				$this->assertNull($crawler->handleDocumentInfo($crawlerInfo));
			}
		}
	}
	
	/**
	 * Tests dodgy URLs with "Good" server code(s) using the StaticSiteURLProcessor_DropExtensions URL Processor
	 */
	public function testHandleDocumentInfoGoodServerCode_DropExtensions() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$cacheDir = BASE_PATH . '/staticsiteconnector/tests/static-site-1/';
		$urlList = new StaticSiteUrlList($source, $cacheDir);
		$urlList->setUrlProcessor(new StaticSiteURLProcessor_DropExtensions());
		$crawler = new StaticSiteCrawler($urlList);
		
		foreach(self::$url_patterns_for_drop_extensions as $urlFromServer=>$expected) {
			$urlFromServer = 'http://localhost'.$urlFromServer;
			foreach(self::$server_codes_good as $code) {
				// Fake a server response into a PHPCrawlerDocumentInfo object
				$crawlerInfo = new PHPCrawlerDocumentInfo(); 
				$crawlerInfo->url = $urlFromServer;
				$crawlerInfo->http_status_code = $code;
				$crawler->handleDocumentInfo($crawlerInfo);
				$this->assertContains($expected, $urlList->getProcessedURLs());
			}
		}
	}	
	
	/**
	 * Tests dodgy URLs with "Bad" server code(s) using the StaticSiteMOSSURLProcessor URL Processor
	 */
	public function testHandleDocumentInfoBadServerCode_MOSS() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$cacheDir = BASE_PATH . '/staticsiteconnector/tests/static-site-1/';
		$urlList = new StaticSiteUrlList($source, $cacheDir);
		$urlList->setUrlProcessor(new StaticSiteMOSSURLProcessor());
		$crawler = new StaticSiteCrawler($urlList);
		
		$mossUrltests = array_merge(
			self::$url_patterns_for_drop_extensions,
			self::$url_patterns_for_moss
		);
		
		foreach($mossUrltests as $urlFromServer=>$expected) {
			$urlFromServer = 'http://localhost'.$urlFromServer;
			foreach(self::$server_codes_bad as $code) {
				// Fake a server response into a PHPCrawlerDocumentInfo object
				$crawlerInfo = new PHPCrawlerDocumentInfo(); 
				$crawlerInfo->url = $urlFromServer;
				$crawlerInfo->http_status_code = $code;
				// If we get a bad server error code, we return null regardless
				$this->assertNull($crawler->handleDocumentInfo($crawlerInfo));
			}
		}
	}
	
	/**
	 * Tests dodgy URLs with "Good" server code(s) using the StaticSiteMOSSURLProcessor URL Processor
	 */
	public function testHandleDocumentInfoGoodServerCode_Moss() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$cacheDir = BASE_PATH . '/staticsiteconnector/tests/static-site-1/';
		$urlList = new StaticSiteUrlList($source, $cacheDir);
		$urlList->setUrlProcessor(new StaticSiteMOSSURLProcessor());
		$crawler = new StaticSiteCrawler($urlList);
		
		$mossUrltests = array_merge(
			self::$url_patterns_for_drop_extensions,
			self::$url_patterns_for_moss
		);
		
		foreach($mossUrltests as $urlFromServer=>$expected) {
			$urlFromServer = 'http://localhost'.$urlFromServer;
			foreach(self::$server_codes_good as $code) {
				// Fake a server response into a PHPCrawlerDocumentInfo object
				$crawlerInfo = new PHPCrawlerDocumentInfo(); 
				$crawlerInfo->url = $urlFromServer;
				$crawlerInfo->http_status_code = $code;
				$crawler->handleDocumentInfo($crawlerInfo);
				$this->assertContains($expected, $urlList->getProcessedURLs());
			}
		}
	}		
	
}