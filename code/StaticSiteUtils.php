<?php
/*
 * Basic class for utility methods unsuited to any other class
 */
class StaticSiteUtils {

	/**
	 * Log a message if the logging has been setup according to docs
	 *
	 * @param string $message
	 * @param string $filename
	 * @param string $mime
	 * @return void
	 */
	protected function log($message, $filename=null, $mime=null) {
		$logFile = Config::inst()->get('StaticSiteContentExtractor', 'log_file');
		if(!$logFile) {
			return;
		}

		if(is_writable($logFile) || !file_exists($logFile) && is_writable(dirname($logFile))) {
			$message = $message.($filename?$filename:'').' ('.($mime?$mime:'').')';
			error_log($message. PHP_EOL, 3, $logFile);
		}
	}

}
