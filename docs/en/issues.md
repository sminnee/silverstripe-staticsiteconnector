# ToDo

## StaticSiteConnector Module Issues (in order of severity/importance)

* BUG: Can only crawl VHosts. Websites located on a subdirectory e.g. http://localhost/mysite are only partially crawled.
* BUG: Lists of crawled URLs from large crawls (1000+ pages), slow down the CMS considerably. Suggest show only partial tree under each "Connector" (or optimise existing and problematic CMS JS)
* BUG: Project won't build properly from composer (While it's not on Packagist)
* TASK: Is StaticSiteCrawlURLsTask needed anymore?
* TASK: Translation: Ensure all messages are rendered through _t()
* TASK: Selecting "external content" in the CMS for the first time, shows nothing in the main pane. Show a default connector (e.g. the first) by default.
* TASK: Add new filter expression as per `FileNameFilter` to module _config instead of using str_replace() in StaticSiteFIleTransformer::buildFileProperties()
* ENHANCEMENT: Add "Link rewrite task was run automatically. "[View failed URL rewrite report"]" confirmation text to "successful import" message
* ENHANCEMENT: Add a "Description" field to each schema. Allows users to outline/describe what content from the external site's page-content, each rule refers to.
* ENHANCEMENT: In addition to the "Number of URLs" total under the "Crawl" tab, modify to show a list of totals for each mime-type or SS type (e.g. SiteTree)
* ENHANCEMENT: Add schema export feature for use between SilverStripe installs e.g. CWP
* ENHANCEMENT: Create a multi-select dropdown menu that comprises data from framework/_config/mimetypes.yml
* ENHANCEMENT: Use php-diff lib via composer as the arbiter of change in the StaticSiteUrlRewriteTask::run() method

## External Content Module Issues:

* BUG: Fix the UI under the "Import" tab to store saved values. Currently you will lose your changes if you move away from the "Import" tab and then go back to it.
* [PR#17] ENHANCEMENT: Add a default to "Select how duplicate items should be handled" radio buttons field
* Logic found in StaticSiteTransformResult class should really exist in the external-content module itself.
