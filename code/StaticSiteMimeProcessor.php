<?php
/**
 * Utility class to deal-to all manner of Mime-Type pre/post-processing.
 *
 * @author Russell Michell 2013 <russell@silverstripe.com>
 */
class StaticSiteMimeProcessor extends Object {

	/**
	 *
	 * @var array internal "cache" of mime-types
	 */
	public $mimeTypes;

	/*
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$args = func_get_args();
		if(isset($args[0])) {
			$mimeTypes = $args[0];
			$this->setMimes($mimeTypes);
		}
	}

	/*
	 * Based on one of three SilverStripe core classes, returns an array of suitable mime-types
	 * from SilverStripe config, used to represent matching content or all associated mimes if no type is passed.
	 *
	 * @param $ssType one of: SiteTree, File, Image
	 * @return array $mimes
	 */
	public static function get_mime_for_ss_type($SSType=null) {
		$httpMimeTypes = Config::inst()->get('HTTP', 'MimeTypes');
		$ssTypeMimeMap = self::ss_type_to_suffix_map();

		$mimes = array(
			'sitetree' => array(),
			'file' => array(),
			'image' => array(),
		);
		
		// Only support specific classes
		if($SSType && !in_array(strtolower($SSType), array_keys($mimes))) {
			return false;
		}

		foreach($httpMimeTypes as $mimeKey => $mimeType) {
			// SiteTree
			if(in_array($mimeKey,$ssTypeMimeMap['sitetree'])) {
				$mimes['sitetree'][] = $mimeType;
			}
			// File
			if(in_array($mimeKey,$ssTypeMimeMap['file'])) {
				$mimes['file'][] = $mimeType;
			}
			// Image
			if(in_array($mimeKey,$ssTypeMimeMap['image'])) {
				$mimes['image'][] = $mimeType;
			}
		}

		if($SSType) {
			$SSType = strtolower($SSType);
			if(isset($mimes[$SSType])) {
				return $mimes[$SSType];
			}
		}

		return $mimes;
	}

	/*
	 * Return a mapping of SS types (File, SiteTree etc) to suitable file-extensions out of the File class
	 *
	 * @param string $SSType
	 * @return array
	 */
	public static function ss_type_to_suffix_map($SSType = null) {
		$mimeCategories = singleton('File')->config()->app_categories;
		/*
		 * Imported files and images are going to passed through to Upload#load() and checked aginst File::$app_categories so use this method to
		 * filter in calls to DataObject#validate()
		 */
		// Get SilverStripe supported SiteTree-ish mime categories
		$mimeKeysForSiteTree = array('html','htm','xhtml');
		// Get SilverStripe supported File-ish mime categories
		// File contains values of $mimeKeysForSiteTree which we don't want
		$mimeKeysForFile = array_merge(
			array_splice($mimeCategories['doc'],14,2),
			array_splice($mimeCategories['doc'],0,11)
		);
		// Get SilverStripe supported Image-ish mime categories
		$mimeKeysForImage = $mimeCategories['image'];
		$map = array(
			'sitetree'	=> $mimeKeysForSiteTree,
			'file'		=> $mimeKeysForFile,
			'image'		=> $mimeKeysForImage
		);
		if($SSType) {	
			$SSType = strtolower($SSType);
			// Only support specific classes
			if(!in_array(strtolower($SSType), array_keys($mimes))) {
				return false;
			}			
			return $map[$key];
		}
		return $map;
	}

	/*
	 * Compares a file-extension with a mime type and returns true if the passed extension matches the passed mime
	 *
	 * @param string $ext The file extension to compare e.g. ".doc"
	 * @param string $mime The Mime-Type to compare e.g. application/msword
	 * @param boolean $fix whether or not to try and "fix" borked file-extensions coming through from third-parties.
	 * - If true, the matched extension is returned (if found, otherwise false) instead of boolean false
	 * - This is a pretty sketchy way of doing things and relies on the file-extension string comprising the mime-type
	 * - e.g. "pdf" can be found in "application/pdf" but "doc" cannot be found in "application/msword"
	 * @return mixed boolean or string $ext | $coreExt if the $fix param is set to true, no extra processing is required
	 * @todo this method could really benefit from some tests..
	 */
	public static function ext_to_mime_compare($ext, $mime, $fix = false) {
		$httpMimeTypes = Config::inst()->get('HTTP', 'MimeTypes');
		$mimeCategories = singleton('File')->config()->app_categories;
		list($ext,$mime) = array(strtolower($ext),strtolower($mime));
		$notAuthoratative = !isset($httpMimeTypes[$ext]);					// We've found ourselves a weird extension
		$notMatch = (!$notAuthoratative && $httpMimeTypes[$ext] !== $mime);	// No match found for passed extension in our ext=>mime mapping from config
		if($notAuthoratative || $notMatch) {
			if(!$fix) {
				return false;
			}
			// Attempt to "fix" broken or badly encoded file-extensions by guessing what it should be, based on $mime
			$coreExts = array_merge($mimeCategories['doc'],$mimeCategories['image']);
			foreach($coreExts as $coreExt) {
				// Make sure we check the correct category so we don't find a match for ms-excel in the image \File category (.cel) !!
				$isFile = in_array($coreExt,$mimeCategories['doc']) && singleton(__CLASS__)->isOfFile($mime);		// dirty
				$isImge = in_array($coreExt,$mimeCategories['image']) && singleton(__CLASS__)->isOfImage($mime);	// more dirt
				if(($isFile || $isImge) && stristr($mime,$coreExt) !== false) {
					// "Manually" force "jpg" as the file-suffix to be returned
					return $coreExt == 'jpeg' ? 'jpg' : $coreExt;
				}
			}
			return false;
		}
		return false;
 	}

	/*
	 * Post-proces user-inputted mime-types. Allows space, comma or newline
	 * delimited mime-types input into a TextareaField
	 *
	 * @param string $mimeTypes
	 * @return array - returns an array of mimetypes
	 */
	public static function get_mimetypes_from_text($mimeTypes) {
		$mimes = preg_split("#[\r\n\s,]+#",trim($mimeTypes));
		$_mimes = array();
		foreach($mimes as $mimeType) {
			// clean 'em up a little
			$_mimes[] = self::cleanse($mimeType);
		}
		return $_mimes;
	}

	/*
	 * Simple cleanup utility
	 *
	 * @param string $mimeType
	 * @return string
	 */
	public static function cleanse($mimeType) {
		if(!$mimeType) {
			return '';
		}
		return strtolower(trim($mimeType));
	}

	/*
	 * Takes an array of mime-type strings and simply returns true after the first Image-ish mime-type is found
	 *
	 * @param mixed $mimeTypes
	 * @return boolean
	 */
	public function isOfImage($mimeTypes) {
		if(!is_array($mimeTypes)) {
			$mimeTypes = array(self::cleanse($mimeTypes));
		}
		foreach($mimeTypes as $mime) {
			if(in_array($mime,self::get_mime_for_ss_type('image'))) {
				return true;
			}
		}
		return false;
	}

	/*
	 * Takes an array of mime-type strings and simply returns true after the first File-ish mime-type is found
	 *
	 * @param mixed $mimeTypes
	 * @return boolean
	 */
	public function isOfFile($mimeTypes) {
		if(!is_array($mimeTypes)) {
			$mimeTypes = array(self::cleanse($mimeTypes));
		}
		foreach($mimeTypes as $mime) {
			if(in_array($mime,self::get_mime_for_ss_type('file'))) {
				return true;
			}
		}
		return false;
	}

	/*
	 * Takes an array of mime-type strings and simply returns true after the first SiteTree-ish mime-type is found
	 *
	 * @param mixed $mimeTypes
	 * @return boolean
	 */
	public function isOfHtml($mimeTypes) {
		if(!is_array($mimeTypes)) {
			$mimeTypes = array(self::cleanse($mimeTypes));
		}
		foreach($mimeTypes as $mime) {
			if(in_array($mime,self::get_mime_for_ss_type('sitetree'))) {
				return true;
			}
		}
		return false;
	}

	/*
	 * Simple "shortcut" to isOfFile() and isOfImage()
	 *
	 * @param mixed $mimeTypes
	 * @return boolean
	 */
	public function isOfFileOrImage($mimeTypes) {
		if(!is_array($mimeTypes)) {
			$mimeTypes = array(self::cleanse($mimeTypes));
		}
		if($this->isOfFile($mimeTypes) || $this->isOfImage($mimeTypes)) {
			return true;
		}
		return false;
	}
	
	/*
	 * Ascertain passed $mime is not something we can do anything useful with
	 * 
	 * @param string $mime
	 * @return boolean
	 */
	public function isBadMimeType($mime) {
		return (!$this->isOfFileOrImage($mime) && !$this->isOfHtml($mime));
	}

	/*
	 *
	 * Getters & Setters
	 * -----------------
	 *
	 */

	/*
	 * @param array $mimes
	 */
	public function setMimes($mimeTypes) {
		$this->mimeTypes = $mimeTypes;
	}

	/*
	 * @return array
	 */
	public function getMimes() {
		return $this->mimeTypes;
	}
}
