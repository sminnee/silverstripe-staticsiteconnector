<?php
/**
 * @package staticsiteconnector
 */
class StaticSiteUrlListTest extends SapphireTest {
	
	/*
	 * @var string
	 */
	public static $fixture_file = 'StaticSiteContentSource.yml';
	
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
	
}