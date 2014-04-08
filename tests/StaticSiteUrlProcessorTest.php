<?php
/**
 * 
 * @author Russell Michell <russ@silverstripe.com>
 * @package staticsiteconnector
 */
class StaticSiteUrlProcessorTest extends SapphireTest {
	
	/**
	 * Tests StaticSiteURLProcessor_DropExtensions URL Processor
	 */
	public function testStaticSiteURLProcessor_DropExtensions() {
		$processor = new StaticSiteURLProcessor_DropExtensions();
		
		$this->assertFalse($processor->processUrl('http://test.com/test1.html'));
		$this->assertFalse($processor->processUrl(''));
		$this->assertFalse($processor->processUrl(array()));
		
		$testUrlData01 = $processor->processUrl(array(
			'url' => 'http://test.com/test1.html',
			'mime' => 'text/html'
		));
		$this->assertEquals('http://test.com/test1', $testUrlData01['url']);
		
		$testUrlData02 = $processor->processUrl(array(
			'url' => 'http://test.com/test2.htm',
			'mime' => 'text/html'
		));
		$this->assertEquals('http://test.com/test2', $testUrlData02['url']);
		
		$testUrlData03 = $processor->processUrl(array(
			'url' => 'http://test.com/test3',
			'mime' => 'text/html'
		));
		$this->assertEquals('http://test.com/test3', $testUrlData03['url']);		
	}
	
}
