<?php
/**
 * @author Science Ninjas <scienceninjas@silverstripe.com>
 */
class StaticSiteFileTransformerTest extends SapphireTest {

	/*
	 * @var 
	 */
	protected $transformer;
	
	/*
	 * @var string
	 */
	public static $fixture_file = 'StaticSiteContentSource.yml';
	
	/*
	 * Setup
	 * 
	 * @todo Add a fake urls cache file to the tests dir, so that StaticSiteContentItem.php:42 can be run
	 */
	public function setUp() {
		parent::setUp();
		$this->transformer = singleton('StaticSiteFileTransformer');
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
	 * Test what happens when we encounter duplicates and employ the "skip" strategy
	 */
	public function testTransformForDuplicateStrategySkip() {
		$source = $this->objFromFixture('StaticSiteContentSource', 'MyContentSourceIsImage2');
		$source->setCacheDirPath('./');
		$item = new StaticSiteContentItem($source, 44);
		$item->AbsoluteURL = 'http://localhost/images/test.png';
		$item->ProcessedMIME = 'image/png';
		$item->Name = 'test';
		$item->Title = 'test';
		$item->source = $source;
		$parentObject = null;
		$duplicateStrategy = 'Skip';
		$this->assertFalse($this->transformer->transform($item, $parentObject, $duplicateStrategy));
	}
	
	/**
	 * Test what happens when we encounter duplicates and employ the "duplicate" strategy
	 */
//	public function testTransformForDuplicateStrategyDuplicate() {
//		$item = $this->objFromFixture("StaticSiteContentItem", 'MyContentItemFileTest2');
//		$parentObject = null;
//		$duplicateStrategy = 'Duplicate';
//		$this->assertInstanceOf('StaticSiteTransformResult', $this->transformer->transform($item, $parentObject, $duplicateStrategy));
//	}
	
	/**
	 * Test what happens when we encounter duplicates and employ the "overwrite" strategy
	 */
//	public function testTransformForDuplicateStrategyOverwrite() {
//		$item = $this->objFromFixture("StaticSiteContentItem", 'MyContentItemFileTest3');
//		$parentObject = null;
//		$duplicateStrategy = 'Overwrite';
//		$this->assertInstanceOf('StaticSiteTransformResult', $this->transformer->transform($item, $parentObject, $duplicateStrategy));
//	}	
	
}
