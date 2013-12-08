<?php
/**
 * @todo add test for SchemaCanParseUrl()
 */
class StaticSiteContentSourceTest extends SapphireTest {
	
	/*
	 * @var string
	 */
	public static $fixture_file = 'StaticSiteContentSource.yml';
	
	/*
	 * @var \StaticSiteContentSource
	 */
	protected $source;
	
	/*
	 * Tests that when given a fake URL and Mime, getSchemaForURL() returns a suitable and expected Schema result
	 */
	public function testGetSchemaForURLtextHtml() {
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsHTML1');
		$absoluteURL = $this->source->BaseUrl.'/some-page-or-other';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'text/html');
		$this->assertInstanceOf('StaticSiteContentSource_ImportSchema', $schema);
		$this->assertEquals('/*', $schema->AppliesTo);
		
		// Correct schema is returned via URL matching string as defined by AppliesTo = "/sub-dir/.*"
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsHTML3');
		$absoluteURL = $this->source->BaseUrl.'/sub-dir/some-page-or-other';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'text/html');
		$this->assertEquals("/sub-dir/.*", $schema->AppliesTo);
		
		// No schema is returned via URL matching string as defined by MimeType _not_ matching 'application/pdf'
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsHTML2');
		$absoluteURL = $this->source->BaseUrl.'/some-page-or-other';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'application/pdf');
		$this->assertFalse($schema);
		
		// No schema is returned via URL matching string becuase URL doesn't match any "known" (fixture-defined) pattern
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsHTML5');
		$absoluteURL = $this->source->BaseUrl.'/blah/some-page-or-other';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'text/html');
		$this->assertFalse($schema);
		
		// No schema is returned via URL matching string becuase of an inadequate mime
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsHTML5');
		$absoluteURL = $this->source->BaseUrl.'/blah/some-page-or-other';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'application/pdf');
		$this->assertFalse($schema);
		
		// No schema is returned via URL matching string becuase of an inadequate mime
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsHTML5');
		$absoluteURL = $this->source->BaseUrl.'/blah/some-page-or-other';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'image/png');
		$this->assertFalse($schema);		
		
		// Correct schema is returned via URL matching string as defined by Order = 2 i.e priority order is maintained
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsHTML4');
		$absoluteURL = $this->source->BaseUrl.'/sub-dir/some-page-or-other';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'text/html');	
		$this->assertEquals("2", $schema->Order);		
	}
	
	/*
	 * Tests that when given a fake URL and Mime, getSchemaForURL() returns a suitable and expected Schema result
	 */
	public function testGetSchemaForURLFile() {
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsFile1');
		$absoluteURL = $this->source->BaseUrl.'/assets/some-file-or-other.pdf';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'application/pdf');
		$this->assertInstanceOf('StaticSiteContentSource_ImportSchema', $schema);
		$this->assertEquals('/assets/.*', $schema->AppliesTo);
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		// But we "try it on" with a suffix of .jpg but a Mime-Type of application/pdf
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsFile1');
		$absoluteURL = $this->source->BaseUrl.'/assets/some-file-or-other.jpg';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'application/pdf');
		$this->assertInstanceOf('StaticSiteContentSource_ImportSchema', $schema);
		$this->assertEquals('/assets/.*', $schema->AppliesTo);		
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsFile1');
		$absoluteURL = $this->source->BaseUrl.'/assets/some-file-or-other.pdf';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'application/pdf');
		$this->assertInstanceOf('StaticSiteContentSource_ImportSchema', $schema);
		$this->assertEquals('1', $schema->Order);		
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsFile1');
		$absoluteURL = $this->source->BaseUrl.'/some-file-or-other.pdf';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'image/png');
		$this->assertFalse($schema);
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsFile1');
		$absoluteURL = $this->source->BaseUrl.'/some-file-or-other.pdf';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'text/html');
		$this->assertFalse($schema);			
		
	}
	
	/*
	 * Tests that when given a fake URL and Mime, getSchemaForURL() returns a suitable and expected Schema result
	 */
	public function testGetSchemaForURLImage() {
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsImage1');
		$absoluteURL = $this->source->BaseUrl.'/assets/some-file-or-other.png';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'image/png');
		$this->assertInstanceOf('StaticSiteContentSource_ImportSchema', $schema);
		$this->assertEquals('/assets/.*', $schema->AppliesTo);
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		// But we "try it on" with a .jpg suffix and an image/png Mime-Type
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsImage1');
		$absoluteURL = $this->source->BaseUrl.'/assets/some-file-or-other.jpg';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'image/png');
		$this->assertInstanceOf('StaticSiteContentSource_ImportSchema', $schema);
		$this->assertEquals('/assets/.*', $schema->AppliesTo);		
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsImage1');
		$absoluteURL = $this->source->BaseUrl.'/assets/some-file-or-other.png';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'application/pdf');
		$this->assertFalse($schema);
		
		// Correct schema is returned via URL matching anything - as defined by AppliesTo = ".*"
		$this->source = $this->objFromFixture("StaticSiteContentSource", 'MyContentSourceIsImage1');
		$absoluteURL = $this->source->BaseUrl.'/assets/some-file-or-other.png';
		$schema = $this->source->getSchemaForURL($absoluteURL, 'text/html');
		$this->assertFalse($schema);			
		
	}	
}
