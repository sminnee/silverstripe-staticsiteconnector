<?php
/**
 * 
 * @author Russell Michell <russ@silverstripe.com>
 * @package staticsiteconnector
 */
class StaticSiteContentExtractorTest extends SapphireTest {
	
	public function testPrepareContentNoRootTag() {
		$badContent = '<head></head><body><p>test.</p></body>';
		$url = '/test/test.html';
		$mime = 'text/html';
		$extractor = new StaticSiteContentExtractor($url, $mime, $badContent);
		$extractor->prepareContent();
		$matcher = array('tag' => 'html');
		$this->assertTag($matcher, $extractor->getContent());
	}
	
	public function testPrepareContentRootTag() {
		$badContent = '<html><head></head><body><p>test.</p></body></html>';
		$url = '/test/test.html';
		$mime = 'text/html';
		$extractor = new StaticSiteContentExtractor($url, $mime, $badContent);
		$extractor->prepareContent();
		$matcher = array('tag' => 'html');
		$this->assertTag($matcher, $extractor->getContent());
	}	
	
}
