<?php
/**
 * Utility class to deal-to all manner of Mime-Type pre/post-processing.
 *
 * @author Russell Michell 2013 russell@silverstripe.com
 * @todo set both arrays of Mime-Types and single Mime-Types on construct, to class properties so we can go $mime->isOfImage() and the like..
 */
class MimeTypeProcessor extends Object {

	/**
	 *
	 * @var array internal "cache" of mime-types
	 */
	public $mimeTypes;

	public function __construct() {
		parent::__construct();
		$args = func_get_args();
		if(isset($args[0])) {
			$mimeTypes = $args[0];
			$this->setMimes($mimeTypes);
		}
	}

	/*
	 * Based on one of three SS classes, returns an array of suitable mime-types from SS config used to represent matching content,
	 * or all associated mimes of no type is passed.
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
		$mimeKeysForSiteTree = array('html','htm','xhtml');
		// File contains values of $mimeKeysForSiteTree which we don't want
		$mimeKeysForFile = array_merge(
			array_splice($mimeCategories['doc'],14,2),
			array_splice($mimeCategories['doc'],0,11)
		);
		$mimeKeysForImage = $mimeCategories['image'];
		$map = array(
			'sitetree'	=> $mimeKeysForSiteTree,
			'file'		=> $mimeKeysForFile,
			'image'		=> $mimeKeysForImage
		);
		if($SSType) {
			$SSType = strtolower($SSType);
			return $map[$key];
		}
		return $map;
	}

	/*
	 * Compares a file-extension with a mime type and returns true if the passed extension matches the passed mime
	 *
	 * @param string $ext The file extension to comapre e.g. .doc
	 * @param string $mime The Mime-Type to compare e.g. application/msword
	 * @param boolean $fix whether or not to try and "fix" borked file-extensions coming through from third-parties.
	 * - If true, the matched extension is returned instead of boolean true
	 * - This is a pretty sketchy way of doing things and relies on the file-extension as a string bein gpresent somewhere in the mime-type
	 * - e.g. "pdf" can be found in "application/pdf" but "doc" cannot be found in "application/msword"
	 * @return mixed boolean or string $coreExt if the $fix param is set to true
	 */
	public static function ext_to_mime_compare($ext,$mime,$fix = false) {
		$httpMimeTypes = Config::inst()->get('HTTP', 'MimeTypes');
		$mimeCategories = singleton('File')->config()->app_categories;
		list($ext,$mime) = array(strtolower($ext),strtolower($mime));
		$notAuthoratative = !isset($httpMimeTypes[$ext]);
		$notMatch = ($httpMimeTypes[$ext] !== $mime);
		if($notAuthoratative || $notMatch) {
			if(!$fix) {
				return false;
			}
			// Attempt to "fix" broken or badly encoded extensions by guessing what the extension should be from the mime-type
			$coreExts = array_merge($mimeCategories['doc'],$mimeCategories['image']);
			foreach($coreTypes as $coreExt) {
				if(stristr($mime,$coreExt) !== false) {
					return $coreExt;
				}
			}
		}
		return true;
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