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
	 * Setup
	 */
	public function setUp() {
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
	
}
