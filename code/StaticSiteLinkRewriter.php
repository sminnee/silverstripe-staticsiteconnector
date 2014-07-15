<?php
/**
 *
 * Helper class for rewriting links using phpQuery.
 * 
 * @author Sam Minnee <sam@silverstripe.com>
 * @author Russell Michell <russ@silverstripe.com>
 * @author Michael Parkhill <mike@silverstripe.com>
 * @package staticsiteconnector
 * @see {@link StaticSiteRewriteLinksTask}
 */

// We need PHPQuery
require_once(BASE_PATH . '/vendor/electrolinux/phpquery/phpQuery/phpQuery.php');

class StaticSiteLinkRewriter {

	/**
	 * Simple map of tags to count as "linkable"
	 * 
	 * @var array 
	 */
	protected $tagMap = array(
		'a' => 'href',
		'img' => 'src',
	);

	/**
	 * The callback function to run over each link.
	 * 
	 * @var Object
	 */
	protected $callback;

	/**
	 * 
	 * @param $callback
	 * @return void
	 */
	public function __construct($callback) {
		$this->callback = $callback;
	}

	/**
	 * Set a map of tags & attributes to search for URls.
	 * Each key is a tagname, and each value is an array of attribute names.
	 * 
	 * @param array $tagMap
	 * @return void
	 */
	public function setTagMap($tagMap) {
		$this->tagMap = $tagMap;
	}

	/**
	 * Return the tagmap
	 * 
	 * @return array
	 */
	public function getTagMap() {
		return $this->tagMap;
	}

	/**
	 * 
	 * @param type $pq
	 * @return void
	 */
	public function rewriteInPQ($pq) {
		$callback = $this->callback;

		// Make URLs absolute
		foreach($this->tagMap as $tag => $attribute) {
			foreach($pq[$tag] as $tagObj) {
				if($url = pq($tagObj)->attr($attribute)) {
					$newURL = $callback($url);
					pq($tagObj)->attr($attribute, $newURL);
				}
			}
		}
	}

	/**
	 * Rewrite URLs in the given content snippet. Returns the updated content.
	 *
	 * @param string $content The content containing the links to rewrite.
	 * @return string
	 */
	public function rewriteInContent($content) {
		$pq = phpQuery::newDocument($content);
		$this->rewriteInPQ($pq);
		return $pq->html();
	}
}
