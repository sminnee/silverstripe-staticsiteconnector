<?php
/**
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSiteFileTransformerTest extends SapphireTest {

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
		$this->transformer = singleton('StaticSiteFileTransformer');
		parent::setUp();	
	}
	
	/**
	 * 
	 */
	public function testBuildFileProperties() {
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.zzz', 'image/png');
		$this->assertEquals('assets/Import/Images/test.png', $processFile->Filename);
		
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.zzz', 'image/png');
		$this->assertEquals('assets/Import/Images/test.png', $processFile->Filename);
		
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.png', 'image/png');
		$this->assertEquals('assets/Import/Images/test.png', $processFile->Filename);		
		
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.zzz', 'application/pdf');
		$this->assertEquals('assets/Import/Documents/test.pdf', $processFile->Filename);
		
		// Cannot easily match between, and therefore convert using application/msword => .doc
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.zzz', 'application/msword');
		$this->assertFalse($processFile);
		
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.zzz', 'image/fake');
		$this->assertFalse($processFile);
	}
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, but:
	 * - The URL isn't found in the cache
	 * - "We" in this case is this test which is basically "pretending" to be PHPCrawler
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLNotInCacheIsFile() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsImage1');
		$item = new StaticSiteContentItem($source, '/assets/test-1.png');
		$item->source = $source;
		
		// Fail becuase test.png isn't found in the url cache
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
	public function testTransformForURLIsInCacheNotFile() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsImage1');
		$item = new StaticSiteContentItem($source, '/about-us');
		$item->source = $source;
		
		// Fail becuase we're using a StaticSiteFileTransformer on a Mime-Type of text/html
		$this->assertFalse($this->transformer->transform($item, null, 'Skip'));
		$this->assertFalse($this->transformer->transform($item, null, 'Duplicate'));
		$this->assertFalse($this->transformer->transform($item, null, 'Overwrite'));
	}
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * - "We" in this case is this test which is basically "pretending" to be PHPCrawler
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheIsFile() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsImage1');
		$item = new StaticSiteContentItem($source, '/assets/test-2.png');
		$item->source = $source;
		
		// Fail becuase we're simply using the "skip" strategy. Nothing else needs to be done
		$this->assertFalse($this->transformer->transform($item, null, 'Skip'));
		// Pass becuase we want to perform something on the URL
		$this->assertInstanceOf('StaticSiteTransformResult', $this->transformer->transform($item, null, 'Duplicate'));
		$this->assertInstanceOf('StaticSiteTransformResult', $this->transformer->transform($item, null, 'Overwrite'));
	}	
}
