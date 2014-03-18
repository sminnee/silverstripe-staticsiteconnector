<?php
/**
 * 
 * @author Russell Michell <russell@silverstripe.com>
 * @package staticsiteconnector
 * @todo fixup buildFileProperties() and comment assertions in testBuildFileProperties()
 * @todo Duplicating files during test-runs doesn't work so testTransformForURLIsInCacheIsFileStrategyDuplicate() fails if uncommented
 */
class StaticSiteFileTransformerTest extends SapphireTest {

	/**
	 * 
	 * @var \StaticSiteFileTransformer
	 */
	protected $transformer;
	
	/**
	 * 
	 * @var string
	 */
	public static $fixture_file = 'StaticSiteContentSource.yml';
	
	/**
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
		
		// 'unknown' is what's used as the mime-type for parent URLs that are defined by string manioulation, not actual file-analysis
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test', 'unknown');
		$this->assertFalse($processFile);
		
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.png', 'unknown');
		$this->assertFalse($processFile);		
		
		// Cannot easily match between, and therefore convert using application/msword => .doc
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.zzz', 'application/msword');
		$this->assertFalse($processFile);
		
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.zzz', 'image/fake');
		$this->assertFalse($processFile);
		
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.png.gif', 'image/gif');
		$this->assertEquals('assets/Import/Images/test-png.gif', $processFile->Filename);	 
		
		$processFile = $this->transformer->buildFileProperties(new File(), 'http://localhost/images/test.gif.png', 'image/png');
		$this->assertEquals('assets/Import/Images/test-gif.png', $processFile->Filename);		
	}
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, but:
	 * - The URL isn't found in the cache
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
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheNotFile() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsImage1');
		$item = new StaticSiteContentItem($source, '/test-page-44');
		$item->source = $source;
		
		// Fail becuase we're using a StaticSiteFileTransformer on a Mime-Type of text/html
		$this->assertFalse($this->transformer->transform($item, null, 'Skip'));
		$this->assertFalse($this->transformer->transform($item, null, 'Duplicate'));
		$this->assertFalse($this->transformer->transform($item, null, 'Overwrite'));
	}
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheIsFileStrategyDuplicate() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsImage2');
		$item = new StaticSiteContentItem($source, '/graphics/my-image.png');
		$item->source = $source;
		
		// Pass becuase we do want to perform something on the URL
		$this->assertInstanceOf('StaticSiteTransformResult', $fileStrategyDup1 = $this->transformer->transform($item, null, 'Duplicate'));
		$this->assertInstanceOf('StaticSiteTransformResult', $fileStrategyDup2 = $this->transformer->transform($item, null, 'Duplicate'));
		
		// Pass becuase regardless of duplication strategy, we should be getting our filenames post-processed
		// Because we're trying to duplicate (copy), SilverStripe should rename the file with a '-2' suffix
		$this->assertEquals('assets/Import/Images/my-image.png', $fileStrategyDup1->file->Filename);
		$this->assertEquals('assets/Import/Images/my-image.png', $fileStrategyDup2->file->Filename);
		/*
		 * Files don't duplicate in the same way as pages are. Duplicate images _are_ created, but their
		 * existence is tested for slightly differently.
		 */
		$this->assertNotEquals($fileStrategyDup1->file->ID, $fileStrategyDup2->file->ID);
	}	
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheIsFileStrategyOverwrite() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsImage4');
		$item = new StaticSiteContentItem($source, '/graphics/her-image.png');
		$item->source = $source;
		
		// Pass becuase we do want to perform something on the URL
		$this->assertInstanceOf('StaticSiteTransformResult', $fileStrategyOvr1 = $this->transformer->transform($item, null, 'Overwrite'));
		$this->assertInstanceOf('StaticSiteTransformResult', $fileStrategyOvr2 = $this->transformer->transform($item, null, 'Overwrite'));
		
		// Pass becuase regardless of duplication strategy, we should be getting our filenames post-processed
		$this->assertEquals('assets/Import/Images/her-image.png', $fileStrategyOvr1->file->Filename);
		$this->assertEquals('assets/Import/Images/her-image.png', $fileStrategyOvr2->file->Filename);
		// Ids should be the same becuase overwrite really means update
		$this->assertEquals($fileStrategyOvr1->file->ID, $fileStrategyOvr2->file->ID);
	}	
	
	/**
	 * Test what happens when we define what we want to do when encountering duplicates, and:
	 * - The URL represents a Mime-Type which does match our transformer
	 * 
	 * @todo employ some proper mocking
	 */
	public function testTransformForURLIsInCacheIsFileStrategySkip() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsImage3');
		$item = new StaticSiteContentItem($source, '/assets/test-3.png');
		$item->source = $source;
		
		// Fail becuase we're simply using the "skip" strategy. Nothing else needs to be done
		$this->assertFalse($this->transformer->transform($item, null, 'Skip'));
	}	
}
