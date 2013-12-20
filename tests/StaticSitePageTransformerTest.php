<?php
/**
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 * @todo At the moment we're only using the StaticSiteURLProcessor_DropExtensions URL strategy. Add fixtures for the others
 */
class StaticSitePageTransformerTest extends SapphireTest {

	/*
	 * @var \StaticSiteFileTransformer
	 */
	protected $transformer;
	
	/*
	 * @var string
	 */
	public static $fixture_file = 'StaticSiteContentSource.yml';
	
	/*
	 * Setup
	 * 
	 * @return void
	 */
	public function setUp() {
		$this->transformer = singleton('StaticSitePageTransformer');
		parent::setUp();	
	}
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, but:
	 * - The URL isn't found in the cache
	 * - "We" in this case is this test which is basically "pretending" to be PHPCrawler
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLNotInCacheIsPage() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML3');
		$item = new StaticSiteContentItem($source, '/test-1-null.html');
		$item->source = $source;
		
		// Fail becuase test-1-null.html isn't found in the url cache
		$this->assertFalse($this->transformer->transform($item, null, 'Skip'));
		$this->assertFalse($this->transformer->transform($item, null, 'Duplicate'));
		$this->assertFalse($this->transformer->transform($item, null, 'Overwrite'));
	}	
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, but:
	 * - The URL represents a Mime-Type which doesn't match our transformer
	 * - "We" in this case is this test which is basically "pretending" to be PHPCrawler
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheNotPage() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML3');
		$item = new StaticSiteContentItem($source, '/images/test.png');
		$item->source = $source;
		
		// Fail becuase we're using a StaticSitePageTransformer on a Mime-Type of image/png
		$this->assertFalse($this->transformer->transform($item, null, 'Skip'));
		$this->assertFalse($this->transformer->transform($item, null, 'Duplicate'));
		$this->assertFalse($this->transformer->transform($item, null, 'Overwrite'));
	}
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * - We don't want to overwrite duplicates, we want to duplicate (!!)
	 * - "We" in this case is this test which is basically "pretending" to be PHPCrawler
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheIsPageStrategyDuplicate() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$item = new StaticSiteContentItem($source, '/test-about-the-team');
		$item->source = $source;
		
		// Pass becuase we do want to perform something on the URL
		$this->assertInstanceOf('StaticSiteTransformResult', $pageStrategyDup = $this->transformer->transform($item, null, 'Duplicate'));
		
		// Pass becuase regardless of duplication strategy, we should be getting a result
		$this->assertEquals('test-about-the-team-2', $pageStrategyDup->page->URLSegment);
	}
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * - We want to overwrite duplicates
	 * - "We" in this case is this test which is basically "pretending" to be PHPCrawler
	 * 
	 * @todo employ some proper mocking
	 * @todo the "overwrite" strategy doesn't actually work. Need to talk with implementing dev as to why not
	 */
	public function testTransformForURLIsInCacheIsPageStrategyOverwrite() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML8');
		$item = new StaticSiteContentItem($source, '/test-i-am-page-5');
		$item->source = $source;
		
		// Pass becuase we do want to perform something on the URL
		$this->assertInstanceOf('StaticSiteTransformResult', $pageStrategyOvr = $this->transformer->transform($item, null, 'Overwrite'));
		
		// Pass becuase regardless of duplication strategy, we should be getting a result
		$this->assertEquals('test-i-am-page-5', $pageStrategyOvr->page->URLSegment);
	}
	
	/* 
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * - We don't want to do anything with duplicates, just skip them
	 * - "We" in this case is this test which is basically "pretending" to be PHPCrawler
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheIsPageStrategySkip() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$item = new StaticSiteContentItem($source, '/test-about-the-team');
		$item->source = $source;
		
		// Fail becuase we're simply using the "skip" strategy. Nothing else needs to be done
		$this->assertFalse($this->transformer->transform($item, null, 'Skip'));
	}	
}
