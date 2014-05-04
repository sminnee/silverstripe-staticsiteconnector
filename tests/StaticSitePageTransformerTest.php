<?php
/**
 * 
 * @author Russell Michell <russell@silverstripe.com>
 * @package staticsiteconnector
 * @todo add tests that excercise duplicationStrategy() with a non-null $parentId param
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
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheNotPage() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML3');
		$item = new StaticSiteContentItem($source, '/images/test.png');
		$item->source = $source;
		
		// Fail becuase we're using a SiteTree/Page transformer on an image
		$this->assertFalse($this->transformer->transform($item, null, 'Skip'));
		$this->assertFalse($this->transformer->transform($item, null, 'Duplicate'));
		$this->assertFalse($this->transformer->transform($item, null, 'Overwrite'));
	}
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * - We don't want to overwrite duplicates, we want to duplicate them.
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheIsPageStrategyDuplicate() {		
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$item = new StaticSiteContentItem($source, '/test-about-the-team');
		$item->source = $source;
		
		// Pass becuase we do want to perform something on the URL
		$this->assertInstanceOf('StaticSiteTransformResult', $pageStrategyDup1 = $this->transformer->transform($item, null, 'Duplicate'));
		$this->assertInstanceOf('StaticSiteTransformResult', $pageStrategyDup2 = $this->transformer->transform($item, null, 'Duplicate'));
		
		// Pass becuase regardless of duplication strategy, we should be getting a result
		$this->assertEquals('test-about-the-team', $pageStrategyDup1->page->URLSegment);
		$this->assertEquals('test-about-the-team-2', $pageStrategyDup2->page->URLSegment);
	}
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * - We want to overwrite duplicates
	 * 
	 * @todo employ some proper mocking
	 * @todo the "overwrite" strategy doesn't actually work. Need to talk with implementing dev as to why not
	 */
	public function testTransformForURLIsInCacheIsPageStrategyOverwrite() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML8');
		$item = new StaticSiteContentItem($source, '/test-i-am-page-5');
		$item->source = $source;
		
		// Pass becuase we do want to perform something on the URL
		$this->assertInstanceOf('StaticSiteTransformResult', $pageStrategyOvr1 = $this->transformer->transform($item, null, 'Overwrite'));
		$this->assertInstanceOf('StaticSiteTransformResult', $pageStrategyOvr2 = $this->transformer->transform($item, null, 'Overwrite'));
		
		// Pass becuase regardless of duplication strategy, we should be getting a result
		$this->assertEquals('test-i-am-page-5', $pageStrategyOvr1->page->URLSegment);
		$this->assertEquals('test-i-am-page-5', $pageStrategyOvr2->page->URLSegment);
	}
	
	/* 
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * - We don't want to do anything with duplicates, just skip them
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheIsPageStrategySkip() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$item = new StaticSiteContentItem($source, '/test-about-the-team');
		$item->source = $source;
		
		// Pass becuase we do want to perform something on the URL
		$this->assertInstanceOf('StaticSiteTransformResult', $this->transformer->transform($item, null, 'Skip'));
		$this->assertFalse($this->transformer->transform($item, null, 'Skip'));
	}	
	
	/**
	 * Test we get an instance of StaticSiteContentExtractor to use in custom StaticSiteDataTypeTransformer
	 * subclasses.
	 */
	public function testGetContentFieldsAndSelectorsNonSSType() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsHTML7');
		$item = new StaticSiteContentItem($source, '/test-about-the-team');
		$item->source = $source;
		
		$this->assertInstanceOf('StaticSiteContentExtractor', $this->transformer->getContentFieldsAndSelectors($item, 'Custom'));
		$this->assertNotInstanceOf('StaticSiteContentExtractor', $this->transformer->getContentFieldsAndSelectors($item, 'SiteTree'));
	}	
}
