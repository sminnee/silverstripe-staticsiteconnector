<?php

require_once(dirname(__FILE__) . "/../thirdparty/phpQuery/phpQuery/phpQuery.php");

/**
 * Helper class for rewriting links using phpQuery.
 */
class StaticSiteLinkRewriter {

	protected $tagMap = array(
		'a' => 'href',
		'img' => 'src',
	);

	protected $tagMap2 = array(
		'a' => array('href'),
		'img' => array('src'),
	);

	protected $callback;

	function __construct($callback) {
		$this->callback = $callback;
	}

	/**
	 * Set a map of tags & attributes to search for URls.
	 *
	 * Each key is a tagname, and each value is an array of attribute names.
	 */
	function setTagMap($tagMap) {
		$this->tagMap = $tagMap;
	}

	/**
	 * Return the tagmap
	 */
	function getTagMap($tagMap) {
		$this->tagMap = $tagMap;
	}

	/**
	 * Rewrite URLs in a PHPQuery object.  The content of the object will be modified.
	 *
	 * @param  phpQuery $pq The content containing the links to rewrite
	 */
	function rewriteInPQ($pq) {

		$callback = $this->callback;

		// Make URLs absolute
		foreach($this->tagMap as $element) {
			foreach($pq[$element] as $tag => $attribute) {
		//	foreach($attributes as $attribute) {
					if($url = pq($tag)->attr($attribute)) {
						$newURL = $callback($url);
						pq($tag)->attr($attribute, $newURL);
					}
		//		}
			}
		}
	}

	/**
	 * Rewrite URLs in the given content snippet.  Returns the updated content.
	 *
	 * @param  phpQuery $pq The content containing the links to rewrite
	 */
	function rewriteInContent($content) {
		$pq = phpQuery::newDocument($content);
		$this->rewriteInPQ($pq);
		return $pq->html();
	}

}