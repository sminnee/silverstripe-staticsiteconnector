<?php
/**
 * A Queued jobs wrapper for StaticSiteRewriteLinksTask
 *
 */
class StaticSiteRewriteLinksJob extends AbstractQueuedJob implements QueuedJob {

	/**
	 * 
	 * @return string
	 */
	public function getJobType() {
		$this->totalSteps = 1;
		return QueuedJob::QUEUED;
	}

	/**
	 * Starts the crawl urls task
	 */
	public function process() {
		$task = singleton('StaticSiteRewriteLinksTask');
		$task->run();
		$this->currentStep = 1;
		$this->isComplete = true;
	}
}