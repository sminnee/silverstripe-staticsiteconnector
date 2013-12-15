<?php
/**
 * Tests aspects of Mime-Type pre/post-processing.
 *
 * @author Russell Michell 2013 <russell@silverstripe.com>
 */
class StaticSiteMimeProcessorTest extends SapphireTest {
	
	/*
	 * @var
	 */
	protected $mimeProcessor;
	
	/*
	 * @var array
	 */
	private static $mime_types_image = array(
		'image/jpeg',
		'image/png',
		'image/gif'
	);
	
	/*
	 * @var array
	 */
	private static $mime_types_document = array(
		'application/msword',
		'application/pdf',
		'text/plain'
	);	
	
	public function setUp() {
		$this->mimeProcessor = singleton('StaticSiteMimeProcessor');
	}	
	
	/*
	 * Tests that the correct file-extension is matched with the given mime and suffix and returns a "fixed" file suffix
	 * Tests known working suffixes+mimes
	 */
	public function testExtToMimeCompareMatchFoundFix() {
		$this->assertEquals('jpg', StaticSiteMimeProcessor::ext_to_mime_compare('.zzz', 'image/jpeg', true));
		$this->assertEquals('png', StaticSiteMimeProcessor::ext_to_mime_compare('.zzz', 'image/png', true));
		$this->assertEquals('gif', StaticSiteMimeProcessor::ext_to_mime_compare('.zzz', 'image/gif', true));
		$this->assertFalse(StaticSiteMimeProcessor::ext_to_mime_compare('.zzz', 'unknown', true));
		$this->assertFalse(StaticSiteMimeProcessor::ext_to_mime_compare('.png', 'unknown', true));
		$this->assertFalse(StaticSiteMimeProcessor::ext_to_mime_compare('.zzz', 'unknown', false));
		$this->assertFalse(StaticSiteMimeProcessor::ext_to_mime_compare('.png', 'unknown', false));		
	}
	
	/*
	 * Tests isOfImage() with string mime-types
	 */
	public function testIsOfImageString() {
		$this->assertTrue($this->mimeProcessor->isOfImage('image/jpeg'));
		$this->assertTrue($this->mimeProcessor->isOfImage('image/png'));
		$this->assertTrue($this->mimeProcessor->isOfImage('image/gif'));
		$this->assertFalse($this->mimeProcessor->isOfImage('application/pdf'));
		$this->assertFalse($this->mimeProcessor->isOfImage('application/msword'));
	}
	
	/*
	 * Tests isOfImage() with an array of mime-types
	 */
	public function testIsOfImageArray() {
		$this->assertTrue($this->mimeProcessor->isOfImage(self::$mime_types_image));
		$this->assertFalse($this->mimeProcessor->isOfImage(self::$mime_types_document));
	}	
	
	/*
	 * Tests isOfFile() with string mime-types
	 */
	public function testIsOfFileString() {
		$this->assertTrue($this->mimeProcessor->isOfFile('application/pdf'));
		$this->assertTrue($this->mimeProcessor->isOfFile('application/msword'));
		$this->assertFalse($this->mimeProcessor->isOfFile('image/jpeg'));
		$this->assertFalse($this->mimeProcessor->isOfFile('image/png'));
		$this->assertFalse($this->mimeProcessor->isOfFile('image/gif'));
	}	
	
	/*
	 * Tests isOfFile() with array mime-types
	 */
	public function testIsOfFileArray() {
		$this->assertFalse($this->mimeProcessor->isOfFile(self::$mime_types_image));
		$this->assertTrue($this->mimeProcessor->isOfFile(self::$mime_types_document));
	}
	
	/*
	 * Tests isOfHTML() with string mime-type
	 */
	public function testIsOfHTMLString() {
		$this->assertTrue($this->mimeProcessor->isOfHTML('text/html'));
		$this->assertFalse($this->mimeProcessor->isOfHTML('text/plain'));
		$this->assertFalse($this->mimeProcessor->isOfHTML('image/png'));
	}
	
	/*
	 * Test for bad mime-types
	 */
	public function testIsBadMime() {
		$this->assertTrue($this->mimeProcessor->isBadMimeType('text/text'));
		$this->assertTrue($this->mimeProcessor->isBadMimeType('unknown'));
		$this->assertTrue($this->mimeProcessor->isBadMimeType(''));
		$this->assertTrue($this->mimeProcessor->isBadMimeType(' '));
		$this->assertTrue($this->mimeProcessor->isBadMimeType(null));
		$this->assertFalse($this->mimeProcessor->isBadMimeType('image/png'));
		$this->assertFalse($this->mimeProcessor->isBadMimeType('image/png '));
		$this->assertFalse($this->mimeProcessor->isBadMimeType(' image/png'));
		$this->assertFalse($this->mimeProcessor->isBadMimeType('image/png'));
	}	
	
	/*
	 * Tests get_mime_for_ss_type() for SiteTree
	 */
	public function testGetMimeForSSTypeSiteTree() {
		$this->assertContains('text/html', StaticSiteMimeProcessor::get_mime_for_ss_type('SiteTree'));
		$this->assertContains('text/html', StaticSiteMimeProcessor::get_mime_for_ss_type('sitetree'));
		$this->assertNotContains('image/png', StaticSiteMimeProcessor::get_mime_for_ss_type('sitetree'));
		$this->assertNotContains('image/gif', StaticSiteMimeProcessor::get_mime_for_ss_type('sitetree'));
		$this->assertNotContains('image/jpeg', StaticSiteMimeProcessor::get_mime_for_ss_type('sitetree'));
	}
	
	/*
	 * Tests get_mime_for_ss_type() for File
	 */
	public function testGetMimeForSSTypeFile() {
		$this->assertContains('text/plain', StaticSiteMimeProcessor::get_mime_for_ss_type('File'));
		$this->assertContains('text/plain', StaticSiteMimeProcessor::get_mime_for_ss_type('file'));
		$this->assertContains('application/pdf', StaticSiteMimeProcessor::get_mime_for_ss_type('file'));
		$this->assertContains('application/msword', StaticSiteMimeProcessor::get_mime_for_ss_type('file'));
		$this->assertNotContains('text/html', StaticSiteMimeProcessor::get_mime_for_ss_type('file'));
		$this->assertNotContains('image/png', StaticSiteMimeProcessor::get_mime_for_ss_type('file'));
	}	
	
	/*
	 * Tests get_mime_for_ss_type() for Image
	 */
	public function testGetMimeForSSTypeImage() {
		$this->assertNotContains('text/plain', StaticSiteMimeProcessor::get_mime_for_ss_type('Image'));
		$this->assertNotContains('text/plain', StaticSiteMimeProcessor::get_mime_for_ss_type('image'));
		$this->assertNotContains('application/pdf', StaticSiteMimeProcessor::get_mime_for_ss_type('image'));
		$this->assertNotContains('application/msword', StaticSiteMimeProcessor::get_mime_for_ss_type('image'));
		$this->assertNotContains('text/html', StaticSiteMimeProcessor::get_mime_for_ss_type('image'));
		$this->assertContains('image/png', StaticSiteMimeProcessor::get_mime_for_ss_type('image'));
		$this->assertContains('image/png', StaticSiteMimeProcessor::get_mime_for_ss_type('image'));
	}	
	
	/*
	 * Tests get_mime_for_ss_type() for Unsupported SilverStripe core-classes
	 */
	public function testGetMimeForSSTypeUnsupported() {
		$this->assertFalse(StaticSiteMimeProcessor::get_mime_for_ss_type('Images'));
		$this->assertFalse(StaticSiteMimeProcessor::get_mime_for_ss_type('images'));
		$this->assertFalse(StaticSiteMimeProcessor::get_mime_for_ss_type('ViewableData'));
		$this->assertFalse(StaticSiteMimeProcessor::get_mime_for_ss_type('DataObject'));	
		$this->assertFalse(StaticSiteMimeProcessor::get_mime_for_ss_type('img'));
		$this->assertFalse(StaticSiteMimeProcessor::get_mime_for_ss_type('buckrogers'));
		$this->assertFalse(StaticSiteMimeProcessor::get_mime_for_ss_type('21stcentury'));
	}
}
