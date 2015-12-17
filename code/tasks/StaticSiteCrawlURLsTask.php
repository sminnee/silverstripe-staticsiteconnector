<?php

/**
 * StaticSiteCrawlURLs
 *
 */
class StaticSiteCrawlURLsTask extends BuildTask
{

    public function run($request)
    {
        $id = $request->getVar('ID');
        if (!is_numeric($id) || !$id) {
            echo "<p>Specify ?ID=(number)</p>";
            return;
        }
        // Find all pages
        $contentSource = StaticSiteContentSource::get()->byID($id);
        $contentSource->urllist()->crawl(false, true);
    }
}
