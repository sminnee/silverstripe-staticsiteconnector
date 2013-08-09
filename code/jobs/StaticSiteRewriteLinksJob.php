<?php
/**
 * A Queued jobs wrapper for StaticSiteRewriteLinksTask
 *
 */
class StaticSiteRewriteLinksJob extends AbstractQueuedJob implements QueuedJob {

	/**
	 * The ID number of the StaticSiteContentSource which has the links to be rewritten
	 *
	 * @var int
	 */
	protected $contentSourceID;

	/**
	 * sets the content source id
	 */
	public function __construct($contentSourceID = null) {
		if ($contentSourceID) {
			$this->contentSourceID = $contentSourceID;
		}
	}

	/**
	 * 
	 * @return string
	 */
	public function getJobType() {
		$this->totalSteps = 1;
		return QueuedJob::QUEUED;
	}

	/**
	 * Starts the rewrite links task
	 */
	public function process() {
		$task = singleton('StaticSiteRewriteLinksTask');
		$task->setContentSourceID($this->contentSourceID);
		$task->process();
		$this->currentStep = 1;
		$this->isComplete = true;
	}
}